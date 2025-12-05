<?php
// المسار: admin/slider.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

if (!function_exists('e')) { function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$err = ''; $msg = '';

function asset_url_local(string $path): string {
  $config = require dirname(__DIR__) . '/config.php';
  $base = rtrim($config['base_url'], '/');
  $path = trim($path);
  if ($path === '') return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  if (str_starts_with($path, '/uploads/')) return $base . '/public' . $path;
  if (str_starts_with($path, '/public/')) return $base . $path;
  if ($path[0] === '/') return $base . $path;
  return $base . '/' . ltrim($path, '/');
}

// حفظ (إنشاء/تعديل)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act'] ?? '')==='save') {
  if (!csrf_check($_POST['csrf'] ?? '')) {
    $err = 'فشل التحقق الأمني (CSRF).';
  } else {
    $sid       = (int)($_POST['id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $subtitle  = trim($_POST['subtitle'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);
    $linkUrl   = trim($_POST['link_url'] ?? '');
    $sort      = (int)($_POST['sort_order'] ?? 0);
    $active    = !empty($_POST['is_active']) ? 1 : 0;

    // معالجة الصورة
    $image = trim($_POST['image_existing'] ?? '');
    if (!empty($_FILES['image']['name'])) {
      $r = save_upload($_FILES['image'], 'image');
      if ($r['ok']) $image = $r['path']; else $err = $r['error'];
    }
    if (!$image) $err = 'الصورة مطلوبة.';

    if (!$err) {
      try {
        if ($sid > 0) {
          $st = $pdo->prepare('UPDATE homepage_slider SET title=:t, subtitle=:s, image=:i, product_id=:pid, link_url=:u, sort_order=:so, is_active=:a WHERE id=:id');
          $st->execute([':t'=>$title?:null, ':s'=>$subtitle?:null, ':i'=>$image, ':pid'=>$productId?:null, ':u'=>$linkUrl?:null, ':so'=>$sort, ':a'=>$active, ':id'=>$sid]);
          $msg = 'تم تحديث العنصر بنجاح.';
          $action = 'list';
        } else {
          $st = $pdo->prepare('INSERT INTO homepage_slider (title,subtitle,image,product_id,link_url,sort_order,is_active) VALUES (:t,:s,:i,:pid,:u,:so,:a)');
          $st->execute([':t'=>$title?:null, ':s'=>$subtitle?:null, ':i'=>$image, ':pid'=>$productId?:null, ':u'=>$linkUrl?:null, ':so'=>$sort, ':a'=>$active]);
          $msg = 'تم إضافة عنصر جديد للسلايدر.';
          $action = 'list';
        }
      } catch (Throwable $e) {
        $err = 'خطأ بالحفظ: ' . $e->getMessage();
      }
    }
  }
}

// جلب عنصر للتعديل
$editRow = null;
if ($action==='edit' && $id>0) {
  $st = $pdo->prepare('SELECT * FROM homepage_slider WHERE id=:id');
  $st->execute([':id'=>$id]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
  if (!$editRow) { $action='list'; $err='العنصر غير موجود.'; }
}

// جلب قائمة العناصر
$rows = [];
if ($action==='list') {
  $rows = $pdo->query('SELECT * FROM homepage_slider ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
}

// جلب المنتجات لمربع اختيار (اختياري)
$products = $pdo->query('SELECT id,name FROM products ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>إدارة السلايدر</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#f6f7fb;}
    .thumb{height:56px;width:80px;object-fit:cover;border-radius:.35rem;border:1px solid #e2e8f0;}
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">إدارة السلايدر</h1>
    <div>
      <a href="slider.php?action=new" class="btn btn-success rounded-pill"><i class="bi bi-plus-lg"></i> عنصر جديد</a>
      <a href="dashboard.php" class="btn btn-secondary rounded-pill">لوحة التحكم</a>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <?php if ($action==='list'): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>الصورة</th>
            <th>العنوان</th>
            <th>رابط</th>
            <th>ترتيب</th>
            <th>فعال؟</th>
            <th class="text-end">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): 
            $link = $r['link_url'];
            if ($r['product_id']) { $cfg = require dirname(__DIR__).'/config.php'; $link = rtrim($cfg['base_url'],'/').'/public/product.php?id='.$r['product_id']; }
          ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><img class="thumb" src="<?= e(asset_url_local($r['image'])) ?>" alt=""></td>
              <td><?= e($r['title'] ?? '—') ?></td>
              <td class="text-truncate" style="max-width:320px;"><a href="<?= e($link ?: '#') ?>" target="_blank"><?= e($link ?: '—') ?></a></td>
              <td><?= (int)$r['sort_order'] ?></td>
              <td><?= $r['is_active'] ? 'نعم' : 'لا' ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary rounded-pill" href="slider.php?action=edit&id=<?= (int)$r['id'] ?>"><i class="bi bi-pencil-square"></i> تعديل</a>
                <form method="post" action="slider_delete.php" class="d-inline" onsubmit="return confirm('حذف هذا العنصر من السلايدر؟');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> حذف</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted">لا توجد عناصر بعد.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php else: 
    $isEdit = $action==='edit' && $editRow;
    $f = $isEdit ? $editRow : ['id'=>0,'title'=>'','subtitle'=>'','image'=>'','product_id'=>null,'link_url'=>'','sort_order'=>0,'is_active'=>1];
  ?>
    <form method="post" enctype="multipart/form-data" class="card p-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="row g-3">
        <div class="col-lg-6">
          <label class="form-label">العنوان (اختياري)</label>
          <input type="text" name="title" class="form-control" value="<?= e($f['title'] ?? '') ?>">
        </div>
        <div class="col-lg-6">
          <label class="form-label">العنوان الفرعي (اختياري)</label>
          <input type="text" name="subtitle" class="form-control" value="<?= e($f['subtitle'] ?? '') ?>">
        </div>

        <div class="col-lg-6">
          <label class="form-label">الصورة (1200×360 مقترح)</label>
          <input type="file" name="image" accept="image/*" class="form-control" <?= $isEdit?'':'required' ?>>
          <?php if ($f['image']): ?>
            <div class="mt-2">
              <img src="<?= e(asset_url_local($f['image'])) ?>" class="thumb" alt="">
              <input type="hidden" name="image_existing" value="<?= e($f['image']) ?>">
            </div>
          <?php endif; ?>
        </div>

        <div class="col-lg-3">
          <label class="form-label">ربط بمنتج (اختياري)</label>
          <select name="product_id" class="form-select">
            <option value="0">— بدون —</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)$f['product_id']=== (int)$p['id'] ? 'selected' : '' ?>>#<?= (int)$p['id'] ?> — <?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3">
          <label class="form-label">أو رابط مخصص (يُستخدم إذا لم تحدد منتجاً)</label>
          <input type="url" name="link_url" class="form-control" placeholder="https://..." value="<?= e($f['link_url'] ?? '') ?>">
        </div>

        <div class="col-lg-3">
          <label class="form-label">الترتيب</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)$f['sort_order'] ?>">
        </div>
        <div class="col-lg-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkActive" name="is_active" value="1" <?= !empty($f['is_active'])?'checked':'' ?>>
            <label class="form-check-label" for="chkActive">فعّال</label>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-success rounded-pill"><i class="bi bi-save"></i> حفظ</button>
        <a href="slider.php" class="btn btn-secondary rounded-pill">رجوع</a>
      </div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>