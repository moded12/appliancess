<?php require_once __DIR__.'/../includes/functions.php'; require_login(); require_once __DIR__.'/../includes/db.php';
$title='لوحة التحكم'; include __DIR__.'/../includes/header.php';
$counts=['products'=>(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),'categories'=>(int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),'orders'=>(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn()];
?>
<h1 class="h4 mb-4">لوحة التحكم</h1>
<div class="row g-3">
  <div class="col-md-4"><div class="card p-3 glass"><div class="d-flex align-items-center gap-3"><div class="display-6 text-success"><i class="bi bi-box-seam"></i></div><div><div class="text-muted">المنتجات</div><div class="fs-2 fw-bold"><?= $counts['products'] ?></div></div></div></div></div>
  <div class="col-md-4"><div class="card p-3 glass"><div class="d-flex align-items-center gap-3"><div class="display-6 text-success"><i class="bi bi-collection"></i></div><div><div class="text-muted">الأقسام</div><div class="fs-2 fw-bold"><?= $counts['categories'] ?></div></div></div></div></div>
  <div class="col-md-4"><div class="card p-3 glass"><div class="d-flex align-items-center gap-3"><div class="display-6 text-success"><i class="bi bi-receipt"></i></div><div><div class="text-muted">الطلبات</div><div class="fs-2 fw-bold"><?= $counts['orders'] ?></div></div></div></div></div>
</div>
<div class="mt-4 d-flex gap-2">
  <a class="btn btn-brand rounded-pill" href="<?= $base ?>/admin/products.php"><i class="bi bi-plus-circle"></i> إدارة المنتجات</a>
  <a class="btn btn-outline-primary rounded-pill" href="<?= $base ?>/admin/categories.php"><i class="bi bi-grid"></i> إدارة الأقسام</a>
  <a class="btn btn-outline-secondary rounded-pill" href="<?= $base ?>/admin/orders.php"><i class="bi bi-receipt"></i> عرض الطلبات</a>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
