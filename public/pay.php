<?php
/**
 * Payment Entry Point
 * Starts payment process for an order based on selected gateway
 * 
 * POST Parameters:
 * - order_id: The order ID to pay for
 * - gateway: Payment gateway (stripe, paypal)
 * - csrf: CSRF token
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
$orderId = 0;
$gateway = '';

// Handle auto redirect from checkout (GET request with session data)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['auto']) && isset($_GET['order_id'])) {
    $pendingPayment = $_SESSION['pending_payment'] ?? null;
    $autoGateway = trim($_GET['auto']);
    $autoOrderId = (int) $_GET['order_id'];
    
    if ($pendingPayment && 
        (int)$pendingPayment['order_id'] === $autoOrderId && 
        $pendingPayment['gateway'] === $autoGateway) {
        // Convert to POST-like processing
        $_POST['order_id'] = $autoOrderId;
        $_POST['gateway'] = $autoGateway;
        $_POST['csrf'] = $pendingPayment['csrf'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SESSION['pending_payment']);
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'انتهت صلاحية النموذج. يرجى المحاولة مرة أخرى.';
    } else {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $gateway = trim($_POST['gateway'] ?? '');
        
        // Validate order ID
        if ($orderId <= 0) {
            $error = 'رقم الطلب غير صالح.';
        } elseif (!in_array($gateway, ['stripe', 'paypal'], true)) {
            $error = 'بوابة الدفع غير مدعومة.';
        } else {
            // Verify order exists and is payable
            $order = get_order($pdo, $orderId);
            
            if (!$order) {
                $error = 'الطلب غير موجود.';
            } elseif (in_array($order['payment_status'] ?? '', ['paid', 'completed'], true)) {
                $error = 'هذا الطلب مدفوع مسبقاً.';
            } else {
                // Process based on gateway
                if ($gateway === 'stripe') {
                    $result = create_stripe_session($pdo, $order);
                    
                    if ($result['success']) {
                        // Update order payment method
                        $pdo->prepare("UPDATE orders SET payment_method = 'stripe', gateway = 'stripe' WHERE id = :id")
                            ->execute([':id' => $orderId]);
                        
                        // Redirect to Stripe Checkout
                        header('Location: ' . $result['checkout_url']);
                        exit;
                    } else {
                        $error = 'فشل في إنشاء جلسة الدفع: ' . ($result['error'] ?? 'خطأ غير معروف');
                    }
                } elseif ($gateway === 'paypal') {
                    $result = create_paypal_order($pdo, $order);
                    
                    if ($result['success']) {
                        // Update order payment method
                        $pdo->prepare("UPDATE orders SET payment_method = 'paypal', gateway = 'paypal' WHERE id = :id")
                            ->execute([':id' => $orderId]);
                        
                        // Redirect to PayPal approval
                        header('Location: ' . $result['approval_url']);
                        exit;
                    } else {
                        $error = 'فشل في إنشاء طلب PayPal: ' . ($result['error'] ?? 'خطأ غير معروف');
                    }
                }
            }
        }
    }
}

// If we reach here, there was an error
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
        <i class="bi bi-exclamation-triangle text-warning" style="font-size:4rem;"></i>
    </div>
    
    <div class="alert alert-danger">
        <h5 class="alert-heading"><i class="bi bi-x-circle"></i> خطأ في الدفع</h5>
        <p class="mb-0"><?= e($error ?: 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.') ?></p>
    </div>
    
    <div class="d-flex gap-2 justify-content-center">
        <a href="checkout.php" class="btn btn-primary rounded-pill">
            <i class="bi bi-arrow-right"></i> العودة للدفع
        </a>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-shop"></i> الصفحة الرئيسية
        </a>
    </div>
</div>
</body>
</html>
