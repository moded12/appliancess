<?php
// المسار: public/partials/shop_header.php

if (!isset($config)) { $config = require __DIR__ . '/../../config.php'; }
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$base = rtrim($config['base_url'], '/');
if (!function_exists('e')) { function e($v){ return htmlspecialchars($v, ENT_QUOTES,'UTF-8'); } }

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
    $rates   = $config['currency_rates']   ?? [$baseCurrency => 1];
    $symbols = $config['currency_symbols'] ?? [$baseCurrency => ($config['currency_symbol'] ?? 'د.أ')];
    $rate = $rates[$selected] ?? 1;
    $converted = $v * $rate;
    $amount = number_format($converted,2);
    $symbol = $symbols[$selected] ?? $selected;
    $pos = $config['currency_position'] ?? 'after';
    return $pos==='after' ? "$amount $symbol" : "$symbol $amount";
  }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title><?= isset($title)? e($title).' - ' : '' ?><?= e($config['app_name'] ?? 'العجوري للأجهزة المنزلية') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- خطوط: Cairo + Tajawal -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Theme -->
  <link href="<?= e($base) ?>/public/assets/css/theme.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom px-2" style="box-shadow:0 2px 6px rgba(0,0,0,.05);">
  <div class="container-fluid" style="max-width:1280px;">
    <a class="navbar-brand fw-bold" href="<?= $base ?>/public/index.php"><?= e($config['app_name'] ?? '') ?></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav">
      <span class="bi bi-list" style="font-size:28px;"></span>
    </button>
    <div class="collapse navbar-collapse d-none d-lg-flex">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='index.php'?'active':'') ?>" href="<?= $base ?>/public/index.php">المنتجات</a></li>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <a href="<?= $base ?>/public/cart.php" class="btn btn-outline-primary btn-sm btn-pill">
          <i class="bi bi-cart"></i> السلة
        </a>
      </div>
    </div>
  </div>
</nav>

<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNav">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><?= e($config['app_name'] ?? '') ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="إغلاق"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column gap-3">
    <a href="<?= $base ?>/public/index.php" class="nav-link"><i class="bi bi-grid"></i> المنتجات</a>
    <a href="<?= $base ?>/public/cart.php" class="nav-link"><i class="bi bi-cart"></i> السلة</a>
    <div class="mt-auto small text-muted">© <?= date('Y') ?> <?= e($config['app_name'] ?? '') ?></div>
  </div>
</div>

<main class="py-4" style="max-width:1280px;margin:auto;">