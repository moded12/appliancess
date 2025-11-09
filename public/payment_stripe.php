<?php include __DIR__.'/../includes/header.php'; $base=rtrim($config['base_url'],'/'); $order_id=(int)($_GET['order_id']??0); ?>
<div class="glass p-3">هذه صفحة تجريبية. لربط Stripe فعليًا: ثبّت stripe-php، أنشئ Checkout Session، استقبل Webhook لتأكيد الدفع، ثم حدّث payment_status.</div>
<div class="mt-3"><a class="btn btn-primary" href="<?= $base ?>/order_success.php?id=<?= $order_id ?>">محاكاة نجاح الدفع</a></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
