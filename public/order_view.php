<?php
// المسار: public/order_view.php
// عرض تفاصيل طلب مع معالجة أخطاء وحالات الدفع (COD / CliQ / Card)
// احفظ UTF-8 بدون BOM

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../config.php';
$base   = rtrim($config['base_url'] ?? '', '/');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('asset_url')) {
  function asset_url(string $path): string {
    global $base;
    $path = trim($path);
    if ($path==='') return '';
    if (preg_match('~^https?://~i',$path)) return $path;
    if (str_starts_with($path,'/uploads/')) return $base.'/public'.$path;
    if (str_starts_with($path,'/public/')) return $base.$path;
    if ($path[0]==='/') return $base.$path;
    return $base.'/'.ltrim($path,'/');
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
    $amount = number_format($converted,2);
    $symbol = $symbols[$selected] ?? $selected;
    $pos = $config['currency_position'] ?? 'after';
    return $pos==='after' ? "$amount $symbol" : "$symbol $amount";
  }
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
  http_response_code(404);
  echo 'رقم طلب غير صالح.';
  exit;
}

/* ------------- جلب الطلب ------------- */
$order = null;
try {
  $sql = "SELECT o.*,
                 c.name AS customer_name,
                 c.phone AS customer_phone,
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

/* ------------- جلب البنود ------------- */
$items = [];
try {
  // نضيف صورة المنتج لعرض مصغّر
  $stItems = $pdo->prepare("
      SELECT oi.*, p.name AS product_name, p.main_image
      FROM order_items oi
      LEFT JOIN products p ON p.id = oi.product_id
      WHERE oi.order_id = :oid
      ORDER BY oi.id
  ");
  $stItems->execute([':oid'=>$orderId]);
  $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $items = [];
}

/* ------------- حساب الإجمالي (تحقق) ------------- */
$computedTotal = 0.0;
foreach ($items as $it) {
  $computedTotal += ((float)$it['price']) * ((int)$it['qty']);
}
$storedTotal = (float)($order['total'] ?? 0);
if (abs($computedTotal - $storedTotal) > 0.01) {
  // ممكن تحديث أو فقط عرض ملاحظة
  $totalMismatchNote = "تنبيه: الإجمالي المخزن (".price($storedTotal).") يختلف عن المحسوب (".price($computedTotal).").";
} else {
  $totalMismatchNote = '';
}

/* ------------- تحليل ميتا الدفع (CliQ / Card) ------------- */
$paymentMetaRaw = $order['payment_meta'] ?? '';
$paymentMeta = [];
if ($paymentMetaRaw) {
  $decoded = json_decode($paymentMetaRaw, true);
  if (is_array($decoded)) $paymentMeta = $decoded;
}

/* ------------- آخر طلبات للعميل (اختياري) ------------- */
$recentOrders = [];
if (!empty($order['customer_id'])) {
  try {
    $rs = $pdo->prepare("SELECT id,total,status,payment_status,created_at FROM orders
                         WHERE customer_id=:cid AND id<>:current
                         ORDER BY id DESC LIMIT 5");
    $rs->execute([':cid'=>$order['customer_id'], ':current'=>$orderId]);
    $recentOrders = $rs->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {}
}

/* ------------- تهيئة العرض ------------- */
$title = 'تفاصيل الطلب #'.$orderId;
include __DIR__ . '/partials/shop_header.php';
?>

<style>
.order-wrapper{max-width:1100px;margin:0 auto;}
.order-header-card{background:var(--bg-alt);border:1px solid var(--border);border-radius:14px;padding:1rem 1.2rem;box-shadow:var(--shadow-sm);}
.payment-badge{display:inline-block;padding:.35rem .65rem;font-size:.65rem;font-weight:700;border-radius:50px;}
.pay-pending{background:#fff3cd;color:#8a6d00;}
.pay-paid{background:#d1fae5;color:#065f46;}
.pay-failed{background:#fee2e2;color:#991b1b;}
.pay-await{background:#e0f2fe;color:#075985;}
.table-items th, .table-items td{vertical-align:middle;font-size:.74rem;}
.meta-box{background:var(--bg-alt);border:1px solid var(--border);border-radius:12px;padding:.9rem 1rem;box-shadow:var(--shadow-sm);}
</style>

<div class="order-wrapper mb-5">

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h5 m-0">الطلب #<?= (int)$orderId ?></h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-right"></i> المتجر</a>
  </div>

  <div class="order-header-card mb-4">
    <div class="row g-3">
      <div class="col-md-6">
        <h2 class="h6 fw-bold mb-2">بيانات العميل</h2>
        <div class="small">
          <div><strong>الاسم:</strong> <?= e($order['customer_name'] ?? '—') ?></div>
          <div><strong>الهاتف:</strong> <?= e($order['customer_phone'] ?? '—') ?></div>
          <div><strong>العنوان:</strong> <?= nl2br(e($order['customer_address'] ?? '—')) ?></div>
        </div>
      </div>
      <div class="col-md-6">
        <h2 class="h6 fw-bold mb-2">بيانات الدفع والحالة</h2>
        <div class="small mb-1">
          <strong>طريقة الدفع:</strong>
          <?= e(strtoupper($order['payment_method'] ?: '—')) ?>
        </div>
        <div class="small mb-1">
          <strong>حالة الدفع:</strong>
          <?php
            $ps = $order['payment_status'] ?? 'pending';
            $cls = 'pay-pending';
            if ($ps==='paid') $cls='pay-paid';
            elseif ($ps==='failed') $cls='pay-failed';
            elseif ($ps==='awaiting_transfer' || $ps==='initiated') $cls='pay-await';
          ?>
          <span class="payment-badge <?= $cls ?>"><?= e($ps) ?></span>
        </div>
        <div class="small mb-1"><strong>حالة الطلب:</strong> <?= e($order['status'] ?? '—') ?></div>
        <?php if (!empty($order['transaction_id'])): ?>
          <div class="small mb-1"><strong>المعاملة:</strong> <?= e($order['transaction_id']) ?></div>
        <?php endif; ?>
        <div class="small mb-1">
          <strong>التاريخ:</strong>
          <?= e($order['created_at'] ?? '') ?>
        </div>
        <?php if ($totalMismatchNote): ?>
          <div class="alert alert-warning py-1 px-2 mt-2 small mb-0"><?= e($totalMismatchNote) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- بنود الطلب -->
  <div class="meta-box mb-4">
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
          <?php
            $n=1;
            foreach ($items as $it):
              $line = (float)$it['price'] * (int)$it['qty'];
              $img  = $it['main_image'] ? asset_url($it['main_image']) : 'https://via.placeholder.com/60x60?text=No+Img';
          ?>
            <tr>
              <td><?= $n++ ?></td>
              <td style="min-width:200px;">
                <div class="d-flex align-items-center gap-2">
                  <img src="<?= e($img) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                  <div>
                    <div class="fw-semibold small">
                      <a href="product.php?id=<?= (int)$it['product_id'] ?>" class="text-decoration-none"><?= e($it['product_name'] ?? ('#'.$it['product_id'])) ?></a>
                    </div>
                    <div class="text-muted xsmall" style="font-size:.65rem;">ID: <?= (int)$it['product_id'] ?></div>
                  </div>
                </div>
              </td>
              <td><?= price((float)$it['price']) ?></td>
              <td><?= (int)$it['qty'] ?></td>
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
              <th colspan="4" class="text-end">الإجمالي المسجل (الحقل total)</th>
              <th><?= price($storedTotal) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- بيانات إضافية (CliQ / بطاقة) -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="meta-box h-100">
        <h2 class="h6 fw-bold mb-3">تفاصيل الدفع الإضافية</h2>
        <?php if (!$paymentMeta): ?>
          <div class="text-muted small">لا توجد بيانات إضافية.</div>
        <?php else: ?>
          <ul class="small mb-0">
            <?php foreach ($paymentMeta as $k=>$v): ?>
              <li><strong><?= e($k) ?>:</strong> <?= e(is_scalar($v)?$v:json_encode($v,JSON_UNESCAPED_UNICODE)) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-6">
      <div class="meta-box h-100">
        <h2 class="h6 fw-bold mb-3">آخر طلبات لنفس العميل</h2>
        <?php if (!$recentOrders): ?>
          <div class="text-muted small">لا توجد طلبات أخرى.</div>
        <?php else: ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($recentOrders as $ro): ?>
              <li class="mb-1">
                <a class="text-decoration-none" href="order_view.php?id=<?= (int)$ro['id'] ?>">
                  #<?= (int)$ro['id'] ?> — <?= price((float)$ro['total']) ?> — <?= e($ro['status']) ?>/<?= e($ro['payment_status']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2">
    <a href="index.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-right"></i> المتجر</a>
    <a href="cart.php" class="btn btn-primary rounded-pill"><i class="bi bi-cart"></i> السلة</a>
  </div>
</div>

<?php include __DIR__ . '/partials/shop_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>