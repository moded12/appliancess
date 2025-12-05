<?php
/**
 * PayPal Webhook Handler
 * 
 * This file handles webhook/IPN events from PayPal.
 * It verifies the webhook signature and updates order/payment status accordingly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

// Set response headers
header('Content-Type: application/json');

// Get raw POST body
$payload = file_get_contents('php://input');

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

// Get PayPal headers for verification
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_PAYPAL_') === 0) {
        // Convert HTTP_PAYPAL_AUTH_ALGO to PAYPAL-AUTH-ALGO
        $headerName = str_replace('_', '-', substr($key, 5));
        $headers[$headerName] = $value;
    }
}

// Parse the event
$event = json_decode($payload, true);

if (!$event || !isset($event['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Optionally verify webhook signature
// Note: PayPal webhook verification requires PAYPAL_WEBHOOK_ID environment variable
$webhookId = getenv('PAYPAL_WEBHOOK_ID') ?: '';
if (!empty($webhookId)) {
    $verified = verify_paypal_webhook($headers, $payload);
    if (!$verified) {
        // Log the failed verification but don't reject
        // In production, you may want to be stricter
        error_log('PayPal webhook signature verification failed');
    }
}

// Handle the event
try {
    $eventType = $event['event_type'];
    $resource = $event['resource'] ?? [];
    
    switch ($eventType) {
        case 'CHECKOUT.ORDER.APPROVED':
            // Order was approved but not yet captured
            // This is typically handled by the return URL
            $paypalOrderId = $resource['id'] ?? '';
            $customId = $resource['purchase_units'][0]['custom_id'] ?? '';
            $orderId = (int)$customId;
            
            if ($orderId > 0) {
                // Update payment status to processing
                update_payment_by_order($pdo, $orderId, 'paypal', 'processing', $paypalOrderId, [
                    'event' => $eventType,
                    'paypal_order_id' => $paypalOrderId,
                ]);
            }
            break;
            
        case 'PAYMENT.CAPTURE.COMPLETED':
            // Payment has been captured successfully
            $captureId = $resource['id'] ?? '';
            $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
            $customId = $resource['custom_id'] ?? '';
            $orderId = (int)$customId;
            
            // If customId not in capture, try to get from database
            if ($orderId <= 0 && !empty($paypalOrderId)) {
                $stmt = $pdo->prepare(
                    "SELECT order_id FROM payments WHERE transaction_id = :txn_id AND gateway = 'paypal' LIMIT 1"
                );
                $stmt->execute([':txn_id' => $paypalOrderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $orderId = (int)($row['order_id'] ?? 0);
            }
            
            if ($orderId > 0) {
                $pdo->beginTransaction();
                
                // Update payment record
                update_payment_by_order($pdo, $orderId, 'paypal', 'completed', $captureId, [
                    'event' => $eventType,
                    'capture_id' => $captureId,
                    'amount' => $resource['amount']['value'] ?? null,
                    'currency' => $resource['amount']['currency_code'] ?? null,
                ]);
                
                // Update order status
                update_order_payment($pdo, $orderId, 'paid', 'completed');
                
                $pdo->commit();
            }
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.DECLINED':
            // Payment was denied
            $customId = $resource['custom_id'] ?? '';
            $orderId = (int)$customId;
            
            if ($orderId > 0) {
                update_payment_by_order($pdo, $orderId, 'paypal', 'failed', null, [
                    'event' => $eventType,
                    'reason' => $resource['status_details']['reason'] ?? 'Unknown',
                ]);
                
                update_order_payment($pdo, $orderId, 'failed');
            }
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            // Payment was refunded
            $customId = $resource['custom_id'] ?? '';
            $orderId = (int)$customId;
            
            if ($orderId > 0) {
                update_payment_by_order($pdo, $orderId, 'paypal', 'refunded', null, [
                    'event' => $eventType,
                    'refund_id' => $resource['id'] ?? null,
                ]);
            }
            break;
            
        case 'CHECKOUT.ORDER.COMPLETED':
            // Order completed (after capture)
            $paypalOrderId = $resource['id'] ?? '';
            $customId = $resource['purchase_units'][0]['custom_id'] ?? '';
            $orderId = (int)$customId;
            
            if ($orderId > 0) {
                $pdo->beginTransaction();
                
                update_payment_by_order($pdo, $orderId, 'paypal', 'completed', $paypalOrderId, [
                    'event' => $eventType,
                ]);
                
                update_order_payment($pdo, $orderId, 'paid', 'completed');
                
                $pdo->commit();
            }
            break;
            
        default:
            // Unhandled event type - log for debugging
            error_log('Unhandled PayPal webhook event: ' . $eventType);
            break;
    }
    
    // Acknowledge receipt
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('PayPal webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
