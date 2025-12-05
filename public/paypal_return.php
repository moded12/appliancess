<?php
/**
 * PayPal Return Handler - معالجة رجوع المستخدم من PayPal
 * 
 * يستقبل token من PayPal بعد موافقة المستخدم ويقوم بالتقاط الدفعة
 * 
 * المسار: public/paypal_return.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// دالة مساعدة للهروب من HTML
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// قراءة المعاملات من PayPal
$paypalOrderId = trim($_GET['token'] ?? ''); // PayPal يرسل token
$payerId = trim($_GET['PayerID'] ?? '');
$orderId = (int)($_GET['order_id'] ?? 0);

// التحقق من وجود token
if (empty($paypalOrderId)) {
    // ربما ألغى المستخدم
    $cancelled = isset($_GET['cancelled']) || empty($_GET['token']);
    if ($cancelled && $orderId > 0) {
        header('Location: order_view.php?id=' . $orderId . '&payment=cancelled');
        exit;
    }
    
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <title>خطأ في الدفع</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    </head>
    <body>
    <div class="container py-5">
        <div class="alert alert-warning">
            <h4>لم يتم استلام معلومات الدفع</h4>
            <p>يبدو أن عملية الدفع لم تكتمل أو تم إلغاؤها.</p>
            <a href="index.php" class="btn btn-primary">العودة للمتجر</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// التقاط الدفعة من PayPal
$captureResult = capture_paypal_order($pdo, $paypalOrderId);

if ($captureResult['success']) {
    // الدفع تم بنجاح
    $capturedOrderId = $captureResult['order_id'] ?? $orderId;
    
    // تحديث حالة الطلب
    if ($capturedOrderId) {
        update_order_payment_status($pdo, (int)$capturedOrderId, 'paid');
    }
    
    // إعادة التوجيه لصفحة الطلب مع رسالة نجاح
    $redirectUrl = 'order_view.php?id=' . ($capturedOrderId ?: $orderId) . '&payment=success';
    header('Location: ' . $redirectUrl);
    exit;
    
} else {
    // فشل في التقاط الدفعة
    error_log("PayPal capture failed: " . json_encode($captureResult));
    
    // تحديث حالة الدفع
    update_payment_by_transaction($pdo, $paypalOrderId, 'failed', $captureResult);
    
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <title>فشل الدفع</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    </head>
    <body>
    <div class="container py-5">
        <div class="alert alert-danger">
            <h4>فشل في إتمام الدفع</h4>
            <p><?= e($captureResult['error'] ?? 'حدث خطأ أثناء معالجة الدفع') ?></p>
            <div class="mt-3">
                <a href="checkout.php" class="btn btn-primary">إعادة المحاولة</a>
                <?php if ($orderId > 0): ?>
                    <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-secondary">عرض الطلب</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
