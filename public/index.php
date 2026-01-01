<?php
// المسار: public/index.php
// النسخة الكاملة + تطوير واجهة Amazon-like
// ⚠️ لم يتم حذف أي منطق أو ميزة

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../config.php';
$base   = rtrim($config['base_url'] ?? '', '/');
$public = $base . '/public';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* =======================
   Helpers
======================= */
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
    return ($config['currency_position'] ?? 'after') === 'after'
      ? "$amount $symbol"
      : "$symbol $amount";
  }
}

/* =======================
   Params
======================= */
$catId   = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

/* =======================
   Categories
======================= */
$cats = [];
try {
  $cats = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* =======================
   Most Viewed (Sidebar)
======================= */
$mostViewed = [];
try {
  $stmv = $pdo->query("
    SELECT id,name,price,main_image,views
    FROM products
    ORDER BY views DESC
    LIMIT 8
  ");
  $mostViewed = $stmv->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* =======================
   Products Query
======================= */
$where  = ' WHERE 1';
$params = [];
if ($catId > 0) { $where .= ' AND p.category_id = :cat'; $params[':cat'] = $catId; }
if ($q !== '') { $where .= ' AND (p.name LIKE :q OR p.description LIKE :q)'; $params[':q'] = "%$q%"; }

$total = 0;
try {
  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
  $countStmt->execute($params);
  $total = (int)$countStmt->fetchColumn();
} catch (Throwable $e) {}

$pages = max(1, (int)ceil($total / $perPage));

$products = [];
try {
  $sql = "
    SELECT
      p.id,p.name,p.price,p.main_image,p.views,p.is_featured,
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
      ) m ON m.product_id=x.product_id AND m.min_id=x.id
    ) pm ON pm.product_id=p.id
    $where
    ORDER BY p.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k=>$v) $st->bindValue($k,$v);
  $st->bindValue(':lim',$perPage,PDO::PARAM_INT);
  $st->bindValue(':off',$offset,PDO::PARAM_INT);
  $st->execute();
  $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$viewsBadgeThreshold = (int)($config['views_badge_threshold'] ?? 5);

/* =======================
   Header
======================= */
$page_title = 'الرئيسية';
include __DIR__ . '/partials/shop_header.php';
?>

<!-- ================= HERO ================= -->
<section class="home-hero container">
  <div class="hero-inner">
    <div>
      <h1 class="hero-title">تسوّق بذكاء. وفر أكثر.</h1>
      <p class="hero-sub">أفضل العروض والمنتجات المختارة يوميًا.</p>
      <div class="hero-actions">
        <a href="#products" class="btn btn-primary">ابدأ التسوق</a>
        <a href="<?= $public ?>/?featured=1" class="btn btn-outline-secondary">العروض</a>
      </div>
    </div>
    <div class="hero-card">
      <div class="hc-title">لماذا متجرنا؟</div>
      <div class="hc-row">
        <span class="hc-pill"><i class="bi bi-truck"></i> شحن سريع</span>
        <span class="hc-pill"><i class="bi bi-shield-check"></i> ضمان</span>
        <span class="hc-pill"><i class="bi bi-cash-coin"></i> دفع آمن</span>
      </div>
    </div>
  </div>
</section>

<!-- ================= CONTENT ================= -->
<div class="page-grid container mt-4" id="products">

  <!-- PRODUCTS -->
  <div class="products-col">

    <div class="top-toolbar">
      <div class="section-title m-0">
        <div class="sec-icon"><i class="bi bi-grid"></i></div>
        جميع المنتجات
      </div>
      <div class="result-count"><?= (int)$total ?> نتيجة</div>
    </div>

    <?php if (!$products): ?>
      <div class="card-soft text-center text-muted">لا توجد منتجات حالياً.</div>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($products as $p):
          $src = $p['main_image'] ?: ($p['gallery_image'] ?? '');
          $img = $src ? asset_url($src) : 'https://via.placeholder.com/600x450';
        ?>
          <div class="product-card">
            <div class="product-thumb">
              <a href="<?= $public ?>/product.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              </a>
              <?php if (!empty($p['is_featured'])): ?>
                <span class="badge-featured">مميز</span>
              <?php endif; ?>
              <?php if ((int)$p['views'] >= $viewsBadgeThreshold): ?>
                <span class="badge-view">الأكثر مشاهدة</span>
              <?php endif; ?>
            </div>

            <div class="product-title"><?= e($p['name']) ?></div>
            <div class="product-cat"><?= e($p['category_name'] ?? 'غير مصنف') ?></div>

            <div class="price-row">
              <div class="product-price"><?= price((float)$p['price']) ?></div>
              <form method="post" action="<?= $public ?>/cart.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="qty" value="1">
                <button class="add-btn" aria-label="أضف للسلة">
                  <i class="bi bi-cart-plus"></i>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <nav class="mt-4">
          <ul class="pagination justify-content-center gap-1 flex-wrap">
            <?php for ($i=1;$i<=$pages;$i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET,['page'=>$i]))) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- SIDEBAR -->
  <aside class="sidebar-col">
    <div class="card-soft mb-3">
      <div class="sidebar-group-title">الفلاتر</div>
      <form method="get">
        <select name="cat" class="form-select form-select-sm mb-2" onchange="this.form.submit()">
          <option value="0">كل الأقسام</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $catId===(int)$c['id']?'selected':'' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="بحث">
      </form>
    </div>

    <div class="card-soft">
      <div class="sidebar-group-title">الأكثر مشاهدة</div>
      <div class="most-viewed-list">
        <?php foreach ($mostViewed as $mv): ?>
          <a href="<?= $public ?>/product.php?id=<?= (int)$mv['id'] ?>" class="mv-item">
            <img src="<?= e(asset_url($mv['main_image'] ?? '')) ?>" alt="">
            <div>
              <div class="fw-semibold small"><?= e($mv['name']) ?></div>
              <div class="text-muted small"><?= price((float)$mv['price']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>

</div>

<?php include __DIR__ . '/partials/shop_footer.php'; ?>
