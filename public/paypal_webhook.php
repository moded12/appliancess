<?php
/**
 * public/paypal_webhook.php
 * معالجة webhooks من PayPal
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

// تعيين content type للاستجابة
header('Content-Type: application/json');

// قراءة البيانات الخام من الطلب
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (empty($data) || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$eventType = $data['event_type'] ?? '';
$resource = $data['resource'] ?? [];

// تسجيل الحدث للمراجعة
error_log('PayPal Webhook received: ' . $eventType);

try {
    switch ($eventType) {
        case 'CHECKOUT.ORDER.APPROVED':
            // تمت الموافقة على الطلب - ننتظر الـ capture
            $paypalOrderId = $resource['id'] ?? '';
            
            // البحث عن سجل الدفع
            if ($paypalOrderId) {
                $stmt = $pdo->prepare(
                    "SELECT id, order_id FROM payments WHERE gateway = 'paypal' AND transaction_id = :tid LIMIT 1"
                );
                $stmt->execute([':tid' => $paypalOrderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    // تحديث الحالة إلى processing (في انتظار الـ capture)
                    update_payment_status($pdo, (int)$row['id'], 'processing', null, $resource);
                }
            }
            break;

        case 'PAYMENT.CAPTURE.COMPLETED':
            // تم التقاط الدفعة بنجاح
            $captureId = $resource['id'] ?? '';
            $amount = (float)($resource['amount']['value'] ?? 0);
            $currency = $resource['amount']['currency_code'] ?? 'USD';
            $status = $resource['status'] ?? '';
            
            // جلب order_id من supplementary_data أو custom_id
            $customId = $resource['custom_id'] ?? '';
            $orderId = 0;
            
            // البحث عن سجل الدفع المرتبط
            if (!empty($resource['supplementary_data']['related_ids']['order_id'])) {
                $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'];
                $stmt = $pdo->prepare(
                    "SELECT order_id FROM payments WHERE gateway = 'paypal' AND transaction_id = :tid LIMIT 1"
                );
                $stmt->execute([':tid' => $paypalOrderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $orderId = (int)$row['order_id'];
                }
            }
            
            // محاولة بديلة: البحث عن أحدث سجل paypal معلق
            if ($orderId <= 0 && !empty($customId)) {
                // custom_id قد يحتوي على order_id
                if (preg_match('/order_(\d+)/', $customId, $matches)) {
                    $orderId = (int)$matches[1];
                }
            }
            
            if ($orderId > 0 && $status === 'COMPLETED') {
                record_payment(
                    $pdo,
                    $orderId,
                    'paypal',
                    $captureId,
                    $amount,
                    $currency,
                    'paid',
                    $resource
                );
                update_order_payment_status($pdo, $orderId, 'paid', 'processing');
            }
            break;

        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.DECLINED':
            // تم رفض الدفعة
            $captureId = $resource['id'] ?? '';
            
            // البحث عن سجل الدفع
            $stmt = $pdo->prepare(
                "SELECT order_id FROM payments WHERE gateway = 'paypal' AND (transaction_id = :tid1 OR transaction_id LIKE :tid2) LIMIT 1"
            );
            $stmt->execute([':tid1' => $captureId, ':tid2' => '%' . $captureId . '%']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $orderId = (int)$row['order_id'];
                record_payment(
                    $pdo,
                    $orderId,
                    'paypal',
                    $captureId,
                    0,
                    'USD',
                    'failed',
                    $resource
                );
                update_order_payment_status($pdo, $orderId, 'failed');
            }
            break;

        case 'PAYMENT.CAPTURE.REFUNDED':
            // تم استرداد الدفعة
            $captureId = $resource['id'] ?? '';
            
            $stmt = $pdo->prepare(
                "SELECT order_id FROM payments WHERE gateway = 'paypal' AND transaction_id = :tid LIMIT 1"
            );
            $stmt->execute([':tid' => $captureId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $orderId = (int)$row['order_id'];
                record_payment(
                    $pdo,
                    $orderId,
                    'paypal',
                    $captureId,
                    0,
                    'USD',
                    'refunded',
                    $resource
                );
                update_order_payment_status($pdo, $orderId, 'refunded');
            }
            break;

        default:
            // أحداث أخرى - نسجلها فقط للمراجعة
            error_log('PayPal webhook unhandled event: ' . $eventType);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'event' => $eventType]);

} catch (Throwable $e) {
    error_log('PayPal webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing error: ' . $e->getMessage()]);
}
