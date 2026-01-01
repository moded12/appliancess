<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;
if (!$order_id) { header('Location: ./checkout.php'); exit; }

$stmt = $pdo->prepare("SELECT id, total FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) { echo "Order not found."; exit; }

$currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>دفع الطلب #<?= htmlspecialchars($order['id']) ?></title>
<link rel="stylesheet" href="/xx/assets/css/payment.css"> <!-- عدّل المسار إذا يلزم -->
</head>
<body>
<div class="pay-card">
  <h2>دفع الطلب رقم <?= htmlspecialchars($order['id']) ?></h2>
  <p>المبلغ: <?= htmlspecialchars($order['total']) ?> <?= htmlspecialchars($currency) ?></p>
  <form method="post" action="pay.php">
    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
    <button class="btn" type="submit" name="gateway" value="stripe">دفع بالبطاقة (Stripe)</button>
    <button class="btn" type="submit" name="gateway" value="paypal">الدفع عبر PayPal</button>
  </form>
  <p><a href="checkout.php?order=<?= (int)$order['id'] ?>">العودة لصفحة الخروج</a></p>
</div>
</body>
</html>