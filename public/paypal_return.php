<?php
/**
 * PayPal Return Handler
 * المسار: public/paypal_return.php
 * 
 * Handles the return from PayPal after user approval.
 * Captures the payment and updates order/payment status.
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
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$error = '';
$success = false;
$orderId = (int) ($_GET['order_id'] ?? 0);
$paypalToken = trim($_GET['token'] ?? ''); // PayPal adds this parameter

if ($orderId <= 0) {
    $error = 'عذراً، حدث خطأ في معالجة الطلب.';
}

// Fetch order
$order = null;
if (!$error) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $error = 'عذراً، حدث خطأ في معالجة الطلب.';
        } elseif ($order['payment_status'] === 'paid') {
            // Already paid, redirect to success
            header('Location: order_view.php?id=' . $orderId . '&payment=already_paid');
            exit;
        }
    } catch (PDOException $e) {
        error_log('paypal_return.php order fetch error: ' . $e->getMessage());
        $error = 'حدث خطأ في قاعدة البيانات.';
    }
}

// Find the pending PayPal payment record
$payment = null;
if (!$error) {
    $payment = get_payment_by_order($pdo, $orderId, 'paypal');
    if (!$payment) {
        $error = 'لم يتم العثور على سجل دفع PayPal لهذا الطلب.';
    } elseif ($payment['status'] === 'completed') {
        // Already completed
        header('Location: order_view.php?id=' . $orderId . '&payment=already_completed');
        exit;
    }
}

// Capture the PayPal order
if (!$error && $payment) {
    $paypalOrderId = $payment['transaction_id']; // This is the PayPal Order ID
    
    $captureResult = capture_paypal_order($paypalOrderId);
    
    if ($captureResult['success']) {
        $captureId = $captureResult['capture_id'] ?? $paypalOrderId;
        $paypalStatus = $captureResult['status'] ?? 'UNKNOWN';
        
        if ($paypalStatus === 'COMPLETED') {
            // Update payment record
            update_payment_status(
                $pdo,
                (int) $payment['id'],
                'completed',
                $captureId,
                json_encode($captureResult['response'] ?? [])
            );
            
            // Update order status
            update_order_payment_status($pdo, $orderId, 'paid', 'processing');
            
            $success = true;
        } else {
            $error = 'حالة الدفع غير متوقعة: ' . $paypalStatus;
            
            // Update payment to reflect the actual status
            update_payment_status(
                $pdo,
                (int) $payment['id'],
                'pending',
                $captureId,
                json_encode($captureResult['response'] ?? [])
            );
        }
    } else {
        $error = 'فشل التقاط الدفع: ' . ($captureResult['error'] ?? 'خطأ غير معروف');
    }
}

// Redirect to thank you page or show error
if ($success) {
    header('Location: order_view.php?id=' . $orderId . '&payment=success');
    exit;
}

// Show error page
$title = 'خطأ في إتمام الدفع';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">خطأ في إتمام الدفع عبر PayPal</h4>
                    <p class="text-muted"><?= e($error) ?></p>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        لم يتم خصم أي مبلغ من حسابك. يمكنك إعادة المحاولة.
                    </div>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <a href="checkout.php" class="btn btn-primary rounded-pill">
                            <i class="bi bi-arrow-right"></i> العودة للسلة
                        </a>
                        <?php if ($orderId > 0): ?>
                        <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-outline-secondary rounded-pill">
                            <i class="bi bi-eye"></i> عرض الطلب
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
