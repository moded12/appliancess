<?php
/**
 * Stripe Webhook Handler
 * 
 * This file handles webhook events from Stripe.
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
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

if (empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Stripe-Signature header']);
    exit;
}

// Verify webhook signature
$event = verify_stripe_webhook($payload, $sigHeader);

if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Handle the event
try {
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $orderId = (int)($session->client_reference_id ?? $session->metadata->order_id ?? 0);
            
            if ($orderId > 0) {
                $pdo->beginTransaction();
                
                // Update payment record
                $stmt = $pdo->prepare(
                    "UPDATE payments 
                     SET status = 'completed', 
                         transaction_id = :payment_intent,
                         raw_response = :raw_response,
                         updated_at = NOW()
                     WHERE order_id = :order_id AND gateway = 'stripe'"
                );
                $stmt->execute([
                    ':payment_intent' => $session->payment_intent ?? $session->id,
                    ':raw_response' => json_encode([
                        'session_id' => $session->id,
                        'payment_intent' => $session->payment_intent,
                        'payment_status' => $session->payment_status,
                        'amount_total' => $session->amount_total,
                    ]),
                    ':order_id' => $orderId,
                ]);
                
                // Update order status
                update_order_payment($pdo, $orderId, 'paid', 'completed');
                
                $pdo->commit();
            }
            break;
            
        case 'checkout.session.expired':
            $session = $event->data->object;
            $orderId = (int)($session->client_reference_id ?? $session->metadata->order_id ?? 0);
            
            if ($orderId > 0) {
                // Update payment as cancelled
                update_payment_by_order($pdo, $orderId, 'stripe', 'cancelled', null, [
                    'event' => 'checkout.session.expired',
                    'session_id' => $session->id,
                ]);
            }
            break;
            
        case 'payment_intent.succeeded':
            // Payment intent succeeded - this is typically handled by checkout.session.completed
            // but we log it for completeness
            $paymentIntent = $event->data->object;
            // Optional: additional handling
            break;
            
        case 'payment_intent.payment_failed':
            $paymentIntent = $event->data->object;
            $orderId = (int)($paymentIntent->metadata->order_id ?? 0);
            
            if ($orderId > 0) {
                update_payment_by_order($pdo, $orderId, 'stripe', 'failed', $paymentIntent->id, [
                    'event' => 'payment_intent.payment_failed',
                    'last_error' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
                ]);
                
                update_order_payment($pdo, $orderId, 'failed');
            }
            break;
            
        case 'charge.refunded':
            $charge = $event->data->object;
            $orderId = (int)($charge->metadata->order_id ?? 0);
            
            if ($orderId > 0) {
                update_payment_by_order($pdo, $orderId, 'stripe', 'refunded', null, [
                    'event' => 'charge.refunded',
                    'charge_id' => $charge->id,
                    'amount_refunded' => $charge->amount_refunded,
                ]);
            }
            break;
            
        default:
            // Unhandled event type - log for debugging if needed
            break;
    }
    
    // Acknowledge receipt
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Internal error: ' . $e->getMessage()]);
}
