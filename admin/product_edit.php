<?php
// المسار: admin/product_edit.php
// هذا الملف كامل بعد التحديث ويشمل زر التبديل "مميز" بالإضافة لباقي وظائف التعديل والوسائط.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: products.php'); exit; }

$ok  = isset($_GET['ok']) ? 'تم إنشاء المنتج بنجاح. يمكنك الآن إضافة/تعديل الوسائط.' : '';
$msg = '';
$err = '';

$cats = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();

// جلب المنتج
$st = $pdo->prepare('SELECT * FROM products WHERE id=:id');
$st->execute([':id'=>$id]);
$product = $st->fetch(PDO::FETCH_ASSOC);
if (!$product) { header('Location: products.php'); exit; }

// تحديث بيانات المنتج (حفظ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act']==='save') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err = 'فشل التحقق الأمني (CSRF).'; }
  else {
    $name  = trim($_POST['name'] ?? '');
    $slug  = trim($_POST['slug'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $desc  = trim($_POST['description'] ?? '');

    if ($name === '') $err = 'يرجى إدخال اسم المنتج.';
    if ($slug === '') $slug = strtolower(preg_replace('~[^a-z0-9\-]+~i', '-', $name));

    // صورة رئيسية جديدة (اختياري)
    $mainImagePath = $product['main_image'];
    if (!$err && !empty($_FILES['main_image']['name'])) {
      $res = save_upload($_FILES['main_image'], 'image');
      if ($res['ok']) $mainImagePath = $res['path']; else $err = $res['error'];
    }

    if (!$err) {
      $up = $pdo->prepare('UPDATE products SET category_id=:c,name=:n,slug=:s,description=:d,price=:p,stock=:st,main_image=:m WHERE id=:id');
      $up->execute([
        ':c'=>$catId?:null, ':n'=>$name, ':s'=>$slug, ':d'=>$desc!==''?$desc:null,
        ':p'=>$price, ':st'=>$stock, ':m'=>$mainImagePath, ':id'=>$id
      ]);
      $msg = 'تم حفظ التعديلات.';
      // تحديث نسخة العرض
      $product = array_merge($product, [
        'category_id'=>$catId?:null, 'name'=>$name, 'slug'=>$slug, 'description'=>$desc!==''?$desc:null,
        'price'=>$price, 'stock'=>$stock, 'main_image'=>$mainImagePath
      ]);
    }
  }
}

// إضافة صور متعددة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act']==='add_images') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err = 'فشل التحقق الأمني (CSRF).'; }
  else {
    if (!empty($_FILES['gallery']['name'][0])) {
      foreach ($_FILES['gallery']['name'] as $i=>$nm) {
        if (!$_FILES['gallery']['name'][$i]) continue;
        $file = [
          'name'=>$_FILES['gallery']['name'][$i],
          'type'=>$_FILES['gallery']['type'][$i],
          'tmp_name'=>$_FILES['gallery']['tmp_name'][$i],
          'error'=>$_FILES['gallery']['error'][$i],
          'size'=>$_FILES['gallery']['size'][$i],
        ];
        $r = save_upload($file, 'image');
        if ($r['ok']) {
          $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:p,"image",:f,0)')
              ->execute([':p'=>$id, ':f'=>$r['path']]);
        } else {
          $err = $r['error'];
        }
      }
      $msg = $err ? '' : 'تمت إضافة الصور.';
    } else {
      $err = 'اختر صورة واحدة على الأقل.';
    }
  }
}

// إضافة فيديو (رابط/ملف)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act']==='add_video') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err = 'فشل التحقق الأمني (CSRF).'; }
  else {
    $video_url = trim($_POST['video_url'] ?? '');
    if ($video_url !== '') {
      $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:p,"video",:f,0)')
          ->execute([':p'=>$id, ':f'=>$video_url]);
      $msg = 'تم إضافة الفيديو (رابط).';
    } elseif (!empty($_FILES['video_file']['name'])) {
      $vr = save_upload($_FILES['video_file'], 'video');
      if ($vr['ok']) {
        $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:p,"video",:f,0)')
            ->execute([':p'=>$id, ':f'=>$vr['path']]);
        $msg = 'تم إضافة الفيديو (ملف).';
      } else {
        $err = $vr['error'];
      }
    } else {
      $err = 'أضف رابط فيديو أو ارفع ملف فيديو.';
    }
  }
}

$media = $pdo->prepare('SELECT * FROM product_media WHERE product_id=:p ORDER BY sort_order,id');
$media->execute([':p'=>$id]);
$mediaList = $media->fetchAll();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>تعديل منتج</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width:980px;">
  <h1 class="mb-3 h4">تعديل منتج: <?= htmlspecialchars($product['name']) ?></h1>
  <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- زر تبديل مميز -->
  <div class="mb-3 d-flex gap-2">
    <form method="post" action="product_feature_toggle.php" class="m-0">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
      <?php if (!empty($product['is_featured'])): ?>
        <button class="btn btn-warning rounded-pill"><i class="bi bi-star-fill"></i> إلغاء تعيين كمميز</button>
      <?php else: ?>
        <button class="btn btn-outline-warning rounded-pill"><i class="bi bi-star"></i> تعيين كمنتج مميز</button>
      <?php endif; ?>
    </form>

    <a href="product_view.php?id=<?= (int)$product['id'] ?>" class="btn btn-secondary rounded-pill">عرض في المتجر</a>
  </div>

  <form method="post" enctype="multipart/form-data" class="card p-3 mb-4">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="act" value="save">
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">اسم المنتج</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">السلاگ</label>
        <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($product['slug']) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">القسم</label>
        <select name="category_id" class="form-select">
          <option value="0">— بدون قسم —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $product['category_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">السعر ($)</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= htmlspecialchars($product['price']) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">المخزون</label>
        <input type="number" min="0" name="stock" class="form-control" value="<?= (int)$product['stock'] ?>">
      </div>

      <div class="col-12">
        <label class="form-label">الوصف</label>
        <textarea name="description" rows="5" class="form-control"><?= htmlspecialchars($product['description']) ?></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">الصورة الرئيسية</label>
        <input type="file" name="main_image" accept="image/*" class="form-control">
        <?php if ($product['main_image']): ?>
          <div class="mt-2"><img src="<?= htmlspecialchars($product['main_image']) ?>" alt="" style="max-height:120px" class="rounded border"></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-success rounded-pill"><i class="bi bi-save"></i> حفظ</button>
      <a href="products.php" class="btn btn-secondary rounded-pill">رجوع</a>
    </div>
  </form>

  <div class="card p-3 mb-4">
    <h2 class="h6 mb-3">إضافة صور للمنتج</h2>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="act" value="add_images">
      <div class="col-md-9">
        <input type="file" name="gallery[]" accept="image/*" class="form-control" multiple required>
      </div>
      <div class="col-md-3 d-grid">
        <button class="btn btn-primary rounded-pill">إضافة الصور</button>
      </div>
    </form>
  </div>

  <div class="card p-3 mb-4">
    <h2 class="h6 mb-3">إضافة فيديو للمنتج</h2>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="act" value="add_video">
      <div class="col-md-6">
        <input type="url" name="video_url" class="form-control" placeholder="رابط يوتيوب/خارجي (اختياري)">
      </div>
      <div class="col-md-4">
        <input type="file" name="video_file" accept="video/mp4,video/webm" class="form-control">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary rounded-pill">إضافة الفيديو</button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <h2 class="h6 mb-3">وسائط المنتج</h2>
    <?php if (!$mediaList): ?>
      <div class="text-muted">لا توجد وسائط بعد.</div>
    <?php else: ?>
      <div class="row g-2">
        <?php foreach ($mediaList as $m): ?>
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 text-center">
              <?php if ($m['media_type']==='image'): ?>
                <img src="<?= htmlspecialchars($m['file']) ?>" class="img-fluid rounded" alt="">
              <?php else: ?>
                <?php if (preg_match('~^https?://~', $m['file'])): ?>
                  <div class="small text-truncate"><a href="<?= htmlspecialchars($m['file']) ?>" target="_blank">رابط فيديو</a></div>
                <?php else: ?>
                  <video src="<?= htmlspecialchars($m['file']) ?>" controls style="max-width:100%;height:auto"></video>
                <?php endif; ?>
              <?php endif; ?>
              <form method="post" action="product_media_delete.php" class="mt-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('حذف الوسيط؟');">
                  <i class="bi bi-trash"></i> حذف
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>