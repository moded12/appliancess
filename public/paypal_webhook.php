<?php
/**
 * المسار: public/paypal_webhook.php
 * 
 * معالج PayPal Webhook
 * يستقبل إشعارات من PayPal ويحدث حالة الطلبات والمدفوعات
 * 
 * Endpoint URL للتهيئة في PayPal Dashboard:
 * https://yourdomain.com/public/paypal_webhook.php
 * 
 * الأحداث المدعومة:
 * - CHECKOUT.ORDER.APPROVED
 * - PAYMENT.CAPTURE.COMPLETED
 * - PAYMENT.CAPTURE.DENIED
 * - PAYMENT.CAPTURE.REFUNDED
 */

declare(strict_types=1);

// تعطيل عرض الأخطاء في الإنتاج
ini_set('display_errors', '0');
error_reporting(E_ALL);

// تسجيل الأخطاء (اختياري)
$logFile = __DIR__ . '/../logs/paypal_webhook.log';

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

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload']);
    exit;
}

// تحميل الاتصال والدوال
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

// جمع الـ headers للتحقق
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = str_replace('_', '-', substr($key, 5));
        $headers[$headerName] = $value;
    }
}

// التحقق من صحة الـ webhook
$webhookResult = verifyPayPalWebhook($payload, $headers);

if (!$webhookResult['valid']) {
    logWebhook('Webhook verification failed: ' . ($webhookResult['error'] ?? 'Unknown error'));
    // في بيئة sandbox قد نتخطى التحقق
    if (empty($webhookResult['warning'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook']);
        exit;
    }
}

$eventType = $webhookResult['event']['type'] ?? '';
$eventData = $webhookResult['event']['data'] ?? [];

logWebhook("Received event: $eventType");

try {
    switch ($eventType) {
        case 'CHECKOUT.ORDER.APPROVED':
            handleOrderApproved($pdo, $eventData);
            break;
            
        case 'PAYMENT.CAPTURE.COMPLETED':
            handleCaptureCompleted($pdo, $eventData);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
            handleCaptureDenied($pdo, $eventData);
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            handleCaptureRefunded($pdo, $eventData);
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
 * معالجة موافقة الطلب
 */
function handleOrderApproved(PDO $pdo, array $data): void {
    $paypalOrderId = $data['id'] ?? '';
    
    if (empty($paypalOrderId)) {
        logWebhook('CHECKOUT.ORDER.APPROVED: No order ID');
        return;
    }
    
    logWebhook("CHECKOUT.ORDER.APPROVED: $paypalOrderId");
    
    // البحث عن الطلب بمعرّف PayPal
    $stmt = $pdo->prepare("
        SELECT id FROM orders 
        WHERE payment_meta LIKE :pattern AND gateway = 'paypal'
        LIMIT 1
    ");
    $stmt->execute([':pattern' => '%"paypal_order_id":"' . $paypalOrderId . '"%']);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $orderId = (int) $order['id'];
        logWebhook("Found order #$orderId for PayPal order $paypalOrderId");
        // لا نحدث الحالة هنا - سننتظر capture
    } else {
        logWebhook("No order found for PayPal order $paypalOrderId");
    }
}

/**
 * معالجة اكتمال الاستلام
 */
function handleCaptureCompleted(PDO $pdo, array $capture): void {
    $captureId = $capture['id'] ?? '';
    $amount = $capture['amount']['value'] ?? '0.00';
    $currency = $capture['amount']['currency_code'] ?? 'USD';
    
    // استخراج reference_id من supplementary_data أو custom_id
    $orderId = 0;
    if (!empty($capture['supplementary_data']['related_ids']['order_id'])) {
        // البحث عن الطلب
        $paypalOrderId = $capture['supplementary_data']['related_ids']['order_id'];
        $stmt = $pdo->prepare("
            SELECT id FROM orders 
            WHERE payment_meta LIKE :pattern AND gateway = 'paypal'
            LIMIT 1
        ");
        $stmt->execute([':pattern' => '%"paypal_order_id":"' . $paypalOrderId . '"%']);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            $orderId = (int) $order['id'];
        }
    }
    
    // محاولة أخرى: البحث بمعرّف المعاملة
    if ($orderId === 0 && !empty($captureId)) {
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE transaction_id = :tid LIMIT 1");
        $stmt->execute([':tid' => $captureId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            $orderId = (int) $order['id'];
        }
    }
    
    logWebhook("PAYMENT.CAPTURE.COMPLETED: $captureId, amount: $amount $currency, order: $orderId");
    
    if ($orderId > 0) {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                transaction_id = :transaction_id,
                status = CASE WHEN status = 'waiting_payment' THEN 'new' ELSE status END,
                updated_at = NOW()
            WHERE id = :order_id
        ");
        $stmt->execute([
            ':transaction_id' => $captureId,
            ':order_id' => $orderId
        ]);
        
        // تسجيل في جدول payments
        try {
            savePaymentRecord(
                $pdo,
                $orderId,
                'paypal',
                (float) $amount,
                $currency,
                'paid',
                $captureId,
                $capture
            );
        } catch (Throwable $e) {
            logWebhook('Could not save to payments table: ' . $e->getMessage());
        }
        
        logWebhook("Order #$orderId marked as paid");
    }
}

/**
 * معالجة رفض الاستلام
 */
function handleCaptureDenied(PDO $pdo, array $capture): void {
    $captureId = $capture['id'] ?? '';
    
    logWebhook("PAYMENT.CAPTURE.DENIED: $captureId");
    
    // البحث عن الطلب بمعرّف المعاملة
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE transaction_id = :tid LIMIT 1");
    $stmt->execute([':tid' => $captureId]);
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
function handleCaptureRefunded(PDO $pdo, array $capture): void {
    $captureId = $capture['id'] ?? '';
    
    logWebhook("PAYMENT.CAPTURE.REFUNDED: $captureId");
    
    // البحث عن الطلب بمعرّف المعاملة
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE transaction_id = :tid LIMIT 1");
    $stmt->execute([':tid' => $captureId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $orderId = (int) $order['id'];
        
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
                WHERE order_id = :order_id AND gateway = 'paypal'
            ");
            $stmt->execute([':order_id' => $orderId]);
        } catch (Throwable $e) {
            logWebhook('Could not update payments table: ' . $e->getMessage());
        }
        
        logWebhook("Order #$orderId marked as refunded");
    }
}
