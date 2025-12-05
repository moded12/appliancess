<?php
/**
 * PayPal Return Handler
 * 
 * This file handles the return from PayPal after user approves the payment.
 * It captures the payment and updates the order status.
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
$success = false;
$orderId = (int)($_GET['order_id'] ?? 0);
$paypalToken = $_GET['token'] ?? '';

if ($orderId <= 0) {
    $error = 'رقم الطلب غير صالح.';
} elseif (empty($paypalToken)) {
    $error = 'رمز PayPal غير صالح.';
} else {
    // Get order
    $order = get_order($pdo, $orderId);
    
    if (!$order) {
        $error = 'الطلب غير موجود.';
    } elseif ($order['payment_status'] === 'paid') {
        // Already paid, redirect to success
        header('Location: order_view.php?id=' . $orderId . '&payment=success');
        exit;
    } else {
        // Capture the PayPal payment
        $captureResult = capture_paypal_payment($paypalToken);
        
        if ($captureResult['success']) {
            try {
                $pdo->beginTransaction();
                
                // Update payment record
                update_payment_by_order(
                    $pdo,
                    $orderId,
                    'paypal',
                    'completed',
                    $captureResult['capture_id'] ?? $paypalToken,
                    $captureResult['raw_response'] ?? null
                );
                
                // Update order status
                update_order_payment($pdo, $orderId, 'paid', 'completed');
                
                $pdo->commit();
                $success = true;
                
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'حدث خطأ أثناء تحديث الطلب: ' . $e->getMessage();
            }
        } else {
            // Update payment as failed
            update_payment_by_order(
                $pdo,
                $orderId,
                'paypal',
                'failed',
                null,
                $captureResult['raw_response'] ?? null
            );
            
            $error = $captureResult['error'] ?? 'فشل في إتمام الدفع.';
        }
    }
}

// Redirect on success
if ($success) {
    header('Location: order_view.php?id=' . $orderId . '&payment=success');
    exit;
}

// Show error page
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
            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
            <h1 class="h4 mt-3">فشل الدفع عبر PayPal</h1>
        </div>
        
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
        
        <div class="d-flex gap-2 justify-content-center">
            <?php if ($orderId > 0): ?>
                <a href="order_view.php?id=<?= (int)$orderId ?>" class="btn btn-primary rounded-pill">
                    <i class="bi bi-arrow-right"></i> العودة للطلب
                </a>
                <a href="checkout.php" class="btn btn-outline-primary rounded-pill">
                    <i class="bi bi-arrow-repeat"></i> إعادة المحاولة
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
