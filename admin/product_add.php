<?php
// المسار: admin/product_add.php
// إضافة منتج جديد مع:
// - توليد slug فريد يدعم العربية
// - التقاط صورة بالكاميرا (Base64)
// - رفع صورة رئيسية اختيارية + صور معرض متعددة + فيديو (رابط/ملف)
// - تعيين أول صورة من المعرض كصورة رئيسية تلقائياً إذا لم تُحدد صورة رئيسية

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('e')) {
    function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* أدوات إنشاء slug فريد */
function slugify_base(string $text): string {
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; Latin-ASCII;', $text);
    } else {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) $text = $converted;
    }
    $ar = ['أ','إ','آ','ا','ب','ت','ث','ج','ح','خ','د','ذ','ر','ز','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','ه','و','ي','ة','ى','ئ','ؤ','ﻻ','لا','ٔ','ء'];
    $en = ['a','i','a','a','b','t','th','j','h','kh','d','th','r','z','s','sh','s','d','t','z','a','gh','f','q','k','l','m','n','h','w','y','h','a','y','w','la','la','',''];
    $text = str_replace($ar, $en, $text);
    $text = strtolower($text);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text ?: '';
}
function generate_fallback_slug(): string {
    return 'product-'.date('ymd').'-'.substr(bin2hex(random_bytes(4)),0,8);
}
function make_unique_slug(PDO $pdo, string $baseSlug): string {
    $slug = $baseSlug !== '' ? $baseSlug : generate_fallback_slug();
    if ($slug === '-' || $slug === '') $slug = generate_fallback_slug();
    $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug=:s');
    $current = $slug; $i = 2;
    while (true) {
        $check->execute([':s'=>$current]);
        if ((int)$check->fetchColumn() === 0) return $current;
        $current = $slug . '-' . $i;
        $i++;
        if ($i > 5000) {
            $current = generate_fallback_slug();
            $check->execute([':s'=>$current]);
            if ((int)$check->fetchColumn() === 0) return $current;
            $current .= '-'.substr(bin2hex(random_bytes(2)),0,4);
            return $current;
        }
    }
}

/* الأقسام */
$cats = [];
try {
    $cats = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $cats = [];
}

$err = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'create') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $err = 'انتهت صلاحية النموذج، أعد التحميل.';
    } else {
        $name   = trim($_POST['name'] ?? '');
        $slugIn = trim($_POST['slug'] ?? '');
        $catId  = (int)($_POST['category_id'] ?? 0);
        $price  = (float)($_POST['price'] ?? 0);
        $stock  = (int)($_POST['stock'] ?? 0);
        $desc   = trim($_POST['description'] ?? '');
        $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;

        if ($name === '') {
            $err = 'اسم المنتج مطلوب.';
        }

        $baseSlug = $slugIn !== '' ? slugify_base($slugIn) : slugify_base($name);
        $slug     = make_unique_slug($pdo, $baseSlug);

        // الصورة الرئيسية من الكاميرا أو الملف
        $mainImagePath = '';
        if (!$err) {
            if (!empty($_POST['main_camera_image'])) {
                $b64 = $_POST['main_camera_image'];
                if (preg_match('~^data:image/(\w+);base64,~', $b64, $m)) {
                    $ext  = strtolower($m[1]);
                    $data = substr($b64, strpos($b64, ',') + 1);
                    $bin  = base64_decode($data);
                    if ($bin === false) {
                        $err = 'فشل فك ترميز صورة الكاميرا.';
                    } else {
                        $tmpName = sys_get_temp_dir() . '/cam_' . bin2hex(random_bytes(6)) . '.' . ($ext==='jpeg'?'jpg':$ext);
                        file_put_contents($tmpName, $bin);
                        $fakeFile = [
                            'name'     => 'camera.' . $ext,
                            'type'     => 'image/' . ($ext==='jpg'?'jpeg':$ext),
                            'tmp_name' => $tmpName,
                            'error'    => 0,
                            'size'     => strlen($bin),
                        ];
                        $r = save_upload($fakeFile, 'image');
                        @unlink($tmpName);
                        if ($r['ok']) $mainImagePath = $r['path']; else $err = $r['error'];
                    }
                } else {
                    $err = 'تنسيق بيانات الكاميرا غير صالح.';
                }
            } elseif (!empty($_FILES['main_image']['name'])) {
                $r = save_upload($_FILES['main_image'], 'image');
                if ($r['ok']) $mainImagePath = $r['path']; else $err = $r['error'];
            }
        }

        $video_url    = trim($_POST['video_url'] ?? '');
        $hasVideoFile = !empty($_FILES['video_file']['name']);

        if (!$err) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO products (category_id,name,slug,description,price,stock,main_image,is_featured,created_at)
                                       VALUES (:c,:n,:s,:d,:p,:st,:m,:f,NOW())');
                $stmt->execute([
                    ':c'=>$catId ?: null,
                    ':n'=>$name,
                    ':s'=>$slug,
                    ':d'=>$desc!=='' ? $desc : null,
                    ':p'=>$price,
                    ':st'=>$stock,
                    ':m'=>$mainImagePath,
                    ':f'=>$isFeatured
                ]);
                $newId = (int)$pdo->lastInsertId();

                // صور المعرض
                $firstGalleryImage = '';
                if (!empty($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
                    $insImg = $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:pid,"image",:f,0)');
                    foreach ($_FILES['gallery']['name'] as $i => $nm) {
                        if (!$nm) continue;
                        $file = [
                            'name'     => $_FILES['gallery']['name'][$i],
                            'type'     => $_FILES['gallery']['type'][$i],
                            'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                            'error'    => $_FILES['gallery']['error'][$i],
                            'size'     => $_FILES['gallery']['size'][$i],
                        ];
                        $g = save_upload($file, 'image');
                        if ($g['ok']) {
                            $insImg->execute([':pid'=>$newId, ':f'=>$g['path']]);
                            if ($firstGalleryImage === '') $firstGalleryImage = $g['path'];
                        } else {
                            @file_put_contents(__DIR__.'/../includes/log_gallery_uploads.log',
                                date('c').' - product '.$newId.' - '.$g['error'].PHP_EOL, FILE_APPEND);
                        }
                    }
                }

                // فيديو (رابط)
                if ($video_url !== '') {
                    $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:pid,"video",:f,0)')
                        ->execute([':pid'=>$newId, ':f'=>$video_url]);
                }
                // فيديو (ملف)
                if ($hasVideoFile) {
                    $vr = save_upload($_FILES['video_file'], 'video');
                    if ($vr['ok']) {
                        $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:pid,"video",:f,0)')
                            ->execute([':pid'=>$newId, ':f'=>$vr['path']]);
                    } else {
                        @file_put_contents(__DIR__.'/../includes/log_video_uploads.log',
                            date('c').' - product '.$newId.' - '.$vr['error'].PHP_EOL, FILE_APPEND);
                    }
                }

                // تعيين أول صورة معرض كصورة رئيسية إذا لم تُحدد صورة رئيسية
                if ($mainImagePath === '' && $firstGalleryImage !== '') {
                    $upd = $pdo->prepare('UPDATE products SET main_image=:m WHERE id=:id');
                    $upd->execute([':m'=>$firstGalleryImage, ':id'=>$newId]);
                }

                $pdo->commit();
                header('Location: product_edit.php?id='.$newId.'&ok=1');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $err = 'تعذر حفظ المنتج: '.$e->getMessage();
            }
        }
    }
}

$title = 'إضافة منتج جديد';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title><?= e($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#f6f7fb;}
    .card-soft{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 2px 6px rgba(15,23,42,.06);padding:1rem 1.25rem;}
    .preview-main{width:160px;height:160px;object-fit:cover;border:2px solid #0d6efd;border-radius:.75rem;margin-top:.5rem;}
    .preview-img{width:110px;height:110px;object-fit:cover;border:1px solid #ddd;border-radius:.6rem;margin:.35rem;}
    .cam-area{border:1px dashed #cbd5e1;background:#fff;border-radius:.75rem;padding:1rem;}
  </style>
</head>
<body>
<div class="container py-4" style="max-width:1000px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 m-0"><?= e($title) ?></h1>
    <a href="products.php" class="btn btn-secondary rounded-pill"><i class="bi bi-arrow-right"></i> رجوع</a>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="productForm" class="card-soft">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="act" value="create">
    <input type="hidden" name="main_camera_image" id="mainCameraImage">

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">اسم المنتج</label>
        <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">السلاگ (اختياري)</label>
        <input type="text" name="slug" class="form-control" value="<?= e($_POST['slug'] ?? '') ?>" placeholder="إن تركته فارغاً سيتولد تلقائياً">
      </div>

      <div class="col-md-4">
        <label class="form-label">القسم</label>
        <select name="category_id" class="form-select">
          <option value="0">— بدون قسم —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (!empty($_POST['category_id']) && (int)$_POST['category_id']===(int)$c['id'])?'selected':'' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">السعر</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" required value="<?= e($_POST['price'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">المخزون</label>
        <input type="number" min="0" name="stock" class="form-control" value="<?= e($_POST['stock'] ?? '0') ?>">
      </div>

      <div class="col-12">
        <label class="form-label">الوصف</label>
        <textarea name="description" rows="4" class="form-control"><?= e($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="col-md-4">
        <label class="form-label d-flex justify-content-between align-items-center">
          <span>الصورة الرئيسية (ملف)</span>
          <small class="text-muted">أو التصوير المباشر</small>
        </label>
        <input type="file" name="main_image" accept="image/*" capture="environment" class="form-control">
        <img id="mainPreview" class="preview-main d-none" alt="">
      </div>

      <div class="col-md-8">
        <label class="form-label">صور المعرض (متعددة)</label>
        <input type="file" name="gallery[]" accept="image/*" multiple capture="environment" class="form-control">
        <div id="galleryPreview" class="d-flex flex-wrap mt-2"></div>
      </div>

      <div class="col-12">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="is_featured" id="featSwitch" value="1" <?= !empty($_POST['is_featured'])?'checked':'' ?>>
          <label class="form-check-label" for="featSwitch">تعيين كمميز</label>
        </div>
      </div>
    </div>

    <!-- التصوير المباشر -->
    <div class="cam-area mt-4">
      <h6 class="mb-2"><i class="bi bi-camera-video"></i> التصوير المباشر (اختياري)</h6>
      <p class="small text-muted mb-2">افتح الكاميرا ثم التقط صورة. إذا التقطت صورة فستُستخدم كصورة رئيسية حتى لو اخترت ملفاً.</p>
      <div class="d-flex flex-wrap gap-3 align-items-start">
        <div>
          <video id="camStream" playsinline class="rounded border d-none" style="width:220px;height:220px;object-fit:cover;"></video>
          <canvas id="camCanvas" class="d-none"></canvas>
        </div>
        <div class="d-flex flex-column gap-2">
          <button type="button" id="openCamBtn" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-camera-video"></i> فتح الكاميرا
          </button>
          <button type="button" id="captureBtn" class="btn btn-success btn-sm d-none">
            <i class="bi bi-camera"></i> التقاط
          </button>
          <button type="button" id="closeCamBtn" class="btn btn-outline-secondary btn-sm d-none">
            <i class="bi bi-x-circle"></i> إغلاق
          </button>
          <small id="camStatus" class="text-muted"></small>
        </div>
        <div>
          <img id="capturedImagePreview" class="preview-main d-none" alt="صورة ملتقطة">
        </div>
      </div>
    </div>

    <!-- الفيديو -->
    <div class="card-soft mt-4">
      <h6 class="mb-2"><i class="bi bi-youtube"></i> فيديو المنتج (اختياري)</h6>
      <div class="row g-3">
        <div class="col-md-7">
          <label class="form-label">رابط فيديو (يوتيوب أو خارجي)</label>
          <input type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." value="<?= e($_POST['video_url'] ?? '') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">أو ارفع ملف فيديو (MP4/WEBM)</label>
          <input type="file" name="video_file" accept="video/mp4,video/webm" class="form-control">
        </div>
      </div>
      <small class="text-muted d-block mt-2">يمكن استخدام أحد الخيارين أو كلاهما.</small>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-success rounded-pill">
        <i class="bi bi-save"></i> حفظ المنتج
      </button>
      <a href="products.php" class="btn btn-secondary rounded-pill">رجوع</a>
    </div>
  </form>
</div>

<script>
// معاينة الصورة الرئيسية
const mainInput = document.querySelector('input[name="main_image"]');
const mainPreview = document.getElementById('mainPreview');
mainInput?.addEventListener('change', () => {
  if (mainInput.files && mainInput.files[0]) {
    const url = URL.createObjectURL(mainInput.files[0]);
    mainPreview.src = url;
    mainPreview.classList.remove('d-none');
  } else {
    mainPreview.classList.add('d-none');
  }
});
// معاينة صور المعرض
const galleryInput = document.querySelector('input[name="gallery[]"]');
const galleryPreview = document.getElementById('galleryPreview');
galleryInput?.addEventListener('change', () => {
  galleryPreview.innerHTML = '';
  if (!galleryInput.files) return;
  Array.from(galleryInput.files).forEach(f => {
    const url = URL.createObjectURL(f);
    const img = document.createElement('img');
    img.src = url;
    img.className = 'preview-img';
    galleryPreview.appendChild(img);
  });
});

// التصوير المباشر
let stream = null;
const openCamBtn   = document.getElementById('openCamBtn');
const captureBtn   = document.getElementById('captureBtn');
const closeCamBtn  = document.getElementById('closeCamBtn');
const camVideo     = document.getElementById('camStream');
const camCanvas    = document.getElementById('camCanvas');
const camStatus    = document.getElementById('camStatus');
const hiddenB64    = document.getElementById('mainCameraImage');
const capturedPrev = document.getElementById('capturedImagePreview');

async function openCamera(){
  camStatus.textContent = '';
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } }, audio:false
    });
    camVideo.srcObject = stream;
    camVideo.classList.remove('d-none');
    captureBtn.classList.remove('d-none');
    closeCamBtn.classList.remove('d-none');
    openCamBtn.classList.add('d-none');
    await camVideo.play();
  } catch (err) {
    camStatus.textContent = 'تعذر فتح الكاميرا: ' + (err?.message || err);
  }
}
function captureFrame(){
  if (!stream) return;
  const s = stream.getVideoTracks()[0].getSettings();
  const w = s.width || 800, h = s.height || 800;
  camCanvas.width = w; camCanvas.height = h;
  const ctx = camCanvas.getContext('2d');
  ctx.drawImage(camVideo, 0, 0, w, h);
  const dataURL = camCanvas.toDataURL('image/jpeg', 0.9);
  hiddenB64.value = dataURL;
  capturedPrev.src = dataURL;
  capturedPrev.classList.remove('d-none');
  camStatus.textContent = 'تم التقاط الصورة، ستُستخدم كصورة رئيسية.';
  mainPreview.classList.add('d-none');
}
function closeCamera(){
  if (stream) { stream.getTracks().forEach(t=>t.stop()); stream=null; }
  camVideo.classList.add('d-none');
  captureBtn.classList.add('d-none');
  closeCamBtn.classList.add('d-none');
  openCamBtn.classList.remove('d-none');
  camStatus.textContent = 'تم إغلاق الكاميرا.';
}
openCamBtn?.addEventListener('click', openCamera);
captureBtn?.addEventListener('click', captureFrame);
closeCamBtn?.addEventListener('click', closeCamera);
</script>
</body>
</html>