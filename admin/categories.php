<?php
// المسار: admin/categories.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

$base = rtrim((require __DIR__.'/../config.php')['base_url'], '/');
$msg = '';
$err = '';

// إضافة قسم جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act'] === 'create') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err = 'فشل التحقق الأمني (CSRF).'; }
  else {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name === '') $err = 'يرجى إدخال اسم القسم.';
    if ($slug === '') $slug = strtolower(preg_replace('~[^a-z0-9\-]+~i', '-', $name));
    if (!$err) {
      $st = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:n,:s)');
      try {
        $st->execute([':n'=>$name, ':s'=>$slug]);
        $msg = 'تم إنشاء القسم بنجاح.';
      } catch (PDOException $e) {
        $err = 'فشل إنشاء القسم. تأكد أن السلاگ غير مكرر.';
      }
    }
  }
}

// حذف قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act'] === 'delete') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err = 'فشل التحقق الأمني (CSRF).'; }
  else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $pdo->prepare('DELETE FROM categories WHERE id=:id')->execute([':id'=>$id]);
      $msg = 'تم حذف القسم.';
    }
  }
}

$cats = $pdo->query('SELECT * FROM categories ORDER BY id DESC')->fetchAll();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>إدارة الأقسام</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <h1 class="mb-4">إدارة الأقسام</h1>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <h2 class="h6 mb-3">إضافة قسم جديد</h2>
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="act" value="create">
      <div class="col-md-5">
        <label class="form-label">اسم القسم</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="col-md-5">
        <label class="form-label">السلاگ (اختياري)</label>
        <input type="text" name="slug" class="form-control" placeholder="مثال: air-conditioners">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-success w-100 rounded-pill">إضافة</button>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>الاسم</th>
          <th>السلاگ</th>
          <th class="text-end">إجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cats as $c): ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= e($c['name']) ?></td>
            <td><?= e($c['slug']) ?></td>
            <td class="text-end">
              <a href="category_edit.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="bi bi-pencil-square"></i> تعديل
              </a>
              <form method="post" class="d-inline" onsubmit="return confirm('حذف هذا القسم؟ قد تتأثر المنتجات المرتبطة.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger rounded-pill">
                  <i class="bi bi-trash"></i> حذف
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-4">
    <a href="dashboard.php" class="btn btn-secondary rounded-pill"><i class="bi bi-arrow-right"></i> عودة للوحة التحكم</a>
  </div>
</div>
</body>
</html>