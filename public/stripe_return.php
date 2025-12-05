<?php
/**
 * المسار: public/stripe_return.php
 * 
 * صفحة العودة بعد إتمام الدفع عبر Stripe Checkout
 * يتحقق من حالة الجلسة ويعرض رسالة النجاح أو الفشل
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments.php';
$config = require __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$sessionId = $_GET['session_id'] ?? '';
$orderId = (int)($_GET['order_id'] ?? 0);

$success = false;
$error = '';
$paymentDetails = [];

if (empty($sessionId)) {
    $error = 'معرّف الجلسة غير موجود.';
} else {
    // استرجاع بيانات الجلسة من Stripe
    $sessionResult = retrieveStripeSession($sessionId);
    
    if ($sessionResult['success']) {
        $session = $sessionResult['session'];
        $paymentStatus = $session['payment_status'] ?? '';
        
        // استخراج order_id من metadata أو من URL
        if ($orderId === 0 && !empty($session['metadata']['order_id'])) {
            $orderId = (int) $session['metadata']['order_id'];
        }
        
        if ($paymentStatus === 'paid') {
            $success = true;
            
            // تحديث الطلب في قاعدة البيانات
            if ($orderId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'paid',
                        transaction_id = :transaction_id,
                        status = CASE WHEN status = 'waiting_payment' THEN 'new' ELSE status END,
                        updated_at = NOW()
                    WHERE id = :order_id AND payment_status != 'paid'
                ");
                $stmt->execute([
                    ':transaction_id' => $session['payment_intent'] ?? $sessionId,
                    ':order_id' => $orderId
                ]);
                
                // محاولة تسجيل في جدول payments
                try {
                    $existingPayment = getPaymentByOrderId($pdo, $orderId, 'stripe');
                    if (!$existingPayment) {
                        savePaymentRecord(
                            $pdo,
                            $orderId,
                            'stripe',
                            ($session['amount_total'] ?? 0) / 100,
                            strtoupper($session['currency'] ?? 'USD'),
                            'paid',
                            $session['payment_intent'] ?? $sessionId,
                            $session
                        );
                    }
                } catch (Throwable $e) {
                    // تجاهل إذا كان الجدول غير موجود
                }
            }
            
            $paymentDetails = [
                'transaction_id' => $session['payment_intent'] ?? $sessionId,
                'amount' => number_format(($session['amount_total'] ?? 0) / 100, 2),
                'currency' => strtoupper($session['currency'] ?? 'USD'),
                'email' => $session['customer_email'] ?? '',
            ];
        } else {
            $error = 'لم يتم إتمام الدفع. حالة الدفع: ' . e($paymentStatus);
        }
    } else {
        $error = 'تعذر التحقق من حالة الدفع: ' . e($sessionResult['error'] ?? 'Unknown error');
    }
}

$title = $success ? 'تم الدفع بنجاح' : 'خطأ في الدفع';
$baseUrl = rtrim($config['base_url'] ?? '', '/');
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
        .result-card {
            max-width: 500px;
            margin: 60px auto;
            padding: 2rem;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .success-icon { color: #22c55e; font-size: 4rem; }
        .error-icon { color: #ef4444; font-size: 4rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="result-card">
        <?php if ($success): ?>
            <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
            <h1 class="h4 mb-3">تم الدفع بنجاح!</h1>
            <p class="text-muted mb-4">شكراً لك. تم استلام دفعتك بنجاح.</p>
            
            <?php if ($orderId > 0): ?>
                <div class="bg-light rounded p-3 mb-4">
                    <div class="small text-muted">رقم الطلب</div>
                    <div class="h5 mb-0">#<?= $orderId ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($paymentDetails)): ?>
                <div class="text-start mb-4">
                    <h6 class="fw-semibold mb-2">تفاصيل الدفع:</h6>
                    <ul class="list-unstyled small">
                        <li><strong>المبلغ:</strong> <?= e($paymentDetails['amount']) ?> <?= e($paymentDetails['currency']) ?></li>
                        <li><strong>رقم المعاملة:</strong> <code class="small"><?= e($paymentDetails['transaction_id']) ?></code></li>
                        <?php if (!empty($paymentDetails['email'])): ?>
                            <li><strong>البريد:</strong> <?= e($paymentDetails['email']) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2 justify-content-center">
                <?php if ($orderId > 0): ?>
                    <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-primary rounded-pill">
                        <i class="bi bi-receipt"></i> عرض الطلب
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill">
                    <i class="bi bi-shop"></i> متابعة التسوق
                </a>
            </div>
            
        <?php else: ?>
            <div class="error-icon mb-3"><i class="bi bi-x-circle-fill"></i></div>
            <h1 class="h4 mb-3">حدث خطأ</h1>
            <p class="text-muted mb-4"><?= e($error) ?></p>
            
            <div class="d-flex gap-2 justify-content-center">
                <a href="checkout.php" class="btn btn-primary rounded-pill">
                    <i class="bi bi-arrow-repeat"></i> المحاولة مرة أخرى
                </a>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill">
                    <i class="bi bi-shop"></i> العودة للمتجر
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
