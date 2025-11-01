<?php
$title='';
include __DIR__.'/../includes/header.php';

$perPage=12; $page=max(1,(int)($_GET['page']??1)); $offset=($page-1)*$perPage; $params=[];
$sql='SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE 1=1';
$cnt='SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE 1=1';
if(!empty($_GET['cat'])){ $sql.=' AND c.slug=:slug'; $cnt.=' AND c.slug=:slug'; $params[':slug']=$_GET['cat']; }
if(!empty($_GET['q'])){ $sql.=' AND (p.name LIKE :q OR p.description LIKE :q)'; $cnt.=' AND (p.name LIKE :q OR p.description LIKE :q)'; $params[':q']='%'.$_GET['q'].'%'; }
$sql.=' ORDER BY p.id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset;
$stmt=$pdo->prepare($sql); foreach($params as $k=>$v){ $stmt->bindValue($k,$v); } $stmt->execute(); $products=$stmt->fetchAll();
$total=$pdo->prepare($cnt); foreach($params as $k=>$v){ $total->bindValue($k,$v); } $total->execute(); $totalRows=(int)$total->fetchColumn(); $totalPages=max(1,(int)ceil($totalRows/$perPage));
$base=rtrim($config['base_url'],'/');
?>

<section class="hero mb-5">
  <div class="hero-bg"></div>
  <div class="container hero-content text-center text-lg-start">
    <div class="row align-items-center justify-content-center">
      <div class="col-lg-10 mx-auto">
        <span class="badge-soft d-inline-block mb-3">عروض الأجهزة المنزلية</span>
        <h1 class="display-5 fw-bold mb-2">اختَر جهازك المثالي بسهولة</h1>
        <p class="lead mb-4">تصفّح أحدث الثلاجات والغسالات والمايكرويف والمكيفات بأسعار منافسة وتصميم أنيق.</p>
        <a class="btn btn-brand btn-lg rounded-pill" href="#grid"><i class="bi bi-bag me-1"></i> تَسوّق الآن</a>
      </div>
    </div>
  </div>

  <!-- شريط الأقسام داخل البانر -->
  <div class="cat-wrap">
    <div class="cat-tray" id="catTray" dir="rtl">
      <?php foreach($cats as $c): ?>
        <a class="chip" href="<?= $base ?>/index.php?cat=<?= e($c['slug']) ?>">
          <i class="bi bi-grid"></i> <?= e($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <svg class="hero-wave" viewBox="0 0 1440 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path fill="#ffffff" d="M0,64L80,74.7C160,85,320,107,480,101.3C640,96,800,64,960,53.3C1120,43,1280,53,1360,58.7L1440,64L1440,0L0,0Z"></path>
  </svg>
</section>


<div id="grid" class="row g-3">
  <?php foreach($products as $p): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="card product-card h-100 shadow-sm fade-in">
        <img src="<?= $p['main_image'] ? $base.'/assets/uploads/'.e($p['main_image']) : 'https://placehold.co/600x400?text=No+Image' ?>" class="card-img-top" alt="<?= e($p['name']) ?>">
        <div class="card-body d-flex flex-column">
          <a class="stretched-link text-decoration-none" href="<?= $base ?>/product.php?id=<?= (int)$p['id'] ?>"><h2 class="h6 card-title mb-1"><?= e($p['name']) ?></h2></a>
          <div class="text-muted small mb-2"><?= e($p['category_name'] ?? 'غير مصنف') ?></div>
          <div class="mt-auto d-flex justify-content-between align-items-center">
            <span class="price">$<?= number_format((float)$p['price'],2) ?></span>
<a class="btn btn-sm btn-brand rounded-pill px-3" href="<?= $base ?>/product.php?id=<?= (int)$p['id'] ?>">
  تفاصيل
</a>

          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<nav aria-label="pagination" class="mt-4">
  <ul class="pagination justify-content-center">
    <?php for($i=1;$i<=$totalPages;$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>">
        <a class="page-link" href="<?= $base ?>/index.php?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>

<?php include __DIR__.'/../includes/footer.php'; ?>
