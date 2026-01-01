<?php
// المسار: admin/product_delete.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

// للديباغ لو احتجت: إلغاء التعليق مؤقتًا
// ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: products.php?status=invalid_method');
  exit;
}

if (!function_exists('e')) { function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }

if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'])) {
  header('Location: products.php?status=csrf_fail');
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: products.php?status=bad_id');
  exit;
}

// تأكد من وجود المنتج
$st = $pdo->prepare('SELECT id, main_image FROM products WHERE id=:id LIMIT 1');
$st->execute([':id'=>$id]);
$product = $st->fetch(PDO::FETCH_ASSOC);
if (!$product) {
  header('Location: products.php?status=not_found');
  exit;
}

try {
  $pdo->beginTransaction();

  // جلب الوسائط المرتبطة لحذف ملفاتها لاحقًا
  $ms = $pdo->prepare('SELECT id, media_type, file FROM product_media WHERE product_id=:p');
  $ms->execute([':p'=>$id]);
  $media = $ms->fetchAll(PDO::FETCH_ASSOC);

  // حذف الوسائط من الجدول
  $pdo->prepare('DELETE FROM product_media WHERE product_id=:p')->execute([':p'=>$id]);

  // حذف بنود الطلبات المرتبطة (اختياري)
  $pdo->prepare('DELETE FROM order_items WHERE product_id=:p')->execute([':p'=>$id]);

  // حذف المنتج نفسه
  $pdo->prepare('DELETE FROM products WHERE id=:id')->execute([':id'=>$id]);

  $pdo->commit();

  // حذف الملفات الفعلية من المجلد بعد نجاح المعاملة
  $paths = [];
  if (!empty($product['main_image']) && !preg_match('~^https?://~', $product['main_image'])) {
    $paths[] = $product['main_image'];
  }
  foreach ($media as $m) {
    if ($m['file'] && !preg_match('~^https?://~', $m['file'])) {
      $paths[] = $m['file'];
    }
  }

  foreach ($paths as $rel) {
    // قد يكون المسار قديم (/uploads/...) أو /public/uploads/...
    if (str_starts_with($rel, '/uploads/')) {
      $rel = '/public' . $rel;
    }
    $full = realpath(__DIR__ . '/../' . ltrim($rel, '/'));
    if ($full && is_file($full)) {
      @unlink($full);
    }
  }

  header('Location: products.php?status=deleted');
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  @file_put_contents(__DIR__.'/../includes/log_delete_errors.log',
    date('Y-m-d H:i:s').' - Delete product '.$id.' - '.$e->getMessage().PHP_EOL,
    FILE_APPEND
  );
  header('Location: products.php?status=error');
  exit;
}