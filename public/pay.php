<?php
/**
 * public/pay.php
 * نقطة دخول لبدء عملية الدفع
 * يتلقى order_id و gateway ويعيد التوجيه إلى Stripe أو PayPal
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

// التحقق من المعاملات
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);
$gateway = isset($_GET['gateway']) ? trim($_GET['gateway']) : (isset($_POST['gateway']) ? trim($_POST['gateway']) : '');

if ($orderId <= 0) {
    exit_with_error('رقم الطلب غير صالح.');
}

if (!in_array($gateway, ['stripe', 'paypal'], true)) {
    exit_with_error('بوابة الدفع غير مدعومة. اختر stripe أو paypal.');
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

try {
    if ($gateway === 'stripe') {
        // التحقق من وجود مفتاح Stripe
        $stripeKey = $config['stripe_secret_key'] ?? '';
        if (empty($stripeKey)) {
            exit_with_error('Stripe غير مُهيأ. يرجى إعداد STRIPE_SECRET_KEY.');
        }

        $result = create_stripe_session($pdo, $order);
        
        // إعادة التوجيه إلى Stripe Checkout
        header('Location: ' . $result['checkout_url']);
        exit;

    } elseif ($gateway === 'paypal') {
        // التحقق من وجود بيانات PayPal
        $paypalClientId = $config['paypal_client_id'] ?? '';
        $paypalSecret = $config['paypal_secret'] ?? '';
        if (empty($paypalClientId) || empty($paypalSecret)) {
            exit_with_error('PayPal غير مُهيأ. يرجى إعداد PAYPAL_CLIENT_ID و PAYPAL_SECRET.');
        }

        $result = create_paypal_order($pdo, $order);
        
        // إعادة التوجيه إلى صفحة موافقة PayPal
        header('Location: ' . $result['approval_url']);
        exit;
    }
} catch (Throwable $e) {
    $debugMode = $config['debug'] ?? false;
    $errorMessage = 'حدث خطأ أثناء بدء عملية الدفع.';
    if ($debugMode) {
        $errorMessage .= ' التفاصيل: ' . $e->getMessage();
    }
    exit_with_error($errorMessage, 500);
}
