<?php
/**
 * المسار: public/paypal_return.php
 * 
 * صفحة العودة بعد موافقة المستخدم على الدفع عبر PayPal
 * يقوم بتنفيذ/استلام (capture) الطلب وتحديث قاعدة البيانات
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

$orderId = (int)($_GET['order_id'] ?? 0);
$paypalToken = $_GET['token'] ?? ''; // PayPal order ID

$success = false;
$error = '';
$paymentDetails = [];

if ($orderId <= 0) {
    $error = 'رقم الطلب غير صحيح.';
} else {
    // جلب بيانات الطلب
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $error = 'الطلب غير موجود.';
    } elseif ($order['payment_status'] === 'paid') {
        // الطلب مدفوع بالفعل
        $success = true;
        $paymentDetails = [
            'transaction_id' => $order['transaction_id'] ?? '',
            'amount' => number_format((float)$order['total'], 2),
            'currency' => 'USD',
        ];
    } else {
        // استخراج PayPal order ID من payment_meta
        $paymentMeta = [];
        if (!empty($order['payment_meta'])) {
            $decoded = json_decode($order['payment_meta'], true);
            if (is_array($decoded)) {
                $paymentMeta = $decoded;
            }
        }
        
        $paypalOrderId = $paymentMeta['paypal_order_id'] ?? $paypalToken;
        
        if (empty($paypalOrderId)) {
            $error = 'معرّف طلب PayPal غير موجود.';
        } else {
            // تنفيذ/استلام الطلب من PayPal
            $captureResult = capturePayPalOrder($paypalOrderId);
            
            if ($captureResult['success']) {
                $success = true;
                
                $transactionId = $captureResult['transaction_id'] ?? $paypalOrderId;
                
                // تحديث الطلب في قاعدة البيانات
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'paid',
                        transaction_id = :transaction_id,
                        status = CASE WHEN status = 'waiting_payment' THEN 'new' ELSE status END,
                        updated_at = NOW()
                    WHERE id = :order_id
                ");
                $stmt->execute([
                    ':transaction_id' => $transactionId,
                    ':order_id' => $orderId
                ]);
                
                // محاولة تسجيل في جدول payments
                try {
                    $existingPayment = getPaymentByOrderId($pdo, $orderId, 'paypal');
                    if (!$existingPayment) {
                        savePaymentRecord(
                            $pdo,
                            $orderId,
                            'paypal',
                            (float) ($captureResult['amount'] ?? $order['total']),
                            $captureResult['currency'] ?? 'USD',
                            'paid',
                            $transactionId,
                            $captureResult
                        );
                    }
                } catch (Throwable $e) {
                    // تجاهل إذا كان الجدول غير موجود
                }
                
                $paymentDetails = [
                    'transaction_id' => $transactionId,
                    'amount' => $captureResult['amount'] ?? number_format((float)$order['total'], 2),
                    'currency' => $captureResult['currency'] ?? 'USD',
                    'payer_email' => $captureResult['payer_email'] ?? '',
                ];
            } else {
                $error = 'تعذر إتمام الدفع: ' . e($captureResult['error'] ?? 'Unknown error');
                
                // تحديث حالة الطلب إلى فشل
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'failed',
                        updated_at = NOW()
                    WHERE id = :order_id
                ");
                $stmt->execute([':order_id' => $orderId]);
            }
        }
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
        .paypal-icon { color: #003087; }
    </style>
</head>
<body>
<div class="container">
    <div class="result-card">
        <?php if ($success): ?>
            <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
            <h1 class="h4 mb-3">تم الدفع بنجاح عبر <span class="paypal-icon"><i class="bi bi-paypal"></i> PayPal</span></h1>
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
                        <?php if (!empty($paymentDetails['payer_email'])): ?>
                            <li><strong>البريد:</strong> <?= e($paymentDetails['payer_email']) ?></li>
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
            <h1 class="h4 mb-3">حدث خطأ في الدفع</h1>
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
