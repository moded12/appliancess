<?php
// المسار: admin/order_view.php
// عرض تفاصيل طلب للإدارة مع معالجة أخطاء لطيفة لتجنب 500
// يعمل حتى لو لم تكن أعمدة الدفع موجودة في جدول orders (تُعرض عند توفرها)

declare(strict_types=1);

// جلسة + تفويض
if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.use_strict_mode', '1');
  session_start();
}
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

// اتصالات أساسية
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';
$config = require __DIR__ . '/../config.php';
$base   = rtrim($config['base_url'] ?? '', '/');

// دوال مساعدة
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('asset_url')) {
  function asset_url(string $path): string {
    global $base;
    $path = trim($path);
    if ($path==='') return '';
    if (preg_match('~^https?://~i',$path)) return $path;
    if (str_starts_with($path,'/uploads/')) return $base . '/public' . $path;
    if (str_starts_with($path,'/public/'))  return $base . $path;
    if ($path[0]==='/') return $base . $path;
    return $base . '/' . ltrim($path,'/');
  }
}
if (!function_exists('price')) {
  function price(float $v): string {
    global $config;
    $baseCurrency = $config['currency_code'] ?? 'JOD';
    $selected = $_SESSION['currency'] ?? $baseCurrency;
    $rates   = $config['currency_rates']   ?? [$baseCurrency=>1];
    $symbols = $config['currency_symbols'] ?? [$baseCurrency=>($config['currency_symbol'] ?? 'د.أ')];
    $rate = $rates[$selected] ?? 1;
    $converted = $v * $rate;
    $amount = number_format($converted, 2);
    $symbol = $symbols[$selected] ?? $selected;
    $pos = $config['currency_position'] ?? 'after';
    return $pos==='after' ? "$amount $symbol" : "$symbol $amount";
  }
}

// قراءة معرّف الطلب
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
  http_response_code(404);
  echo 'رقم طلب غير صالح.';
  exit;
}

// جلب بيانات الطلب + العميل
$order = null;
try {
  // لا نذكر أعمدة دفع بالاسم لضمان العمل حتى لو غير موجودة
  $sql = "SELECT o.*,
                 c.name   AS customer_name,
                 c.phone  AS customer_phone,
                 c.address AS customer_address
          FROM orders o
          LEFT JOIN customers c ON c.id = o.customer_id
          WHERE o.id = :id
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':id'=>$orderId]);
  $order = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $order = null;
}
if (!$order) {
  http_response_code(404);
  echo 'الطلب غير موجود.';
  exit;
}

// جلب بنود الطلب + أسماء/صور المنتجات
$items = [];
try {
  $sti = $pdo->prepare("
    SELECT oi.*, p.name AS product_name, p.main_image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :oid
    ORDER BY oi.id
  ");
  $sti->execute([':oid'=>$orderId]);
  $items = $sti->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $items = [];
}

// حساب إجمالي محسوب للتحقق
$computedTotal = 0.0;
foreach ($items as $it) {
  $computedTotal += ((float)($it['price'] ?? 0)) * ((int)($it['qty'] ?? 0));
}
$storedTotal = (float)($order['total'] ?? 0);
$totalMismatchNote = (abs($computedTotal - $storedTotal) > 0.01)
  ? "تنبيه: الإجمالي المخزن (".price($storedTotal).") يختلف عن المحسوب (".price($computedTotal).")."
  : '';

// محاولة قراءة حقول الدفع إن وُجدت دون افتراضها
$payment_method  = $order['payment_method']  ?? null;
$payment_status  = $order['payment_status']  ?? null;
$gateway         = $order['gateway']         ?? null;
$transaction_id  = $order['transaction_id']  ?? null;

// payment_meta قد لا يوجد أو ليس JSON
$payment_meta = [];
if (!empty($order['payment_meta'])) {
  $decoded = json_decode((string)$order['payment_meta'], true);
  if (is_array($decoded)) { $payment_meta = $decoded; }
}

// آخر طلبات للعميل (اختياري)
$recentOrders = [];
if (!empty($order['customer_id'])) {
  try {
    $rs = $pdo->prepare("SELECT id,total,status,created_at FROM orders
                         WHERE customer_id=:cid AND id<>:cur ORDER BY id DESC LIMIT 5");
    $rs->execute([':cid'=>$order['customer_id'], ':cur'=>$orderId]);
    $recentOrders = $rs->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { /* تجاهل */ }
}

// جلب سجلات المدفوعات للطلب
$paymentRecords = get_payments_by_order($pdo, $orderId);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>لوحة الإدارة - الطلب #<?= (int)$orderId ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#f6f7fb;}
    .wrap{max-width:1100px;margin:24px auto;}
    .card-soft{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 2px 6px rgba(15,23,42,.06);padding:1rem 1.2rem;}
    .payment-badge{display:inline-block;padding:.35rem .65rem;font-size:.68rem;font-weight:700;border-radius:999px;}
    .pay-pending{background:#fff3cd;color:#8a6d00;}
    .pay-paid{background:#d1fae5;color:#065f46;}
    .pay-failed{background:#fee2e2;color:#991b1b;}
    .pay-await{background:#e0f2fe;color:#075985;}
    .table-items th,.table-items td{vertical-align:middle;font-size:.8rem;}
  </style>
</head>
<body>
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 m-0">تفاصيل الطلب #<?= (int)$orderId ?></h1>
    <div class="d-flex gap-2">
      <a href="orders.php" class="btn btn-secondary btn-sm rounded-pill"><i class="bi bi-list"></i> كل الطلبات</a>
      <a href="<?= $base ?>/public/order_view.php?id=<?= (int)$orderId ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-box-arrow-up-right"></i> عرض كعميل</a>
    </div>
  </div>

  <!-- بيانات عامة -->
  <div class="card-soft mb-3">
    <div class="row g-3">
      <div class="col-md-6">
        <h2 class="h6 fw-bold mb-2">العميل</h2>
        <div class="small">
          <div><strong>الاسم:</strong> <?= e($order['customer_name'] ?? '—') ?></div>
          <div><strong>الهاتف:</strong> <?= e($order['customer_phone'] ?? '—') ?></div>
          <div><strong>العنوان:</strong> <?= nl2br(e($order['customer_address'] ?? '—')) ?></div>
        </div>
      </div>
      <div class="col-md-6">
        <h2 class="h6 fw-bold mb-2">الحالة والدفع</h2>
        <div class="small mb-1"><strong>حالة الطلب:</strong> <?= e($order['status'] ?? '—') ?></div>
        <div class="small mb-1"><strong>التاريخ:</strong> <?= e($order['created_at'] ?? '') ?></div>
        <?php
          $ps = (string)($payment_status ?? 'pending');
          $cls = 'pay-pending';
          if ($ps==='paid') $cls='pay-paid';
          elseif ($ps==='failed') $cls='pay-failed';
          elseif ($ps==='awaiting_transfer' || $ps==='initiated') $cls='pay-await';
        ?>
        <div class="small mb-1"><strong>طريقة الدفع:</strong> <?= e($payment_method ?? '—') ?></div>
        <div class="small mb-1">
          <strong>حالة الدفع:</strong>
          <span class="payment-badge <?= $cls ?>"><?= e($ps) ?></span>
        </div>
        <?php if (!empty($transaction_id)): ?>
          <div class="small mb-1"><strong>رقم المعاملة:</strong> <?= e($transaction_id) ?></div>
        <?php endif; ?>
        <?php if (!empty($gateway)): ?>
          <div class="small mb-1"><strong>البوابة:</strong> <?= e($gateway) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($totalMismatchNote): ?>
      <div class="alert alert-warning mt-3 py-2 px-3 small mb-0"><?= e($totalMismatchNote) ?></div>
    <?php endif; ?>
  </div>

  <!-- بنود الطلب -->
  <div class="card-soft mb-3">
    <h2 class="h6 fw-bold mb-3">بنود الطلب</h2>
    <?php if (!$items): ?>
      <div class="text-muted small">لا توجد بنود.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle table-items">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>المنتج</th>
              <th>السعر</th>
              <th>الكمية</th>
              <th>المجموع</th>
            </tr>
          </thead>
          <tbody>
          <?php $n=1; foreach ($items as $it):
            $line = ((float)($it['price'] ?? 0))*((int)($it['qty'] ?? 0));
            $img  = !empty($it['main_image']) ? asset_url($it['main_image']) : 'https://via.placeholder.com/60x60?text=No+Img';
          ?>
            <tr>
              <td><?= $n++ ?></td>
              <td style="min-width:220px;">
                <div class="d-flex align-items-center gap-2">
                  <img src="<?= e($img) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;" alt="">
                  <div>
                    <div class="fw-semibold small">
                      <a href="<?= $base ?>/public/product.php?id=<?= (int)$it['product_id'] ?>" class="text-decoration-none" target="_blank">
                        <?= e($it['product_name'] ?? ('#'.(int)$it['product_id'])) ?>
                      </a>
                    </div>
                    <div class="text-muted xsmall" style="font-size:.7rem;">ID: <?= (int)$it['product_id'] ?></div>
                  </div>
                </div>
              </td>
              <td><?= price((float)($it['price'] ?? 0)) ?></td>
              <td><?= (int)($it['qty'] ?? 0) ?></td>
              <td><?= price($line) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <th colspan="4" class="text-end">الإجمالي المحسوب</th>
              <th><?= price($computedTotal) ?></th>
            </tr>
            <tr>
              <th colspan="4" class="text-end">الإجمالي المسجل</th>
              <th><?= price($storedTotal) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- تفاصيل الدفع الإضافية -->
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card-soft h-100">
        <h2 class="h6 fw-bold mb-3">تفاصيل الدفع الإضافية</h2>
        <?php if (!$payment_meta): ?>
          <div class="text-muted small">لا توجد بيانات إضافية.</div>
        <?php else: ?>
          <ul class="small mb-0">
            <?php foreach ($payment_meta as $k=>$v): ?>
              <li><strong><?= e($k) ?>:</strong> <?= e(is_scalar($v)? $v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card-soft h-100">
        <h2 class="h6 fw-bold mb-3">طلبات أخرى لنفس العميل</h2>
        <?php if (!$recentOrders): ?>
          <div class="text-muted small">لا توجد طلبات أخرى.</div>
        <?php else: ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($recentOrders as $ro): ?>
              <li class="mb-1">
                <a href="order_view.php?id=<?= (int)$ro['id'] ?>" class="text-decoration-none">
                  #<?= (int)$ro['id'] ?> — <?= price((float)$ro['total']) ?> — <?= e($ro['status'] ?? '') ?> — <?= e($ro['created_at'] ?? '') ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- سجلات المدفوعات -->
  <div class="card-soft mt-3">
    <h2 class="h6 fw-bold mb-3">سجلات المدفوعات (Payments)</h2>
    <?php if (empty($paymentRecords)): ?>
      <div class="text-muted small">لا توجد سجلات مدفوعات لهذا الطلب.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>البوابة</th>
              <th>رقم المعاملة</th>
              <th>المبلغ</th>
              <th>الحالة</th>
              <th>التاريخ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paymentRecords as $pr): 
              $prStatus = $pr['status'] ?? 'pending';
              $statusClass = 'pay-pending';
              if ($prStatus === 'completed') $statusClass = 'pay-paid';
              elseif ($prStatus === 'failed') $statusClass = 'pay-failed';
              elseif ($prStatus === 'refunded') $statusClass = 'pay-await';
            ?>
              <tr>
                <td><?= (int)$pr['id'] ?></td>
                <td><span class="badge bg-secondary"><?= e($pr['gateway'] ?? '—') ?></span></td>
                <td class="small"><?= e($pr['transaction_id'] ?? '—') ?></td>
                <td><?= number_format((float)($pr['amount'] ?? 0), 2) ?> <?= e($pr['currency'] ?? 'USD') ?></td>
                <td><span class="payment-badge <?= $statusClass ?>"><?= e($prStatus) ?></span></td>
                <td class="small"><?= e($pr['created_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="mt-3 d-flex gap-2">
    <a href="orders.php" class="btn btn-secondary rounded-pill"><i class="bi bi-arrow-right"></i> رجوع</a>
    <button class="btn btn-outline-primary rounded-pill" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
  </div>
</div>

</body>
</html>