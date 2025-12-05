<?php
/**
 * Stripe Webhook Handler
 * Handles Stripe webhook events for payment confirmation
 * 
 * Verifies the webhook signature and updates order/payment status
 * based on the event type.
 * 
 * Supported events:
 * - checkout.session.completed
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
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
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Validate inputs
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload']);
    exit;
}

if (empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Stripe signature']);
    exit;
}

$webhookSecret = $config['stripe_webhook_secret'] ?? '';
if (empty($webhookSecret)) {
    http_response_code(500);
    echo json_encode(['error' => 'Webhook secret not configured']);
    exit;
}

// Verify the webhook signature
$verifyResult = verify_stripe_webhook($payload, $sigHeader, $webhookSecret);

if (!$verifyResult['success']) {
    http_response_code(400);
    echo json_encode(['error' => $verifyResult['error']]);
    exit;
}

$event = $verifyResult['event'];

// Handle based on SDK vs array format
$eventType = is_array($event) ? ($event['type'] ?? '') : ($event->type ?? '');
$eventData = is_array($event) ? ($event['data']['object'] ?? []) : ($event->data->object ?? null);

// Log the event
error_log('Stripe webhook received: ' . $eventType);

try {
    switch ($eventType) {
        case 'checkout.session.completed':
            handleCheckoutSessionCompleted($pdo, $eventData);
            break;
            
        case 'payment_intent.succeeded':
            handlePaymentIntentSucceeded($pdo, $eventData);
            break;
            
        case 'payment_intent.payment_failed':
            handlePaymentIntentFailed($pdo, $eventData);
            break;
            
        default:
            // Unhandled event type - acknowledge receipt
            error_log('Unhandled Stripe event type: ' . $eventType);
    }
    
    // Acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Throwable $e) {
    error_log('Stripe webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle checkout.session.completed event
 */
function handleCheckoutSessionCompleted(PDO $pdo, $session): void {
    // Get order ID from client_reference_id or metadata
    $orderId = 0;
    $sessionId = '';
    
    if (is_array($session)) {
        $orderId = (int) ($session['client_reference_id'] ?? $session['metadata']['order_id'] ?? 0);
        $sessionId = $session['id'] ?? '';
    } else {
        $orderId = (int) ($session->client_reference_id ?? $session->metadata->order_id ?? 0);
        $sessionId = $session->id ?? '';
    }
    
    if ($orderId <= 0) {
        error_log('Stripe checkout.session.completed: No order ID found');
        return;
    }
    
    // Verify order exists
    $order = get_order($pdo, $orderId);
    if (!$order) {
        error_log('Stripe checkout.session.completed: Order not found: ' . $orderId);
        return;
    }
    
    // Check if already paid
    if (in_array($order['payment_status'] ?? '', ['paid', 'completed'], true)) {
        error_log('Stripe checkout.session.completed: Order already paid: ' . $orderId);
        return;
    }
    
    // Get payment intent ID for transaction reference
    $paymentIntent = '';
    if (is_array($session)) {
        $paymentIntent = $session['payment_intent'] ?? '';
    } else {
        $paymentIntent = $session->payment_intent ?? '';
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update payment record
        update_payment_by_order(
            $pdo,
            $orderId,
            'stripe',
            'completed',
            $paymentIntent ?: $sessionId,
            is_array($session) ? $session : json_decode(json_encode($session), true)
        );
        
        // Update order status
        update_order_status($pdo, $orderId, 'paid', 'completed');
        
        $pdo->commit();
        error_log('Stripe checkout.session.completed: Order ' . $orderId . ' marked as paid');
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Handle payment_intent.succeeded event
 */
function handlePaymentIntentSucceeded(PDO $pdo, $paymentIntent): void {
    // Try to get order ID from metadata
    $orderId = 0;
    $intentId = '';
    
    if (is_array($paymentIntent)) {
        $orderId = (int) ($paymentIntent['metadata']['order_id'] ?? 0);
        $intentId = $paymentIntent['id'] ?? '';
    } else {
        $orderId = (int) ($paymentIntent->metadata->order_id ?? 0);
        $intentId = $paymentIntent->id ?? '';
    }
    
    if ($orderId <= 0) {
        // Try to find by transaction_id (payment intent ID might be stored)
        error_log('Stripe payment_intent.succeeded: No order ID in metadata');
        return;
    }
    
    $order = get_order($pdo, $orderId);
    if (!$order) {
        return;
    }
    
    if (in_array($order['payment_status'] ?? '', ['paid', 'completed'], true)) {
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        update_payment_by_order(
            $pdo,
            $orderId,
            'stripe',
            'completed',
            $intentId,
            is_array($paymentIntent) ? $paymentIntent : json_decode(json_encode($paymentIntent), true)
        );
        
        update_order_status($pdo, $orderId, 'paid', 'completed');
        
        $pdo->commit();
        error_log('Stripe payment_intent.succeeded: Order ' . $orderId . ' marked as paid');
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Handle payment_intent.payment_failed event
 */
function handlePaymentIntentFailed(PDO $pdo, $paymentIntent): void {
    $orderId = 0;
    $intentId = '';
    
    if (is_array($paymentIntent)) {
        $orderId = (int) ($paymentIntent['metadata']['order_id'] ?? 0);
        $intentId = $paymentIntent['id'] ?? '';
    } else {
        $orderId = (int) ($paymentIntent->metadata->order_id ?? 0);
        $intentId = $paymentIntent->id ?? '';
    }
    
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
        'stripe',
        'failed',
        $intentId,
        is_array($paymentIntent) ? $paymentIntent : json_decode(json_encode($paymentIntent), true)
    );
    
    update_order_status($pdo, $orderId, 'failed');
    
    error_log('Stripe payment_intent.payment_failed: Order ' . $orderId . ' marked as failed');
}
