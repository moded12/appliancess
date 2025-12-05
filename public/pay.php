<?php
/**
 * Payment Initiation Handler - بدء عملية الدفع
 * 
 * يستقبل order_id و gateway ويوجه المستخدم إلى بوابة الدفع المناسبة
 * 
 * المسار: public/pay.php
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

// قراءة المعاملات
$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$gateway = trim($_GET['gateway'] ?? $_POST['gateway'] ?? '');

// التحقق من صحة المعاملات
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

if (!in_array($gateway, ['stripe', 'paypal'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment gateway. Use "stripe" or "paypal"']);
    exit;
}

// جلب معلومات الطلب
$order = get_order_by_id($pdo, $orderId);
if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// التحقق من حالة الطلب
$paymentStatus = $order['payment_status'] ?? 'pending';
if ($paymentStatus === 'paid') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order already paid']);
    exit;
}

$amount = (float)($order['total'] ?? 0);
$currency = $config['default_currency'] ?? 'USD';

// روابط العودة
$baseUrl = site_url('/public');
$successUrl = $baseUrl . '/order_view.php?id=' . $orderId . '&payment=success';
$cancelUrl = $baseUrl . '/order_view.php?id=' . $orderId . '&payment=cancelled';

if ($gateway === 'stripe') {
    // إنشاء جلسة Stripe
    $result = create_stripe_session(
        $pdo,
        $orderId,
        $amount,
        $currency,
        $successUrl,
        $cancelUrl
    );
    
    if ($result['success'] && !empty($result['url'])) {
        // إعادة التوجيه إلى Stripe Checkout
        header('Location: ' . $result['url']);
        exit;
    } else {
        // عرض خطأ
        http_response_code(500);
        $error = $result['error'] ?? 'Failed to create Stripe session';
        
        // إذا كان الطلب AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        
        // عرض صفحة خطأ
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
            <div class="alert alert-danger">
                <h4>فشل في بدء عملية الدفع</h4>
                <p><?= e($error) ?></p>
                <a href="checkout.php" class="btn btn-primary">العودة للدفع</a>
                <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-secondary">عرض الطلب</a>
            </div>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
    
} elseif ($gateway === 'paypal') {
    // إنشاء طلب PayPal
    $returnUrl = $baseUrl . '/paypal_return.php?order_id=' . $orderId;
    
    $result = create_paypal_order(
        $pdo,
        $orderId,
        $amount,
        $currency,
        $returnUrl,
        $cancelUrl
    );
    
    if ($result['success'] && !empty($result['approval_url'])) {
        // إعادة التوجيه إلى PayPal
        header('Location: ' . $result['approval_url']);
        exit;
    } else {
        // عرض خطأ
        http_response_code(500);
        $error = $result['error'] ?? 'Failed to create PayPal order';
        
        // إذا كان الطلب AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        
        // عرض صفحة خطأ
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
            <div class="alert alert-danger">
                <h4>فشل في بدء عملية الدفع عبر PayPal</h4>
                <p><?= e($error) ?></p>
                <a href="checkout.php" class="btn btn-primary">العودة للدفع</a>
                <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-secondary">عرض الطلب</a>
            </div>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}
