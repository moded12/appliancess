<?php
/**
 * public/stripe_webhook.php
 * معالجة webhooks من Stripe
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

// تعيين content type للاستجابة
header('Content-Type: application/json');

// قراءة البيانات الخام من الطلب
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// التحقق من التوقيع
$webhookSecret = $config['stripe_webhook_secret'] ?? '';

if (empty($webhookSecret)) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook secret not configured']);
    exit;
}

if (empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Stripe signature']);
    exit;
}

// التحقق من وجود Stripe SDK
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe SDK not installed']);
    exit;
}
require_once $autoloadPath;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// معالجة أنواع الأحداث المختلفة
$eventType = $event->type;
$eventData = $event->data->object;

try {
    switch ($eventType) {
        case 'checkout.session.completed':
            // جلسة الدفع اكتملت
            $sessionId = $eventData->id;
            $orderId = (int)($eventData->client_reference_id ?? 0);
            $paymentStatus = $eventData->payment_status ?? '';
            
            if ($orderId <= 0) {
                // محاولة جلب order_id من metadata
                $orderId = (int)($eventData->metadata->order_id ?? 0);
            }
            
            if ($orderId > 0 && $paymentStatus === 'paid') {
                // تحديث سجل الدفع
                $paymentIntentId = $eventData->payment_intent ?? $sessionId;
                $amount = ($eventData->amount_total ?? 0) / 100; // تحويل من سنتات
                $currency = strtoupper($eventData->currency ?? 'USD');
                
                record_payment(
                    $pdo,
                    $orderId,
                    'stripe',
                    $paymentIntentId,
                    (float)$amount,
                    $currency,
                    'paid',
                    (array)$eventData
                );
                
                // تحديث حالة الطلب
                update_order_payment_status($pdo, $orderId, 'paid', 'processing');
            }
            break;

        case 'payment_intent.succeeded':
            // الدفع نجح
            $paymentIntentId = $eventData->id;
            $amount = ($eventData->amount ?? 0) / 100;
            $currency = strtoupper($eventData->currency ?? 'USD');
            
            // البحث عن سجل الدفع بناءً على payment_intent أو session_id
            $stmt = $pdo->prepare(
                "SELECT order_id FROM payments WHERE gateway = 'stripe' AND (transaction_id = :tid1 OR transaction_id LIKE :tid2) LIMIT 1"
            );
            $stmt->execute([':tid1' => $paymentIntentId, ':tid2' => '%' . $paymentIntentId . '%']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $orderId = (int)$row['order_id'];
                record_payment(
                    $pdo,
                    $orderId,
                    'stripe',
                    $paymentIntentId,
                    (float)$amount,
                    $currency,
                    'paid',
                    (array)$eventData
                );
                update_order_payment_status($pdo, $orderId, 'paid', 'processing');
            }
            break;

        case 'payment_intent.payment_failed':
            // الدفع فشل
            $paymentIntentId = $eventData->id;
            
            // البحث عن سجل الدفع
            $stmt = $pdo->prepare(
                "SELECT order_id FROM payments WHERE gateway = 'stripe' AND transaction_id = :tid LIMIT 1"
            );
            $stmt->execute([':tid' => $paymentIntentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $orderId = (int)$row['order_id'];
                record_payment(
                    $pdo,
                    $orderId,
                    'stripe',
                    $paymentIntentId,
                    0,
                    'USD',
                    'failed',
                    (array)$eventData
                );
                update_order_payment_status($pdo, $orderId, 'failed');
            }
            break;

        case 'charge.refunded':
            // تم الاسترداد
            $chargeId = $eventData->id;
            $paymentIntentId = $eventData->payment_intent ?? '';
            
            if ($paymentIntentId) {
                $stmt = $pdo->prepare(
                    "SELECT order_id FROM payments WHERE gateway = 'stripe' AND transaction_id = :tid LIMIT 1"
                );
                $stmt->execute([':tid' => $paymentIntentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    $orderId = (int)$row['order_id'];
                    record_payment(
                        $pdo,
                        $orderId,
                        'stripe',
                        $paymentIntentId,
                        0,
                        'USD',
                        'refunded',
                        (array)$eventData
                    );
                    update_order_payment_status($pdo, $orderId, 'refunded');
                }
            }
            break;

        default:
            // أحداث أخرى - نسجلها فقط للمراجعة
            error_log('Stripe webhook unhandled event: ' . $eventType);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'event' => $eventType]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Processing error: ' . $e->getMessage()]);
}
