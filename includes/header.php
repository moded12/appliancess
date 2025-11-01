<?php require_once __DIR__.'/db.php'; require_once __DIR__.'/functions.php'; require_once __DIR__.'/cart.php';
$base = rtrim($config['base_url'], '/');
$cats = $pdo->query('SELECT id,name,slug FROM categories ORDER BY name')->fetchAll();
$tot  = cart_totals();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') ? $title.' | '.$config['app_name'] : $config['app_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= $base ?>/assets/app.css" rel="stylesheet">

<style>
  /* ---- Header layout and responsive adjustments ----
     Goal:
     - Keep PHP/HTML logic unchanged.
     - Make Arabic text visible and nicely positioned on small devices (not hidden).
     - Keep AL AJOURI visible on larger screens, but avoid overlap with hamburger.
     - Maintain clean centered vertical alignment on all sizes.
  */

  /* Base container behaves as flex row with alignment */
  .navbar .container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  /* Brand block (logo + subtitle) - allow it to wrap on small screens */
  .brand-block {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: nowrap;
    min-width: 0; /* allow shrinking inside flex */
  }

  .logo-aj {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    font-size: 1.05rem;
    letter-spacing: 0.2px;
    white-space: nowrap;
  }
  .logo-aj span { font-weight: 600; }

  /* Subtitle (Arabic) placed next to/under the brand depending on width */
  .brand-subtitle {
    color: #6c757d;
    font-size: 0.95rem;
    line-height: 1;
    margin: 0;
    padding: 0;
    white-space: nowrap;
  }

  /* English small brand on the right visually (for RTL it goes before logo in DOM)
     We'll hide it on very small screens or reduce size depending on available space. */
  .brand-english {
    font-weight: 700;
    letter-spacing: 0.6px;
    white-space: nowrap;
    margin-inline-start: 0.5rem;
    margin-inline-end: 0.5rem;
  }

  /* Ensure navbar toggler (hamburger) doesn't overlap the subtitle:
     We'll keep toggler order after main brand/content so it appears on the left visually
     but below/after in DOM ordering to avoid overlap. */
  .navbar-toggler {
    z-index: 4;
  }

  /* Collapse area should take remaining space */
  .navbar-collapse {
    flex: 1 1 auto;
  }

  /* On small screens we allow the brand block to wrap:
     - Subtitle moves under the main brand (stacked)
     - English shortens (smaller font) or optionally hidden if space very tight
  */
  @media (max-width: 767.98px) {
    .brand-block {
      flex-direction: column;
      align-items: flex-start; /* put subtitle under brand, aligned to start (right in RTL) */
      gap: 0.125rem;
      min-width: 0;
    }
    .brand-subtitle {
      font-size: 0.9rem;
      white-space: normal; /* allow wrapping if needed */
    }
    .brand-english {
      font-size: 0.9rem;
      opacity: 0.95;
      /* keep it visible but small; if you prefer hide it, set display:none; */
    }

    /* Move toggler visually to be left-most element to match RTL expectations
       but ensure it does not overlap brand due to stacking above. */
    .navbar .container { justify-content: space-between; }
    .navbar-toggler { order: 1; }
    .brand-right-wrapper { order: 2; display:flex; align-items:center; gap:.5rem; }
    .navbar-brand { order: 3; }
    .brand-left-wrapper { order: 4; }
  }

  /* On medium screens, keep single-line layout but reduce sizes */
  @media (min-width: 768px) and (max-width: 991.98px) {
    .brand-subtitle { font-size: 0.95rem; }
    .brand-english { font-size: 0.95rem; }
  }

  /* On large screens keep full sizes */
  @media (min-width: 992px) {
    .brand-block { flex-direction: row; align-items: center; }
    .brand-subtitle { font-size: 0.95rem; }
    .brand-english { font-size: 1rem; }
  }

  /* Minor tweaks for search input on small devices */
  .form-control[type="search"] { width: 240px; min-width: 120px; }
  @media (max-width: 575.98px) {
    .form-control[type="search"] { width: 140px; }
  }

  /* Keep dropdown items vertically centered */
  .navbar-nav { align-items: center; }

  /* Optional: a subtle visual separation so subtitle looks harmonious */
  .brand-subtitle-muted { color: #6c757d; font-size: .95rem; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
  <div class="container">
    <!-- For RTL we want AL AJOURI visually on the right side.
         Place it in a wrapper so we can reorder safely on small screens if needed. -->
    <div class="brand-right-wrapper d-flex align-items-center">
      <div class="brand-english">AL AJOURI</div>
    </div>

    <!-- Main brand block: logo + subtitle.
         On small screens the subtitle will wrap under the logo (visible). -->
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2 brand-block" href="<?= $base ?>/index.php">
      <span class="logo-aj"><b>AJOURI</b> <span>AL</span></span>
      <!-- Subtitle kept inside brand to avoid overlap with hamburger -->
      <small class="brand-subtitle brand-subtitle-muted">العجوري للأجهزة المنزلية</small>
    </a>

    <!-- Keep a left wrapper for spacing consistency (if you need extra left-side content) -->
    <div class="brand-left-wrapper d-none d-lg-flex align-items-center"></div>

    <!-- Hamburger toggler: placed after the brand in DOM but styled to avoid overlapping -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="تبديل التنقل">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/index.php">الرئيسية</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">الأقسام</a>
          <ul class="dropdown-menu shadow"><?php foreach($cats as $c): ?><li><a class="dropdown-item" href="<?= $base ?>/index.php?cat=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?></ul>
        </li>
      </ul>
      <a class="btn btn-outline-secondary me-2 rounded-pill position-relative" href="<?= $base ?>/cart.php">
        <i class="bi bi-cart"></i> السلة
        <?php if ($tot['count']>0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success"><?= (int)$tot['count'] ?></span><?php endif; ?>
      </a>
      <form class="d-flex" method="get" action="<?= $base ?>/index.php">
        <input class="form-control" type="search" name="q" placeholder="ابحث عن منتج">
        <button class="btn btn-brand ms-2">بحث</button>
      </form>
    </div>
  </div>
</nav>
<div class="container my-4">