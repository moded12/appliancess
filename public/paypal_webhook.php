<?php
/**
 * PayPal Webhook Handler
 * المسار: public/paypal_webhook.php
 * 
 * Receives and processes PayPal webhook events.
 * Handles PAYMENT.CAPTURE.COMPLETED and similar events.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';
$config = require __DIR__ . '/../config.php';

// Get raw POST body
$payload = file_get_contents('php://input');

// Log incoming webhook
error_log('PayPal webhook received: ' . substr($payload, 0, 200));

// Get PayPal headers for verification
$headers = getallheaders();
$transmissionId = $headers['Paypal-Transmission-Id'] ?? '';
$transmissionTime = $headers['Paypal-Transmission-Time'] ?? '';
$certUrl = $headers['Paypal-Cert-Url'] ?? '';
$authAlgo = $headers['Paypal-Auth-Algo'] ?? '';
$transmissionSig = $headers['Paypal-Transmission-Sig'] ?? '';

// Decode payload
$event = json_decode($payload, true);

if (!$event || !isset($event['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    error_log('PayPal webhook error: Invalid payload');
    exit;
}

// Verify PayPal webhook signature (if webhook ID is configured)
$webhookId = $config['paypal_webhook_id'] ?? '';
if (!empty($webhookId)) {
    $verified = verify_paypal_webhook($payload, $headers, $webhookId);
    if (!$verified) {
        http_response_code(401);
        echo json_encode(['error' => 'Webhook signature verification failed']);
        error_log('PayPal webhook error: Signature verification failed');
        exit;
    }
} else {
    // Log warning but continue processing (useful for sandbox testing)
    error_log('PayPal webhook: PAYPAL_WEBHOOK_ID not configured, skipping signature verification');
}

$eventType = $event['event_type'];
$resource = $event['resource'] ?? [];

error_log('PayPal webhook event type: ' . $eventType);

try {
    switch ($eventType) {
        case 'PAYMENT.CAPTURE.COMPLETED':
            handlePaymentCaptureCompleted($pdo, $resource, $payload);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
            handlePaymentCaptureDenied($pdo, $resource, $payload);
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            handlePaymentCaptureRefunded($pdo, $resource, $payload);
            break;
            
        case 'CHECKOUT.ORDER.APPROVED':
            // Order approved but not yet captured
            error_log('PayPal CHECKOUT.ORDER.APPROVED: ' . ($resource['id'] ?? 'unknown'));
            break;
            
        case 'CHECKOUT.ORDER.COMPLETED':
            handleCheckoutOrderCompleted($pdo, $resource, $payload);
            break;
            
        default:
            // Log unhandled event types
            error_log('PayPal webhook: Unhandled event type ' . $eventType);
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log('PayPal webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing error']);
}

/**
 * Handle PAYMENT.CAPTURE.COMPLETED event
 */
function handlePaymentCaptureCompleted(PDO $pdo, array $capture, string $rawPayload): void
{
    $captureId = $capture['id'] ?? '';
    $amount = $capture['amount']['value'] ?? '0';
    $currency = $capture['amount']['currency_code'] ?? 'USD';
    
    // Try to find the order from custom_id or supplementary_data
    $orderId = 0;
    
    // PayPal may include custom_id in the capture
    if (!empty($capture['custom_id'])) {
        $orderId = (int) $capture['custom_id'];
    }
    
    // Try to find by invoice_id
    if (!$orderId && !empty($capture['invoice_id'])) {
        $orderId = (int) $capture['invoice_id'];
    }
    
    // Look for order_id in supplementary_data
    if (!$orderId && !empty($capture['supplementary_data']['related_ids']['order_id'])) {
        // This is the PayPal order ID, not our order ID
        $paypalOrderId = $capture['supplementary_data']['related_ids']['order_id'];
        $stmt = $pdo->prepare("SELECT order_id FROM payments WHERE transaction_id = :tid AND gateway = 'paypal' LIMIT 1");
        $stmt->execute([':tid' => $paypalOrderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $orderId = (int) $row['order_id'];
        }
    }
    
    error_log("PayPal PAYMENT.CAPTURE.COMPLETED: capture=$captureId, order=$orderId, amount=$amount $currency");
    
    if (!$orderId) {
        error_log('PayPal webhook: Cannot find order_id for capture ' . $captureId);
        return;
    }
    
    // Find payment record
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND gateway = 'paypal' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        // Update payment record
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'completed',
            $captureId,
            $rawPayload
        );
        
        // Update order status
        update_order_payment_status($pdo, $orderId, 'paid', 'processing');
        
        error_log("PayPal webhook: Order $orderId marked as paid");
    }
}

/**
 * Handle PAYMENT.CAPTURE.DENIED event
 */
function handlePaymentCaptureDenied(PDO $pdo, array $capture, string $rawPayload): void
{
    $captureId = $capture['id'] ?? '';
    
    // Try to find order ID
    $orderId = 0;
    if (!empty($capture['custom_id'])) {
        $orderId = (int) $capture['custom_id'];
    }
    
    error_log("PayPal PAYMENT.CAPTURE.DENIED: capture=$captureId, order=$orderId");
    
    if (!$orderId) {
        return;
    }
    
    // Find and update payment record
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND gateway = 'paypal' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'failed',
            $captureId,
            $rawPayload
        );
        
        // Update order payment status
        update_order_payment_status($pdo, $orderId, 'failed', 'new');
        
        error_log("PayPal webhook: Payment for order $orderId marked as failed");
    }
}

/**
 * Handle PAYMENT.CAPTURE.REFUNDED event
 */
function handlePaymentCaptureRefunded(PDO $pdo, array $capture, string $rawPayload): void
{
    $captureId = $capture['id'] ?? '';
    
    // Try to find order ID
    $orderId = 0;
    if (!empty($capture['custom_id'])) {
        $orderId = (int) $capture['custom_id'];
    }
    
    error_log("PayPal PAYMENT.CAPTURE.REFUNDED: capture=$captureId, order=$orderId");
    
    if (!$orderId) {
        return;
    }
    
    // Find and update payment record
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND gateway = 'paypal' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'refunded',
            $captureId,
            $rawPayload
        );
        
        error_log("PayPal webhook: Payment for order $orderId marked as refunded");
    }
}

/**
 * Handle CHECKOUT.ORDER.COMPLETED event
 */
function handleCheckoutOrderCompleted(PDO $pdo, array $order, string $rawPayload): void
{
    $paypalOrderId = $order['id'] ?? '';
    
    error_log("PayPal CHECKOUT.ORDER.COMPLETED: order=$paypalOrderId");
    
    // Find payment by PayPal order ID
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_id = :tid AND gateway = 'paypal' LIMIT 1");
    $stmt->execute([':tid' => $paypalOrderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment && $payment['status'] !== 'completed') {
        $orderId = (int) $payment['order_id'];
        
        // Get capture ID from purchase_units
        $captureId = $paypalOrderId;
        $captures = $order['purchase_units'][0]['payments']['captures'] ?? [];
        if (!empty($captures)) {
            $captureId = $captures[0]['id'] ?? $paypalOrderId;
        }
        
        // Update payment record
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'completed',
            $captureId,
            $rawPayload
        );
        
        // Update order status
        update_order_payment_status($pdo, $orderId, 'paid', 'processing');
        
        error_log("PayPal webhook: Order $orderId marked as paid (from CHECKOUT.ORDER.COMPLETED)");
    }
}
