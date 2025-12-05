<?php
/**
 * Payment Redirect Helper
 * 
 * This file handles the redirect from checkout to the payment gateway.
 * It automatically submits the form to pay.php with the order details.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check for pending payment
$payment = $_SESSION['pending_payment'] ?? null;

if (!$payment || empty($payment['order_id']) || empty($payment['gateway'])) {
    header('Location: checkout.php');
    exit;
}

$orderId = (int)$payment['order_id'];
$gateway = $payment['gateway'];
$csrf = $payment['csrf'] ?? '';

// Clear the pending payment
unset($_SESSION['pending_payment']);

// Helper function
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$config = require __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>جارٍ التحويل للدفع... | <?= e($config['app_name'] ?? 'المتجر') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f5f7fa; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .loading-container { text-align: center; }
        .spinner-border { width: 3rem; height: 3rem; }
    </style>
</head>
<body>
<div class="loading-container">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h2 class="h5">جارٍ التحويل إلى بوابة الدفع...</h2>
    <p class="text-muted">يرجى الانتظار، لا تغلق هذه الصفحة.</p>
    
    <!-- Auto-submit form to pay.php -->
    <form id="paymentForm" method="POST" action="pay.php" style="display: none;">
        <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
        <input type="hidden" name="gateway" value="<?= e($gateway) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    </form>
</div>

<script>
    // Auto-submit the form
    document.getElementById('paymentForm').submit();
</script>
</body>
</html>
