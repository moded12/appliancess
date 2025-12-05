<?php
// المسار: public/product.php
// عرض منتج: الصورة الرئيسية أو أول صورة من المعرض كبديل

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
    $amount = number_format($converted,2);
    $symbol = $symbols[$selected] ?? $selected;
    $pos = $config['currency_position'] ?? 'after';
    return $pos==='after' ? "$amount $symbol" : "$symbol $amount";
  }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$product = null;
try {
  $st = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=:id LIMIT 1');
  $st->execute([':id'=>$id]);
  $product = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
if (!$product) { header('Location: index.php'); exit; }

try {
  $pdo->prepare('UPDATE products SET views = COALESCE(views,0)+1 WHERE id=:id')->execute([':id'=>$id]);
} catch (Throwable $e) {}

$media = [];
try {
  $med = $pdo->prepare('SELECT * FROM product_media WHERE product_id=:p ORDER BY sort_order,id');
  $med->execute([':p'=>$id]);
  $media = $med->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$title = $product['name'];
include __DIR__ . '/partials/shop_header.php';

/* اختيار الصورة الرئيسية أو أول صورة من المعرض */
$mainImg = $product['main_image'];
if (!$mainImg) {
  foreach ($media as $m) {
    if ($m['media_type'] === 'image') {
      $mainImg = $m['file'];
      break;
    }
  }
}
$mainImg = $mainImg ? asset_url($mainImg) : 'https://via.placeholder.com/800x800?text=No+Image';
$stock   = (int)($product['stock'] ?? 0);
?>
<div class="product-container">
  <div class="prod-grid">

    <!-- تفاصيل (اجعلها يسار الصورة لتظهر الصورة) -->
    <div>
      <h1 class="prod-title"><?= e($product['name']) ?></h1>
      <div class="prod-meta">
        <?= e($product['category_name'] ?? 'غير مصنف') ?> | مشاهدات: <?= (int)($product['views'] ?? 0) ?> | المخزون: <?= $stock ?>
      </div>
      <div class="price-box">
        <div>
          <div class="the-price"><?= price((float)$product['price']) ?></div>
          <div class="stock">المخزون المتوفر: <?= $stock ?></div>
        </div>
        <form method="post" action="<?= $base ?>/public/cart.php" class="add-to-cart-form d-flex align-items-end gap-2">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <div>
              <label class="form-label small mb-1">الكمية</label>
              <input type="number" name="qty" value="1" min="1" class="form-control qty-input">
            </div>
            <button class="btn btn-success rounded-pill" style="min-width:130px;">
              <i class="bi bi-cart-plus"></i> أضف للسلة
            </button>
        </form>
      </div>
      <div class="description-card">
        <h2 class="h6 fw-bold mb-2">الوصف</h2>
        <div class="text-body"><?= nl2br(e($product['description'] ?? 'لا يوجد وصف')) ?></div>
      </div>
      <div class="mt-4 d-flex flex-wrap gap-2">
        <a href="index.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-right"></i> رجوع للمتجر</a>
        <a href="cart.php" class="btn btn-primary rounded-pill"><i class="bi bi-cart"></i> السلة</a>
      </div>
    </div>

    <!-- الصورة + مصغرات -->
    <div class="prod-image-box">
      <div class="main-img-wrapper">
        <img src="<?= e($mainImg) ?>" alt="<?= e($product['name']) ?>">
        <?php if (!empty($product['is_featured'])): ?>
          <span class="badge-featured" style="top:12px;left:12px;">مميز</span>
        <?php endif; ?>
        <?php if ((int)($product['views'] ?? 0) >= ($config['views_badge_threshold'] ?? 5)): ?>
          <span class="badge-view" style="top:12px; right:12px; left:auto;">الأكثر مشاهدة</span>
        <?php endif; ?>
      </div>
      <?php if ($media): ?>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <?php foreach ($media as $m): ?>
            <?php if ($m['media_type']==='image'):
              $img = asset_url($m['file']); ?>
              <img src="<?= e($img) ?>" class="rounded border" style="height:70px;width:70px;object-fit:cover;cursor:pointer"
                   onclick="document.querySelector('.main-img-wrapper img').src=this.src;" alt="">
            <?php elseif ($m['media_type']==='video'):
              $file = $m['file'];
              if (preg_match('~youtube\.com|youtu\.be~i',$file)):
                parse_str(parse_url($file, PHP_URL_QUERY) ?? '', $qv);
                $vid = $qv['v'] ?? '';
                if (!$vid && preg_match('~youtu\.be/([^?&/]+)~',$file,$mm)) $vid = $mm[1];
                if ($vid): ?>
                  <iframe width="110" height="70" src="https://www.youtube.com/embed/<?= e($vid) ?>" frameborder="0" allowfullscreen class="rounded border"></iframe>
                <?php else: ?>
                  <a href="<?= e($file) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">فيديو</a>
                <?php endif; ?>
              <?php else:
                $vsrc = asset_url($file); ?>
                <video src="<?= e($vsrc) ?>" controls style="height:70px;width:110px;object-fit:cover" class="rounded border"></video>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- شريط شراء (موبايل) -->
<div class="mobile-buy-bar d-md-none">
  <div class="price-mobile"><?= price((float)$product['price']) ?></div>
  <form method="post" action="<?= $base ?>/public/cart.php" class="d-flex align-items-center gap-2 m-0">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
    <input type="hidden" name="qty" value="1">
    <button class="btn btn-success btn-sm rounded-pill">
      <i class="bi bi-cart-plus"></i> أضف
    </button>
  </form>
</div>

<?php include __DIR__ . '/partials/shop_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>