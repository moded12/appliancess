<?php
// المسار: admin/dashboard.php

require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

// دالة تعقيم بديلة إذا لم تكن موجودة
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

// إحصاءات خفيفة وفورية (بدون رسوم)
$counts = [
  'products'   => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
  'orders'     => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
  'customers'  => (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
  'categories' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
];

// خيار لتعطيل الرسوم تمامًا عبر الرابط ?charts=0
$chartsEnabled = !isset($_GET['charts']) || $_GET['charts'] !== '0';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>لوحة تحكم الأدمن</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#f8fafc;}
    .stat-card{transition:.2s; border:1px solid #e2e8f0; background:#fff;}
    .stat-card:hover{box-shadow:0 2px 10px rgba(0,0,0,0.07);}
    .section-title{font-size:1.05rem;font-weight:600;}
    .skeleton{background:linear-gradient(90deg,#eee,#f5f5f5,#eee);background-size:200% 100%;animation:sh 1.2s infinite;}
    @keyframes sh{0%{background-position:200% 0}100%{background-position:-200% 0}}
    .minh-180{min-height:180px;}
  </style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-2">لوحة تحكم الأدمن</h1>
    <!-- أزرار سريعة وواضحة -->
    <div class="d-flex gap-2">
      <a href="categories.php" class="btn btn-outline-info rounded-pill"><i class="bi bi-tags"></i> إدارة الأقسام</a>
      <a href="product_add.php" class="btn btn-outline-success rounded-pill"><i class="bi bi-plus-circle"></i> إضافة منتج جديد</a>
      <a href="products.php" class="btn btn-outline-dark rounded-pill"><i class="bi bi-box"></i> إدارة المنتجات</a>
    </div>
  </div>

  <!-- بطاقات الإحصاءات -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="p-3 stat-card rounded text-center">
        <div class="text-primary mb-1"><i class="bi bi-box-seam fs-3"></i></div>
        <div class="small text-muted">عدد المنتجات</div>
        <div class="fs-3 fw-bold"><?= $counts['products'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="p-3 stat-card rounded text-center">
        <div class="text-info mb-1"><i class="bi bi-tags fs-3"></i></div>
        <div class="small text-muted">عدد الأقسام</div>
        <div class="fs-3 fw-bold"><?= $counts['categories'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="p-3 stat-card rounded text-center">
        <div class="text-success mb-1"><i class="bi bi-receipt fs-3"></i></div>
        <div class="small text-muted">عدد الطلبات</div>
        <div class="fs-3 fw-bold"><?= $counts['orders'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="p-3 stat-card rounded text-center">
        <div class="text-warning mb-1"><i class="bi bi-people fs-3"></i></div>
        <div class="small text-muted">عدد الزبائن</div>
        <div class="fs-3 fw-bold"><?= $counts['customers'] ?></div>
      </div>
    </div>
  </div>

  <!-- الرسوم البيانية: تصبح داخل Collapse وبتحميل كسول -->
  <?php if ($chartsEnabled): ?>
  <div class="card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <div class="section-title"><i class="bi bi-graph-up"></i> التحليلات</div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="?charts=0" title="تعطيل الرسوم">تعطيل الرسوم</a>
        <button class="btn btn-sm btn-primary rounded-pill" data-bs-toggle="collapse" data-bs-target="#chartsBox">
          عرض/إخفاء الرسوم
        </button>
      </div>
    </div>
    <div id="chartsBox" class="collapse">
      <div class="row g-3 mt-2">
        <div class="col-lg-8">
          <div class="rounded border p-2 minh-180">
            <canvas id="revChart" class="w-100" height="120"></canvas>
            <div id="revSkeleton" class="skeleton rounded w-100 h-100 position-absolute top-0 start-0" style="display:none;"></div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="rounded border p-2 minh-180">
            <canvas id="catChart" class="w-100" height="120"></canvas>
            <div id="catSkeleton" class="skeleton rounded w-100 h-100 position-absolute top-0 start-0" style="display:none;"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="small text-muted mt-2">لن يتم تحميل البيانات إلا عند فتح القسم أعلاه لتسريع الصفحة.</div>
  </div>
  <?php else: ?>
    <div class="alert alert-info">تم تعطيل الرسوم عبر الرابط. لإعادة التفعيل أزل ?charts=0 من العنوان.</div>
  <?php endif; ?>

  <!-- روابط إضافية -->
  <div class="d-flex flex-wrap gap-2">
    <a href="orders.php" class="btn btn-outline-primary rounded-pill"><i class="bi bi-bag"></i> الطلبات</a>
    <a href="customers.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-person"></i> الزبائن</a>
    <a href="logout.php" class="btn btn-outline-danger rounded-pill"><i class="bi bi-box-arrow-right"></i> تسجيل الخروج</a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
<?php if ($chartsEnabled): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const chartsBox = document.getElementById('chartsBox');
  let chartsLoaded = false;

  chartsBox?.addEventListener('shown.bs.collapse', async () => {
    if (chartsLoaded) return;
    chartsLoaded = true;

    const revSk = document.getElementById('revSkeleton');
    const catSk = document.getElementById('catSkeleton');
    revSk && (revSk.style.display = 'block');
    catSk && (catSk.style.display = 'block');

    try {
      const res = await fetch('api_dashboard.php'); // يجلب بيانات جاهزة من الـ API
      const json = await res.json();

      // إيرادات آخر 6 أشهر
      const ctx1 = document.getElementById('revChart').getContext('2d');
      new Chart(ctx1, {
        type: 'line',
        data: {
          labels: json.months,
          datasets: [{
            label: 'الإيرادات ($)',
            data: json.revenues,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.15)',
            tension: .3,
            fill: true,
            borderWidth: 2,
            pointRadius: 3
          }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
      });

      // توزيع المنتجات حسب الأقسام
      const ctx2 = document.getElementById('catChart').getContext('2d');
      new Chart(ctx2, {
        type: 'doughnut',
        data: {
          labels: json.category_names,
          datasets: [{ data: json.category_counts, backgroundColor: ['#1e40af','#16a34a','#f59e0b','#dc2626','#6d28d9','#0ea5e9','#8b5cf6','#10b981','#f97316'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
      });

    } catch (e) {
      console.error(e);
      alert('تعذر تحميل التحليلات حالياً.');
    } finally {
      revSk && (revSk.style.display = 'none');
      catSk && (catSk.style.display = 'none');
    }
  });
});
</script>
<?php endif; ?>
</body>
</html>