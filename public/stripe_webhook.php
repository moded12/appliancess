<?php
/**
 * Stripe Webhook Handler
 * المسار: public/stripe_webhook.php
 * 
 * Receives and processes Stripe webhook events.
 * Verifies signature using STRIPE_WEBHOOK_SECRET.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';
$config = require __DIR__ . '/../config.php';

// Get raw POST body
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpointSecret = $config['stripe_webhook_secret'] ?? '';

// Log incoming webhook
error_log('Stripe webhook received: ' . substr($payload, 0, 200));

// Verify signature
if (empty($endpointSecret)) {
    http_response_code(500);
    echo json_encode(['error' => 'Webhook secret not configured']);
    error_log('Stripe webhook error: STRIPE_WEBHOOK_SECRET not configured');
    exit;
}

if (empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Stripe-Signature header']);
    exit;
}

$event = verify_stripe_webhook($payload, $sigHeader, $endpointSecret);

if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    error_log('Stripe webhook error: Invalid signature');
    exit;
}

// Process the event
$eventType = $event['type'] ?? '';
$eventData = $event['data']['object'] ?? [];

error_log('Stripe webhook event type: ' . $eventType);

try {
    switch ($eventType) {
        case 'checkout.session.completed':
            handleCheckoutSessionCompleted($pdo, $eventData, $payload);
            break;
            
        case 'checkout.session.expired':
            handleCheckoutSessionExpired($pdo, $eventData, $payload);
            break;
            
        case 'payment_intent.succeeded':
            // Optional: handle payment_intent events
            error_log('Stripe payment_intent.succeeded: ' . ($eventData['id'] ?? 'unknown'));
            break;
            
        case 'payment_intent.payment_failed':
            handlePaymentFailed($pdo, $eventData, $payload);
            break;
            
        default:
            // Log unhandled event types
            error_log('Stripe webhook: Unhandled event type ' . $eventType);
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log('Stripe webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing error']);
}

/**
 * Handle checkout.session.completed event
 */
function handleCheckoutSessionCompleted(PDO $pdo, array $session, string $rawPayload): void
{
    $sessionId = $session['id'] ?? '';
    $paymentStatus = $session['payment_status'] ?? '';
    $orderId = (int) ($session['metadata']['order_id'] ?? 0);
    
    error_log("Stripe checkout.session.completed: session=$sessionId, order=$orderId, status=$paymentStatus");
    
    if (!$orderId) {
        // Try to find by session ID in payments table
        $stmt = $pdo->prepare("SELECT order_id FROM payments WHERE transaction_id = :sid AND gateway = 'stripe' LIMIT 1");
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $orderId = (int) $row['order_id'];
        }
    }
    
    if (!$orderId) {
        error_log('Stripe webhook: Cannot find order_id for session ' . $sessionId);
        return;
    }
    
    // Find payment record
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND gateway = 'stripe' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment && $paymentStatus === 'paid') {
        // Update payment record
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'completed',
            $sessionId,
            $rawPayload
        );
        
        // Update order status
        update_order_payment_status($pdo, $orderId, 'paid', 'processing');
        
        error_log("Stripe webhook: Order $orderId marked as paid");
    }
}

/**
 * Handle checkout.session.expired event
 */
function handleCheckoutSessionExpired(PDO $pdo, array $session, string $rawPayload): void
{
    $sessionId = $session['id'] ?? '';
    $orderId = (int) ($session['metadata']['order_id'] ?? 0);
    
    if (!$orderId) {
        $stmt = $pdo->prepare("SELECT order_id FROM payments WHERE transaction_id = :sid AND gateway = 'stripe' LIMIT 1");
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $orderId = (int) $row['order_id'];
        }
    }
    
    if (!$orderId) {
        return;
    }
    
    // Find and update payment record
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND gateway = 'stripe' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'cancelled',
            $sessionId,
            $rawPayload
        );
        
        error_log("Stripe webhook: Payment for order $orderId marked as cancelled (session expired)");
    }
}

/**
 * Handle payment_intent.payment_failed event
 */
function handlePaymentFailed(PDO $pdo, array $paymentIntent, string $rawPayload): void
{
    $intentId = $paymentIntent['id'] ?? '';
    $orderId = (int) ($paymentIntent['metadata']['order_id'] ?? 0);
    
    error_log("Stripe payment_intent.payment_failed: intent=$intentId, order=$orderId");
    
    if (!$orderId) {
        return;
    }
    
    // Find and update payment record
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid AND gateway = 'stripe' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        update_payment_status(
            $pdo,
            (int) $payment['id'],
            'failed',
            $intentId,
            $rawPayload
        );
        
        // Update order payment status
        update_order_payment_status($pdo, $orderId, 'failed', 'new');
        
        error_log("Stripe webhook: Payment for order $orderId marked as failed");
    }
}
