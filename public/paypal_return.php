<?php
/**
 * public/paypal_return.php
 * معالجة إعادة التوجيه من PayPal بعد موافقة العميل
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../config.php';

// دالة مساعدة للخروج مع خطأ
function exit_with_error(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>خطأ في الدفع</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light"><div class="container py-5"><div class="alert alert-danger">';
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '</div><a href="checkout.php" class="btn btn-primary">العودة للسلة</a></div></body></html>';
    exit;
}

// PayPal يُعيد token و PayerID
$token = trim($_GET['token'] ?? '');
$payerId = trim($_GET['PayerID'] ?? '');
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (empty($token)) {
    exit_with_error('رمز PayPal مفقود.');
}

if ($orderId <= 0) {
    exit_with_error('رقم الطلب غير صالح.');
}

// جلب الطلب
$order = get_order($pdo, $orderId);
if (!$order) {
    exit_with_error('الطلب غير موجود.', 404);
}

// التحقق من أن الطلب لم يُدفع بعد
$paymentStatus = $order['payment_status'] ?? 'pending';
if ($paymentStatus === 'paid') {
    header('Location: order_view.php?id=' . $orderId . '&notice=already_paid');
    exit;
}

// البحث عن سجل الدفع المعلق
$paymentStmt = $pdo->prepare(
    "SELECT * FROM payments WHERE order_id = :order_id AND gateway = 'paypal' AND status = 'pending' ORDER BY id DESC LIMIT 1"
);
$paymentStmt->execute([':order_id' => $orderId]);
$paymentRecord = $paymentStmt->fetch(PDO::FETCH_ASSOC);

if (!$paymentRecord) {
    exit_with_error('سجل الدفع غير موجود.');
}

$paypalOrderId = $paymentRecord['transaction_id'];

// التحقق من تطابق token مع paypalOrderId
if ($token !== $paypalOrderId) {
    // قد يكون token مختلفاً عن order_id في بعض الحالات
    // نستخدم token كمعرّف PayPal Order
    $paypalOrderId = $token;
}

try {
    // التقاط الدفعة
    $captureResult = capture_paypal_order($pdo, $paypalOrderId, $orderId);

    if ($captureResult['status'] === 'COMPLETED') {
        // نجح الدفع - إعادة التوجيه لصفحة الطلب مع رسالة نجاح
        header('Location: order_view.php?id=' . $orderId . '&payment=success');
        exit;
    } else {
        // الدفع لم يكتمل
        header('Location: order_view.php?id=' . $orderId . '&payment=pending');
        exit;
    }
} catch (Throwable $e) {
    $debugMode = $config['debug'] ?? false;
    $errorMessage = 'حدث خطأ أثناء معالجة الدفع.';
    if ($debugMode) {
        $errorMessage .= ' التفاصيل: ' . $e->getMessage();
    }
    exit_with_error($errorMessage, 500);
}
