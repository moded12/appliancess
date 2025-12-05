<?php
/**
 * PayPal Webhook Handler - معالجة PayPal Webhooks
 * 
 * يستقبل الأحداث من PayPal ويحدث حالة المدفوعات
 * 
 * المسار: public/paypal_webhook.php
 */

declare(strict_types=1);

// لا نبدأ جلسة لأن هذا endpoint للـ webhook
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

// قراءة الـ payload
$payload = file_get_contents('php://input');

// التحقق من وجود البيانات
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload received']);
    exit;
}

// جمع الـ headers
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$headerName] = $value;
    }
}

// التحقق من صحة الـ webhook (تحقق أساسي)
if (!verify_paypal_webhook($payload, $headers)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook']);
    exit;
}

// تحليل الحدث
$event = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($event['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$eventType = $event['event_type'];
$resource = $event['resource'] ?? [];

// تسجيل الحدث للتتبع
error_log("PayPal webhook received: " . $eventType);

// معالجة الأحداث المختلفة
switch ($eventType) {
    case 'CHECKOUT.ORDER.APPROVED':
        // تمت الموافقة على الطلب - ينتظر الالتقاط
        $paypalOrderId = $resource['id'] ?? '';
        $customId = null;
        
        foreach (($resource['purchase_units'] ?? []) as $unit) {
            if (!empty($unit['custom_id'])) {
                $customId = $unit['custom_id'];
                break;
            }
        }
        
        error_log("PayPal order approved: " . $paypalOrderId . ", custom_id: " . ($customId ?? 'N/A'));
        
        // تحديث سجل الدفع إذا وجد
        if ($paypalOrderId) {
            update_payment_by_transaction($pdo, $paypalOrderId, 'pending', $resource);
        }
        break;
        
    case 'PAYMENT.CAPTURE.COMPLETED':
        // تم التقاط الدفعة بنجاح
        $captureId = $resource['id'] ?? '';
        $paypalOrderId = null;
        $customId = null;
        $amount = (float)($resource['amount']['value'] ?? 0);
        $currency = $resource['amount']['currency_code'] ?? 'USD';
        
        // محاولة استخراج معلومات الطلب
        if (!empty($resource['supplementary_data']['related_ids']['order_id'])) {
            $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'];
        }
        if (!empty($resource['custom_id'])) {
            $customId = $resource['custom_id'];
        }
        
        error_log("PayPal capture completed: " . $captureId);
        
        // تحديث سجل الدفع
        if ($paypalOrderId) {
            update_payment_by_transaction($pdo, $paypalOrderId, 'completed', $resource);
        }
        
        // تحديث حالة الطلب
        if ($customId) {
            update_order_payment_status($pdo, (int)$customId, 'paid');
        }
        break;
        
    case 'PAYMENT.CAPTURE.DENIED':
    case 'PAYMENT.CAPTURE.DECLINED':
        // فشل التقاط الدفعة
        $captureId = $resource['id'] ?? '';
        $customId = $resource['custom_id'] ?? null;
        
        error_log("PayPal capture failed: " . $captureId);
        
        if ($customId) {
            update_order_payment_status($pdo, (int)$customId, 'failed');
        }
        break;
        
    case 'PAYMENT.CAPTURE.REFUNDED':
        // تم استرداد المبلغ
        $captureId = $resource['id'] ?? '';
        $customId = $resource['custom_id'] ?? null;
        $amount = (float)($resource['amount']['value'] ?? 0);
        $currency = $resource['amount']['currency_code'] ?? 'USD';
        
        error_log("PayPal refund: " . $captureId);
        
        if ($customId) {
            record_payment($pdo, (int)$customId, 'paypal', $captureId . '_refund', $amount, $currency, 'refunded', $resource);
        }
        break;
        
    case 'CHECKOUT.ORDER.COMPLETED':
        // اكتمل الطلب
        $paypalOrderId = $resource['id'] ?? '';
        $customId = null;
        
        foreach (($resource['purchase_units'] ?? []) as $unit) {
            if (!empty($unit['custom_id'])) {
                $customId = $unit['custom_id'];
                break;
            }
        }
        
        error_log("PayPal order completed: " . $paypalOrderId);
        
        if ($paypalOrderId) {
            update_payment_by_transaction($pdo, $paypalOrderId, 'completed', $resource);
        }
        if ($customId) {
            update_order_payment_status($pdo, (int)$customId, 'paid');
        }
        break;
        
    default:
        // حدث غير مُعالج - تسجيله فقط
        error_log("Unhandled PayPal event: " . $eventType);
        break;
}

// الرد بنجاح
http_response_code(200);
echo json_encode(['received' => true]);
