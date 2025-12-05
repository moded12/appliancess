<?php
/**
 * Payment Entry Point
 * 
 * This file handles payment initiation for both Stripe and PayPal gateways.
 * It receives order_id and gateway via POST and redirects to the appropriate payment page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments.php';

$config = require __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Helper function
if (!function_exists('e')) {
    function e($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$error = '';
$orderId = null;
$gateway = null;

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (function_exists('csrf_check') && !csrf_check($_POST['csrf'] ?? '')) {
        $error = 'انتهت صلاحية النموذج. يرجى المحاولة مرة أخرى.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $gateway = $_POST['gateway'] ?? '';
        
        // Validate order_id
        if ($orderId <= 0) {
            $error = 'رقم الطلب غير صالح.';
        } else {
            // Get order from database
            $order = get_order($pdo, $orderId);
            
            if (!$order) {
                $error = 'الطلب غير موجود.';
            } elseif ($order['payment_status'] === 'paid') {
                $error = 'تم دفع هذا الطلب مسبقاً.';
            } else {
                // Process based on gateway
                switch ($gateway) {
                    case 'stripe':
                        $result = create_stripe_session($pdo, $order);
                        if ($result['success']) {
                            // Redirect to Stripe Checkout
                            header('Location: ' . $result['checkout_url']);
                            exit;
                        } else {
                            $error = $result['error'] ?? 'فشل في إنشاء جلسة الدفع عبر Stripe.';
                        }
                        break;
                        
                    case 'paypal':
                        $result = create_paypal_order($pdo, $order);
                        if ($result['success']) {
                            // Redirect to PayPal approval page
                            header('Location: ' . $result['approval_url']);
                            exit;
                        } else {
                            $error = $result['error'] ?? 'فشل في إنشاء طلب الدفع عبر PayPal.';
                        }
                        break;
                        
                    default:
                        $error = 'بوابة الدفع غير مدعومة: ' . e($gateway);
                }
            }
        }
    }
} else {
    $error = 'طريقة الطلب غير صالحة.';
}

// If we reach here, there was an error
$title = 'خطأ في الدفع';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?> | <?= e($config['app_name'] ?? 'المتجر') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .error-container { max-width: 600px; margin: 50px auto; }
    </style>
</head>
<body>
<div class="container error-container">
    <div class="card p-4">
        <div class="text-center mb-4">
            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
            <h1 class="h4 mt-3">خطأ في الدفع</h1>
        </div>
        
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
        
        <div class="d-flex gap-2 justify-content-center">
            <?php if ($orderId > 0): ?>
                <a href="order_view.php?id=<?= (int)$orderId ?>" class="btn btn-primary rounded-pill">
                    <i class="bi bi-arrow-right"></i> العودة للطلب
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-house"></i> الصفحة الرئيسية
            </a>
        </div>
    </div>
</div>
</body>
</html>
