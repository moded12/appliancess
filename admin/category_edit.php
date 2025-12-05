<?php
// المسار: admin/category_edit.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: categories.php'); exit; }

$msg = '';
$err = '';
$st = $pdo->prepare('SELECT * FROM categories WHERE id=:id');
$st->execute([':id'=>$id]);
$cat = $st->fetch();
if (!$cat) { header('Location: categories.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err = 'فشل التحقق الأمني (CSRF).'; }
  else {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name === '') $err = 'يرجى إدخال اسم القسم.';
    if ($slug === '') $slug = strtolower(preg_replace('~[^a-z0-9\-]+~i', '-', $name));
    if (!$err) {
      $up = $pdo->prepare('UPDATE categories SET name=:n, slug=:s WHERE id=:id');
      try {
        $up->execute([':n'=>$name, ':s'=>$slug, ':id'=>$id]);
        $msg = 'تم حفظ التعديلات.';
        // تحديث البيانات المعروضة
        $cat['name'] = $name;
        $cat['slug'] = $slug;
      } catch (PDOException $e) {
        $err = 'فشل حفظ التعديلات. تأكد أن السلاگ غير مكرر.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>تعديل القسم</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width:720px;">
  <h1 class="mb-4 h4">تعديل القسم</h1>
  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

  <form method="post" class="card p-3">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="mb-3">
      <label class="form-label">اسم القسم</label>
      <input type="text" name="name" class="form-control" value="<?= e($cat['name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">السلاگ</label>
      <input type="text" name="slug" class="form-control" value="<?= e($cat['slug']) ?>">
      <div class="form-text">اتركه فارغاً لتوليده تلقائياً.</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-success rounded-pill">حفظ</button>
      <a href="categories.php" class="btn btn-secondary rounded-pill">رجوع</a>
    </div>
  </form>
</div>
</body>
</html>