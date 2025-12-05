<?php
/**
 * Payment Initiation Endpoint
 * المسار: public/pay.php
 * 
 * POST endpoint that receives order_id and gateway,
 * creates a payment session, and redirects the user.
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
$redirectUrl = '';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed. Use POST.';
    exit;
}

// CSRF check
if (!csrf_check($_POST['csrf'] ?? '')) {
    $error = 'انتهت صلاحية النموذج. يرجى إعادة المحاولة.';
}

// Get parameters
$orderId = (int) ($_POST['order_id'] ?? 0);
$gateway = trim($_POST['gateway'] ?? '');

if (!$error && $orderId <= 0) {
    $error = 'رقم الطلب غير صالح.';
}

if (!$error && !in_array($gateway, ['stripe', 'paypal'], true)) {
    $error = 'بوابة دفع غير صالحة.';
}

// Fetch order from database
$order = null;
if (!$error) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $error = 'الطلب غير موجود.';
        } elseif ($order['payment_status'] === 'paid') {
            $error = 'هذا الطلب مدفوع مسبقاً.';
        }
    } catch (PDOException $e) {
        error_log('pay.php order fetch error: ' . $e->getMessage());
        $error = 'حدث خطأ في قاعدة البيانات.';
    }
}

// Create payment session based on gateway
if (!$error && $order) {
    if ($gateway === 'stripe') {
        $result = create_stripe_session($pdo, $order);
        
        if ($result['success'] && !empty($result['url'])) {
            $redirectUrl = $result['url'];
        } else {
            $error = 'فشل إنشاء جلسة Stripe: ' . ($result['error'] ?? 'خطأ غير معروف');
        }
    } elseif ($gateway === 'paypal') {
        $result = create_paypal_order($pdo, $order);
        
        if ($result['success'] && !empty($result['approval_url'])) {
            $redirectUrl = $result['approval_url'];
        } else {
            $error = 'فشل إنشاء طلب PayPal: ' . ($result['error'] ?? 'خطأ غير معروف');
        }
    }
}

// Redirect or show error
if (!$error && $redirectUrl) {
    header('Location: ' . $redirectUrl);
    exit;
}

// Show error page
$title = 'خطأ في الدفع';
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
                    <h4 class="mt-3">خطأ في الدفع</h4>
                    <p class="text-muted"><?= e($error) ?></p>
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
