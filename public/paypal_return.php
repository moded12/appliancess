<?php
/**
 * PayPal Return Handler
 * Handles the return from PayPal after user approval
 * Captures the payment and updates order/payment status
 * 
 * GET Parameters:
 * - token: PayPal order ID (returned by PayPal)
 * - order_id: Our order ID
 * - PayerID: PayPal payer ID (optional)
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
    function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

$error = '';
$success = false;
$orderId = (int) ($_GET['order_id'] ?? 0);
$paypalToken = trim($_GET['token'] ?? '');

// Validate inputs
if ($orderId <= 0) {
    $error = 'رقم الطلب غير صالح.';
} elseif (empty($paypalToken)) {
    $error = 'رمز PayPal غير موجود.';
} else {
    // Verify order exists
    $order = get_order($pdo, $orderId);
    
    if (!$order) {
        $error = 'الطلب غير موجود.';
    } elseif (in_array($order['payment_status'] ?? '', ['paid', 'completed'], true)) {
        // Already paid - redirect to success
        header('Location: order_view.php?id=' . $orderId . '&payment=already_paid');
        exit;
    } else {
        // Capture the PayPal order
        $captureResult = capture_paypal_order($paypalToken);
        
        if ($captureResult['success']) {
            $captureId = $captureResult['capture_id'] ?? $paypalToken;
            
            try {
                $pdo->beginTransaction();
                
                // Update payment record
                update_payment_by_order(
                    $pdo,
                    $orderId,
                    'paypal',
                    'completed',
                    $captureId,
                    $captureResult['raw_response'] ?? null
                );
                
                // Update order status
                update_order_status($pdo, $orderId, 'paid', 'completed');
                
                $pdo->commit();
                $success = true;
                
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('PayPal return processing error: ' . $e->getMessage());
                $error = 'حدث خطأ أثناء تحديث الطلب.';
            }
        } else {
            $error = 'فشل في تأكيد الدفع: ' . ($captureResult['error'] ?? 'خطأ غير معروف');
            
            // Record the failure
            update_payment_by_order(
                $pdo,
                $orderId,
                'paypal',
                'failed',
                null,
                $captureResult['raw_response'] ?? ['error' => $captureResult['error'] ?? 'Unknown']
            );
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
</head>
<body>
<div class="container py-5" style="max-width:600px;">
    <div class="text-center mb-4">
        <i class="bi bi-paypal text-primary" style="font-size:3rem;"></i>
    </div>
    
    <div class="alert alert-danger">
        <h5 class="alert-heading"><i class="bi bi-x-circle"></i> فشل الدفع عبر PayPal</h5>
        <p class="mb-0"><?= e($error ?: 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.') ?></p>
    </div>
    
    <div class="d-flex gap-2 justify-content-center">
        <?php if ($orderId > 0): ?>
            <a href="checkout.php?retry=1&order_id=<?= $orderId ?>" class="btn btn-primary rounded-pill">
                <i class="bi bi-arrow-repeat"></i> المحاولة مرة أخرى
            </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-shop"></i> الصفحة الرئيسية
        </a>
    </div>
</div>
</body>
</html>
