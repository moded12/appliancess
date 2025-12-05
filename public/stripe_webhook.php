<?php
/**
 * Stripe Webhook Handler - معالجة Stripe Webhooks
 * 
 * يستقبل الأحداث من Stripe ويحدث حالة المدفوعات
 * 
 * المسار: public/stripe_webhook.php
 */

declare(strict_types=1);

// لا نبدأ جلسة لأن هذا endpoint للـ webhook
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

// قراءة الـ payload
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// التحقق من وجود البيانات
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload received']);
    exit;
}

// التحقق من التوقيع
$webhookSecret = $config['stripe_webhook_secret'] ?? '';
if (!empty($webhookSecret)) {
    if (!verify_stripe_webhook_signature($payload, $sigHeader, $webhookSecret)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

// تحليل الحدث
$event = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$eventType = $event['type'];
$eventData = $event['data']['object'] ?? [];

// تسجيل الحدث للتتبع
error_log("Stripe webhook received: " . $eventType);

// معالجة الأحداث المختلفة
switch ($eventType) {
    case 'checkout.session.completed':
        // جلسة Checkout اكتملت بنجاح
        $sessionId = $eventData['id'] ?? '';
        $orderId = (int)($eventData['metadata']['order_id'] ?? 0);
        $paymentStatus = $eventData['payment_status'] ?? '';
        
        if ($paymentStatus === 'paid') {
            // تحديث سجل الدفع
            update_payment_by_transaction($pdo, $sessionId, 'completed', $eventData);
            
            // تحديث حالة الطلب
            if ($orderId > 0) {
                update_order_payment_status($pdo, $orderId, 'paid');
            }
            
            error_log("Stripe payment completed for order: " . $orderId);
        }
        break;
        
    case 'checkout.session.expired':
        // انتهت صلاحية الجلسة
        $sessionId = $eventData['id'] ?? '';
        update_payment_by_transaction($pdo, $sessionId, 'failed', $eventData);
        error_log("Stripe session expired: " . $sessionId);
        break;
        
    case 'payment_intent.succeeded':
        // نجح الدفع (يمكن استخدامه كبديل)
        $paymentIntentId = $eventData['id'] ?? '';
        $orderId = (int)($eventData['metadata']['order_id'] ?? 0);
        
        if ($orderId > 0) {
            update_order_payment_status($pdo, $orderId, 'paid');
        }
        
        error_log("Stripe payment_intent succeeded: " . $paymentIntentId);
        break;
        
    case 'payment_intent.payment_failed':
        // فشل الدفع
        $paymentIntentId = $eventData['id'] ?? '';
        $orderId = (int)($eventData['metadata']['order_id'] ?? 0);
        
        if ($orderId > 0) {
            update_order_payment_status($pdo, $orderId, 'failed');
        }
        
        error_log("Stripe payment_intent failed: " . $paymentIntentId);
        break;
        
    case 'charge.refunded':
        // تم استرداد المبلغ
        $chargeId = $eventData['id'] ?? '';
        $orderId = (int)($eventData['metadata']['order_id'] ?? 0);
        
        // تسجيل الاسترداد
        if ($orderId > 0) {
            $amount = ((float)($eventData['amount_refunded'] ?? 0)) / 100;
            $currency = $eventData['currency'] ?? 'usd';
            record_payment($pdo, $orderId, 'stripe', $chargeId . '_refund', $amount, $currency, 'refunded', $eventData);
        }
        
        error_log("Stripe charge refunded: " . $chargeId);
        break;
        
    default:
        // حدث غير مُعالج
        error_log("Unhandled Stripe event: " . $eventType);
        break;
}

// الرد بنجاح
http_response_code(200);
echo json_encode(['received' => true]);
