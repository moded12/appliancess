<?php
/**
 * المسار: public/stripe_webhook.php
 * 
 * معالج Stripe Webhook
 * يستقبل إشعارات من Stripe ويحدث حالة الطلبات والمدفوعات
 * 
 * Endpoint URL للتهيئة في Stripe Dashboard:
 * https://yourdomain.com/public/stripe_webhook.php
 * 
 * الأحداث المدعومة:
 * - checkout.session.completed
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 */

declare(strict_types=1);

// تعطيل عرض الأخطاء في الإنتاج
ini_set('display_errors', '0');
error_reporting(E_ALL);

// تسجيل الأخطاء (اختياري)
$logFile = __DIR__ . '/../logs/stripe_webhook.log';

function logWebhook(string $message): void {
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// قراءة البيانات الخام
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload']);
    exit;
}

// تحميل الاتصال والدوال
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

// التحقق من التوقيع
$webhookResult = verifyStripeWebhook($payload, $sigHeader);

if (!$webhookResult['valid']) {
    logWebhook('Webhook verification failed: ' . ($webhookResult['error'] ?? 'Unknown error'));
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$eventType = $webhookResult['event']['type'] ?? '';
$eventData = $webhookResult['event']['data'] ?? [];

logWebhook("Received event: $eventType");

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
            
        case 'charge.refunded':
            handleChargeRefunded($pdo, $eventData);
            break;
            
        default:
            logWebhook("Unhandled event type: $eventType");
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Throwable $e) {
    logWebhook('Error processing webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing error']);
}

/**
 * معالجة اكتمال جلسة Checkout
 */
function handleCheckoutSessionCompleted(PDO $pdo, array $session): void {
    $orderId = (int) ($session['metadata']['order_id'] ?? $session['client_reference_id'] ?? 0);
    $paymentStatus = $session['payment_status'] ?? '';
    $paymentIntent = $session['payment_intent'] ?? '';
    
    if ($orderId <= 0) {
        logWebhook('checkout.session.completed: No order_id found');
        return;
    }
    
    logWebhook("checkout.session.completed: Order #$orderId, status: $paymentStatus");
    
    if ($paymentStatus === 'paid') {
        // تحديث حالة الطلب
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                transaction_id = :transaction_id,
                status = CASE WHEN status = 'waiting_payment' THEN 'new' ELSE status END,
                updated_at = NOW()
            WHERE id = :order_id
        ");
        $stmt->execute([
            ':transaction_id' => $paymentIntent,
            ':order_id' => $orderId
        ]);
        
        // تسجيل في جدول payments
        try {
            savePaymentRecord(
                $pdo,
                $orderId,
                'stripe',
                ($session['amount_total'] ?? 0) / 100, // تحويل من السنتات
                strtoupper($session['currency'] ?? 'USD'),
                'paid',
                $paymentIntent,
                $session
            );
        } catch (Throwable $e) {
            // تجاهل إذا كان الجدول غير موجود
            logWebhook('Could not save to payments table: ' . $e->getMessage());
        }
        
        logWebhook("Order #$orderId marked as paid");
    }
}

/**
 * معالجة نجاح الدفع
 */
function handlePaymentIntentSucceeded(PDO $pdo, array $intent): void {
    $paymentIntentId = $intent['id'] ?? '';
    $amount = ($intent['amount'] ?? 0) / 100;
    $currency = strtoupper($intent['currency'] ?? 'USD');
    
    logWebhook("payment_intent.succeeded: $paymentIntentId, amount: $amount $currency");
    
    // البحث عن الطلب بمعرّف المعاملة
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE transaction_id = :tid LIMIT 1");
    $stmt->execute([':tid' => $paymentIntentId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $orderId = (int) $order['id'];
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                status = CASE WHEN status = 'waiting_payment' THEN 'new' ELSE status END,
                updated_at = NOW()
            WHERE id = :order_id
        ");
        $stmt->execute([':order_id' => $orderId]);
        logWebhook("Order #$orderId updated to paid via payment_intent.succeeded");
    }
}

/**
 * معالجة فشل الدفع
 */
function handlePaymentIntentFailed(PDO $pdo, array $intent): void {
    $paymentIntentId = $intent['id'] ?? '';
    $errorMessage = $intent['last_payment_error']['message'] ?? 'Payment failed';
    
    logWebhook("payment_intent.payment_failed: $paymentIntentId - $errorMessage");
    
    // البحث عن الطلب بمعرّف المعاملة
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE transaction_id = :tid LIMIT 1");
    $stmt->execute([':tid' => $paymentIntentId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $orderId = (int) $order['id'];
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'failed',
                updated_at = NOW()
            WHERE id = :order_id
        ");
        $stmt->execute([':order_id' => $orderId]);
        logWebhook("Order #$orderId marked as failed");
    }
}

/**
 * معالجة استرداد المبلغ
 */
function handleChargeRefunded(PDO $pdo, array $charge): void {
    $chargeId = $charge['id'] ?? '';
    $paymentIntentId = $charge['payment_intent'] ?? '';
    $refundedAmount = ($charge['amount_refunded'] ?? 0) / 100;
    
    logWebhook("charge.refunded: $chargeId, refunded: $refundedAmount");
    
    // البحث عن الطلب
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE transaction_id = :tid LIMIT 1");
    $stmt->execute([':tid' => $paymentIntentId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $orderId = (int) $order['id'];
        
        // تحديث حالة الدفع إلى مسترد
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'refunded',
                updated_at = NOW()
            WHERE id = :order_id
        ");
        $stmt->execute([':order_id' => $orderId]);
        
        // تحديث جدول payments
        try {
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'refunded',
                    updated_at = NOW()
                WHERE order_id = :order_id AND gateway = 'stripe'
            ");
            $stmt->execute([':order_id' => $orderId]);
        } catch (Throwable $e) {
            logWebhook('Could not update payments table: ' . $e->getMessage());
        }
        
        logWebhook("Order #$orderId marked as refunded");
    }
}
