<?php
/**
 * PayPal Webhook Handler
 * Handles PayPal IPN/Webhook events for payment confirmation
 * 
 * This is a basic handler that logs events and updates payment status
 * when relevant PayPal events are received.
 * 
 * Supported events:
 * - CHECKOUT.ORDER.APPROVED
 * - PAYMENT.CAPTURE.COMPLETED
 * - PAYMENT.CAPTURE.DENIED
 */

declare(strict_types=1);

// Don't start session for webhook
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';
$config = require __DIR__ . '/../config.php';

// Set response type
header('Content-Type: application/json');

// Get the raw POST body
$payload = file_get_contents('php://input');

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload']);
    exit;
}

$event = json_decode($payload, true);

if (!$event || !isset($event['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventType = $event['event_type'] ?? '';
$resource = $event['resource'] ?? [];

// Log the event
error_log('PayPal webhook received: ' . $eventType);
error_log('PayPal webhook payload: ' . $payload);

try {
    switch ($eventType) {
        case 'CHECKOUT.ORDER.APPROVED':
            handleOrderApproved($pdo, $resource);
            break;
            
        case 'PAYMENT.CAPTURE.COMPLETED':
            handleCaptureCompleted($pdo, $resource);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.DECLINED':
            handleCaptureFailed($pdo, $resource);
            break;
            
        default:
            // Log unhandled event
            error_log('Unhandled PayPal event type: ' . $eventType);
    }
    
    // Acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Throwable $e) {
    error_log('PayPal webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle CHECKOUT.ORDER.APPROVED event
 * Note: The actual capture should happen in paypal_return.php
 * This just logs the approval
 */
function handleOrderApproved(PDO $pdo, array $resource): void {
    $paypalOrderId = $resource['id'] ?? '';
    
    // Try to get our order ID from custom_id
    $orderId = 0;
    if (isset($resource['purchase_units'][0]['custom_id'])) {
        $orderId = (int) $resource['purchase_units'][0]['custom_id'];
    } elseif (isset($resource['purchase_units'][0]['reference_id'])) {
        $orderId = (int) $resource['purchase_units'][0]['reference_id'];
    }
    
    error_log('PayPal order approved: PayPal ID = ' . $paypalOrderId . ', Order ID = ' . $orderId);
    
    if ($orderId <= 0) {
        return;
    }
    
    // Update payment status to processing (not completed yet - needs capture)
    update_payment_by_order(
        $pdo,
        $orderId,
        'paypal',
        'processing',
        $paypalOrderId,
        $resource
    );
}

/**
 * Handle PAYMENT.CAPTURE.COMPLETED event
 */
function handleCaptureCompleted(PDO $pdo, array $resource): void {
    $captureId = $resource['id'] ?? '';
    
    // Try to get our order ID
    $orderId = 0;
    if (isset($resource['custom_id'])) {
        $orderId = (int) $resource['custom_id'];
    }
    
    // Also try supplementary data
    if ($orderId <= 0 && isset($resource['supplementary_data']['related_ids']['order_id'])) {
        // This is PayPal's order ID, not ours - try to find by transaction
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'];
        
        // Find payment by PayPal order ID
        try {
            $stmt = $pdo->prepare("
                SELECT order_id FROM payments 
                WHERE gateway = 'paypal' AND transaction_id = :tid 
                LIMIT 1
            ");
            $stmt->execute([':tid' => $paypalOrderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $orderId = (int) $row['order_id'];
            }
        } catch (Throwable $e) {
            error_log('Error finding order by PayPal ID: ' . $e->getMessage());
        }
    }
    
    error_log('PayPal capture completed: Capture ID = ' . $captureId . ', Order ID = ' . $orderId);
    
    if ($orderId <= 0) {
        return;
    }
    
    // Verify order exists and isn't already paid
    $order = get_order($pdo, $orderId);
    if (!$order) {
        return;
    }
    
    if (in_array($order['payment_status'] ?? '', ['paid', 'completed'], true)) {
        error_log('PayPal capture: Order already paid: ' . $orderId);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update payment
        update_payment_by_order(
            $pdo,
            $orderId,
            'paypal',
            'completed',
            $captureId,
            $resource
        );
        
        // Update order
        update_order_status($pdo, $orderId, 'paid', 'completed');
        
        $pdo->commit();
        error_log('PayPal capture: Order ' . $orderId . ' marked as paid');
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Handle PAYMENT.CAPTURE.DENIED/DECLINED events
 */
function handleCaptureFailed(PDO $pdo, array $resource): void {
    $captureId = $resource['id'] ?? '';
    
    $orderId = 0;
    if (isset($resource['custom_id'])) {
        $orderId = (int) $resource['custom_id'];
    }
    
    error_log('PayPal capture failed: Capture ID = ' . $captureId . ', Order ID = ' . $orderId);
    
    if ($orderId <= 0) {
        return;
    }
    
    $order = get_order($pdo, $orderId);
    if (!$order) {
        return;
    }
    
    // Don't update if already in a final state
    if (in_array($order['payment_status'] ?? '', ['paid', 'completed'], true)) {
        return;
    }
    
    update_payment_by_order(
        $pdo,
        $orderId,
        'paypal',
        'failed',
        $captureId,
        $resource
    );
    
    update_order_status($pdo, $orderId, 'failed');
    
    error_log('PayPal capture failed: Order ' . $orderId . ' marked as failed');
}
