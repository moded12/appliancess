<?php
// المسار: public/cart.php

require_once __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../config.php';
$base   = rtrim($config['base_url'], '/');
if (!function_exists('e')) { function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
function price($v){ return '$' . number_format((float)$v, 2); }

session_start();
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = []; // [product_id => qty]

// عمليات السلة
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
    header('Location: ' . $base . '/public/cart.php?added=1'); exit;
  } elseif ($action === 'update') {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
      foreach ($_POST['qty'] as $pid => $q) {
        $pid = (int)$pid; $q = max(0, (int)$q);
        if ($q === 0) { unset($_SESSION['cart'][$pid]); }
        else { $_SESSION['cart'][$pid] = $q; }
      }
    }
  } elseif ($action === 'remove') {
    $pid = (int)($_POST['product_id'] ?? 0);
    unset($_SESSION['cart'][$pid]);
  } elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
  }
}

// جلب بيانات المنتجات في السلة
$items = [];
$total = 0.0;
if ($_SESSION['cart']) {
  $ids = array_map('intval', array_keys($_SESSION['cart']));
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT id,name,price,main_image FROM products WHERE id IN ($in)");
  $st->execute($ids);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $map = [];
  foreach ($rows as $r) $map[$r['id']] = $r;
  foreach ($_SESSION['cart'] as $pid => $qty) {
    if (!isset($map[$pid])) continue;
    $p = $map[$pid];
    $line = $p['price'] * $qty;
    $total += $line;
    $items[] = [
      'id' => $pid,
      'name' => $p['name'],
      'price' => (float)$p['price'],
      'qty' => $qty,
      'image' => $p['main_image'] ? ($base . $p['main_image']) : null,
      'subtotal' => $line,
    ];
  }
}

$title = 'سلة المشتريات';
include __DIR__ . '/partials/shop_header.php';
?>

<h1 class="h4 mb-3">سلة المشتريات</h1>
<?php if (isset($_GET['added'])): ?>
  <div class="alert alert-success">تمت إضافة المنتج إلى السلة.</div>
<?php endif; ?>

<?php if (!$items): ?>
  <div class="alert alert-info">سلتك فارغة حالياً.</div>
  <a class="btn btn-primary rounded-pill" href="<?= $base ?>/public/index.php"><i class="bi bi-shop"></i> ابدأ التسوق</a>
<?php else: ?>
  <form method="post" class="table-responsive">
    <input type="hidden" name="action" value="update">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>المنتج</th>
          <th>السعر</th>
          <th style="width:140px">الكمية</th>
          <th>الإجمالي</th>
          <th class="text-end">إجراء</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if ($it['image']): ?>
                  <img src="<?= e($it['image']) ?>" alt="" class="rounded" style="height:50px;width:60px;object-fit:cover">
                <?php else: ?>
                  <div class="bg-light rounded" style="height:50px;width:60px;"></div>
                <?php endif; ?>
                <a href="<?= $base ?>/public/product.php?id=<?= (int)$it['id'] ?>" class="text-decoration-none"><?= e($it['name']) ?></a>
              </div>
            </td>
            <td><?= price($it['price']) ?></td>
            <td>
              <input type="number" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>" min="0" class="form-control">
            </td>
            <td><?= price($it['subtotal']) ?></td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> إزالة</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" class="text-end fw-bold">الإجمالي</td>
          <td class="fw-bold"><?= price($total) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-repeat"></i> تحديث السلة</button>
      <form method="post">
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-outline-danger rounded-pill" onclick="return confirm('مسح السلة بالكامل؟');"><i class="bi bi-x-circle"></i> مسح السلة</button>
      </form>
      <a class="ms-auto btn btn-success rounded-pill" href="<?= $base ?>/public/checkout.php"><i class="bi bi-credit-card"></i> إتمام الشراء</a>
    </div>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/partials/shop_footer.php'; ?>