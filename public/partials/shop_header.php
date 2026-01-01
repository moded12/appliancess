<?php
// المسار: public/partials/shop_header.php

if (!isset($config)) { $config = require __DIR__ . '/../../config.php'; }
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$base   = rtrim($config['base_url'] ?? '', '/');          // مثال: https://www.shneler.com/xx
$public = $base . '/public';

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// عدّاد السلة (إذا عندك هيكل مختلف غيّره)
$cartCount = (int)($_SESSION['cart_count'] ?? 0);

// عنوان الصفحة (اختياري من كل صفحة)
$page_title = $page_title ?? 'المتجر الإلكتروني';

// قيمة البحث الحالية (مريحة لليوزر)
$q = $_GET['q'] ?? '';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title><?= e($page_title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap RTL + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Theme (✅ المسار الصحيح داخل /xx/public) -->
  <link rel="stylesheet" href="<?= e($public) ?>/assets/css/theme.css?v=100">
</head>
<body class="site-body">

<header class="amz-header" role="banner">

  <!-- Top strip (اختياري) -->
  <div class="amz-topstrip d-none d-lg-block">
    <div class="container amz-topstrip-row">
      <div class="amz-topstrip-left">
        <i class="bi bi-shield-check"></i>
        <span>ضمان وجودة</span>
        <span class="sep">•</span>
        <span>دعم سريع</span>
        <span class="sep">•</span>
        <span>عروض يومية</span>
      </div>
      <div class="amz-topstrip-right">
        <a href="<?= e($public) ?>/contact.php">اتصل بنا</a>
        <span class="sep">•</span>
        <a href="<?= e($public) ?>/index.php?featured=1">المميزة</a>
      </div>
    </div>
  </div>

  <!-- Main bar -->
  <div class="amz-mainbar">
    <div class="container amz-mainbar-row">

      <!-- Mobile menu button -->
      <button class="amz-iconbtn d-lg-none"
              type="button"
              data-bs-toggle="offcanvas"
              data-bs-target="#amzMobileMenu"
              aria-controls="amzMobileMenu"
              aria-label="فتح القائمة">
        <i class="bi bi-list"></i>
      </button>

      <!-- Logo -->
      <a class="amz-logo" href="<?= e($public) ?>/">
        <span class="brand">MyStore</span><span class="dot">.</span><span class="tld">shop</span>
      </a>

      <!-- Search (Desktop) -->
      <form class="amz-search d-none d-md-flex" method="get" action="<?= e($public) ?>/index.php" role="search">
        <div class="amz-search-wrap">
          <input class="form-control amz-search-input"
                 type="search"
                 name="q"
                 placeholder="ابحث عن منتج، ماركة، عرض..."
                 value="<?= e($q) ?>"
                 autocomplete="off">
          <button class="btn amz-search-btn" type="submit" aria-label="بحث">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </form>

      <!-- Actions -->
      <div class="amz-actions">

        <a class="amz-action" href="<?= e($public) ?>/account.php" title="حسابي">
          <i class="bi bi-person"></i>
          <span class="lbl d-none d-sm-inline">حسابي</span>
        </a>

        <a class="amz-action" href="<?= e($public) ?>/cart.php" title="السلة">
          <i class="bi bi-cart3"></i>
          <span class="lbl d-none d-sm-inline">السلة</span>
          <?php if ($cartCount > 0): ?>
            <span class="amz-badge"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>

      </div>
    </div>

    <!-- Search (Mobile) -->
    <div class="container d-md-none">
      <form class="amz-search-mobile" method="get" action="<?= e($public) ?>/index.php" role="search">
        <input class="form-control amz-search-input"
               type="search" name="q" placeholder="بحث سريع..."
               value="<?= e($q) ?>" autocomplete="off">
        <button class="btn amz-search-btn" type="submit" aria-label="بحث">
          <i class="bi bi-search"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- Nav bar (Amazon-like links row) -->
  <nav class="amz-navbar" role="navigation" aria-label="روابط">
    <div class="container amz-navrow">

      <button class="amz-allbtn"
              type="button"
              data-bs-toggle="offcanvas"
              data-bs-target="#amzMobileMenu"
              aria-controls="amzMobileMenu">
        <i class="bi bi-grid-3x3-gap"></i>
        <span>الكل</span>
      </button>

      <div class="amz-navlinks">
        <a href="<?= e($public) ?>/">الرئيسية</a>
        <a href="<?= e($public) ?>/index.php?featured=1">منتجات مميزة</a>
        <a href="<?= e($public) ?>/index.php?sort=views_desc">الأكثر مشاهدة</a>
        <a href="<?= e($public) ?>/index.php?sort=price_asc">الأرخص</a>
        <a href="<?= e($public) ?>/index.php?sort=price_desc">الأغلى</a>
      </div>

      <div class="amz-navright d-none d-lg-flex">
        <a class="amz-dealpill" href="<?= e($public) ?>/index.php?featured=1">
          <i class="bi bi-lightning-charge"></i> عروض اليوم
        </a>
      </div>
    </div>
  </nav>

</header>

<!-- Mobile Offcanvas Menu -->
<div class="offcanvas offcanvas-end amz-offcanvas" tabindex="-1" id="amzMobileMenu" aria-labelledby="amzMobileMenuLabel">
  <div class="offcanvas-header">
    <div>
      <div class="offcanvas-title" id="amzMobileMenuLabel">القائمة</div>
      <div class="amz-off-sub">تصفح سريع</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="إغلاق"></button>
  </div>

  <div class="offcanvas-body">

    <div class="amz-off-section">
      <a class="amz-off-link" href="<?= e($public) ?>/">الرئيسية</a>
      <a class="amz-off-link" href="<?= e($public) ?>/index.php?featured=1">منتجات مميزة</a>
      <a class="amz-off-link" href="<?= e($public) ?>/index.php?sort=views_desc">الأكثر مشاهدة</a>
      <a class="amz-off-link" href="<?= e($public) ?>/cart.php">السلة</a>
      <a class="amz-off-link" href="<?= e($public) ?>/account.php">حسابي</a>
      <a class="amz-off-link" href="<?= e($public) ?>/contact.php">اتصل بنا</a>
    </div>

    <hr class="amz-hr">

    <div class="amz-off-section">
      <div class="amz-off-title">تصفية بسرعة</div>
      <a class="amz-off-chip" href="<?= e($public) ?>/index.php?featured=1"><i class="bi bi-star-fill"></i> مميزة</a>
      <a class="amz-off-chip" href="<?= e($public) ?>/index.php?sort=price_asc"><i class="bi bi-arrow-down"></i> الأرخص</a>
      <a class="amz-off-chip" href="<?= e($public) ?>/index.php?sort=price_desc"><i class="bi bi-arrow-up"></i> الأغلى</a>
      <a class="amz-off-chip" href="<?= e($public) ?>/index.php?sort=views_desc"><i class="bi bi-eye"></i> الأكثر مشاهدة</a>
    </div>

  </div>
</div>
