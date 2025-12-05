<?php
// المسار: public/index.php
// نسخة بدون سلايدر + إصلاح عرض الصور: إن كانت main_image فارغة نستخدم أول صورة من product_media كبديل

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../config.php';
$base   = rtrim($config['base_url'] ?? '', '/');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('asset_url')) {
  function asset_url(string $path): string {
    global $base;
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (str_starts_with($path, '/uploads/')) return $base . '/public' . $path;
    if (str_starts_with($path, '/public/'))  return $base . $path;
    if ($path[0] === '/') return $base . $path;
    return $base . '/' . ltrim($path, '/');
  }
}
if (!function_exists('price')) {
  function price(float $v): string {
    global $config;
    $baseCurrency = $config['currency_code'] ?? 'JOD';
    $selected = $_SESSION['currency'] ?? $baseCurrency;
    $rates = $config['currency_rates'] ?? [$baseCurrency => 1];
    $symbols = $config['currency_symbols'] ?? [$baseCurrency => ($config['currency_symbol'] ?? 'د.أ')];
    $rate = $rates[$selected] ?? 1;
    $converted = $v * $rate;
    $amount = number_format($converted, 2);
    $symbol = $symbols[$selected] ?? $selected;
    $pos = $config['currency_position'] ?? 'after';
    return $pos === 'after' ? "$amount $symbol" : "$symbol $amount";
  }
}

$catId   = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

/* الأقسام */
$cats = [];
try {
  $cats = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $cats = [];
}

/* الأكثر مشاهدة (للبطاقة الجانبية) */
$mostViewed = [];
try {
  $stmv = $pdo->prepare("SELECT id,name,price,main_image,views FROM products ORDER BY views DESC, id DESC LIMIT 8");
  $stmv->execute();
  $mostViewed = $stmv->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* المنتجات الرئيسة
   ملاحظة مهمة: نجلب أول صورة معرض (إن وجدت) عبر LEFT JOIN على product_media بانتقاء أقل id لصورة image لكل منتج
*/
$where  = ' WHERE 1';
$params = [];
if ($catId > 0) { $where .= ' AND p.category_id = :cat'; $params[':cat'] = $catId; }
if ($q !== '') { $where .= ' AND (p.name LIKE :q OR p.description LIKE :q)'; $params[':q'] = "%$q%"; }

$total = 0;
try {
  $countSql  = "SELECT COUNT(*) FROM products p $where";
  $countStmt = $pdo->prepare($countSql);
  $countStmt->execute($params);
  $total = (int)$countStmt->fetchColumn();
} catch (Throwable $e) {}

$pages = max(1, (int)ceil($total / $perPage));

$products = [];
try {
  $sql = "
    SELECT
      p.id,
      p.name,
      p.price,
      p.main_image,
      p.views,
      p.is_featured,
      c.name AS category_name,
      pm.file AS gallery_image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN (
      SELECT x.product_id, x.file
      FROM product_media x
      JOIN (
        SELECT product_id, MIN(id) AS min_id
        FROM product_media
        WHERE media_type='image'
        GROUP BY product_id
      ) m ON m.product_id = x.product_id AND m.min_id = x.id
      WHERE x.media_type='image'
    ) pm ON pm.product_id = p.id
    $where
    ORDER BY p.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k=>$v) { $st->bindValue($k,$v); }
  $st->bindValue(':lim',(int)$perPage,PDO::PARAM_INT);
  $st->bindValue(':off',(int)$offset,PDO::PARAM_INT);
  $st->execute();
  $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$viewsBadgeThreshold = (int)($config['views_badge_threshold'] ?? 5);
$title = 'المنتجات';
include __DIR__ . '/partials/shop_header.php';
?>

<!-- زر إظهار/إخفاء الشريط الجانبي للموبايل -->
<button class="toggle-sidebar-btn" id="toggleSidebarBtn" title="القائمة">
  <i class="bi bi-sliders"></i>
</button>

<!-- عنوان رئيسي -->
<div class="card-soft mb-4 text-center">
  <h2 class="h5 mb-1 fw-bold">مرحباً بك في <?= e($config['app_name'] ?? '') ?></h2>
  <p class="small text-muted mb-0">استعرض أحدث المنتجات. (تم تعطيل السلايدر مؤقتاً)</p>
</div>

<div class="page-grid">
  <!-- المنتجات -->
  <div class="products-col">
    <div class="top-toolbar">
      <div class="section-title m-0">
        <div class="sec-icon"><i class="bi bi-grid"></i></div>
        جميع المنتجات
      </div>
      <div class="result-count"><?= (int)$total ?> نتيجة</div>
    </div>

    <?php if (!$products): ?>
      <div class="card-soft text-center text-muted">لا توجد منتجات مطابقة حالياً.</div>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($products as $p):
          // اختَر الصورة: main_image أولاً، وإلا أول صورة معرض، وإلا Placeholder
          $src = $p['main_image'] ?: ($p['gallery_image'] ?? '');
          $img = $src ? asset_url($src) : 'https://via.placeholder.com/600x450?text=No+Image';
        ?>
          <div class="product-card">
            <div class="product-thumb">
              <a href="<?= $base ?>/public/product.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
              </a>
              <?php if (!empty($p['is_featured'])): ?>
                <span class="badge-featured">مميز</span>
              <?php endif; ?>
              <?php if ((int)($p['views'] ?? 0) >= $viewsBadgeThreshold): ?>
                <span class="badge-view">الأكثر مشاهدة</span>
              <?php endif; ?>
            </div>
            <a href="<?= $base ?>/public/product.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none">
              <div class="product-title" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></div>
            </a>
            <div class="product-cat"><?= e($p['category_name'] ?? 'غير مصنف') ?></div>
            <div class="price-row">
              <div class="product-price"><?= price((float)$p['price']) ?></div>
              <form method="post" action="<?= $base ?>/public/cart.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="qty" value="1">
                <button class="add-btn" title="أضف للسلة" aria-label="أضف المنتج للسلة">
                  <i class="bi bi-cart-plus"></i>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="mt-4" aria-label="صفحات">
          <ul class="pagination justify-content-center gap-1 flex-wrap">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET,['page'=>max(1,$page-1)]))) ?>" aria-label="السابق">&laquo;</a>
            </li>
            <?php
              $window = 3;
              $start = max(1, $page-$window);
              $end   = min($pages, $page+$window);
              for ($i=$start; $i<=$end; $i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET,['page'=>$i]))) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
              <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET,['page'=>min($pages,$page+1)]))) ?>" aria-label="التالي">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- الشريط الجانبي -->
  <aside class="sidebar-col">
    <div class="card-soft mb-3">
      <div class="sidebar-group-title">الفلاتر</div>
      <form method="get" id="filterForm">
        <label class="form-label small mb-1">القسم</label>
        <select name="cat" class="form-select form-select-sm mb-2" onchange="document.getElementById('filterForm').submit()">
          <option value="0">كل الأقسام</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $catId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small mb-1">بحث</label>
        <div class="input-group input-group-sm mb-2">
          <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="اسم أو وصف">
          <button class="btn btn-primary btn-small" type="submit" aria-label="بحث"><i class="bi bi-search"></i></button>
        </div>
      </form>
    </div>

    <div class="card-soft mb-3">
      <div class="sidebar-group-title d-flex justify-content-between align-items-center">
        <span>الأكثر مشاهدة</span>
        <i class="bi bi-eye"></i>
      </div>
      <?php if (!$mostViewed): ?>
        <div class="text-muted small">لا بيانات مشاهدة.</div>
      <?php else: ?>
        <div class="most-viewed-list">
          <?php foreach ($mostViewed as $mv):
            $mvSrc = $mv['main_image'] ?: '';
            $mvImg = $mvSrc ? asset_url($mvSrc) : 'https://via.placeholder.com/80x48';
          ?>
            <a href="<?= $base ?>/public/product.php?id=<?= (int)$mv['id'] ?>" class="mv-item">
              <img src="<?= e($mvImg) ?>" alt="" loading="lazy">
              <div class="small flex-grow-1">
                <div class="fw-semibold text-truncate" style="max-width:140px;"><?= e($mv['name']) ?></div>
                <div class="text-muted"><?= price((float)$mv['price']) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card-soft">
      <div class="sidebar-group-title">روابط سريعة</div>
      <div class="d-grid gap-2">
        <a href="<?= $base ?>/public/cart.php" class="btn btn-outline-primary btn-sm">عرض السلة</a>
        <a href="<?= $base ?>/admin/login.php" class="btn btn-outline-secondary btn-sm">لوحة الأدمن</a>
      </div>
    </div>
  </aside>
</div>

<?php include __DIR__ . '/partials/shop_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const toggleBtn = document.getElementById('toggleSidebarBtn');
  const sidebar  = document.querySelector('.sidebar-col');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  }
})();
</script>