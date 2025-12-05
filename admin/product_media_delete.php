<?php
// المسار: admin/product_media_delete.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
  http_response_code(400);
  echo 'Bad Request';
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);

if ($id <= 0 || $product_id <= 0) {
  header('Location: products.php');
  exit;
}

$st = $pdo->prepare('SELECT * FROM product_media WHERE id=:id AND product_id=:pid');
$st->execute([':id'=>$id, ':pid'=>$product_id]);
$media = $st->fetch();

if ($media) {
  // حاول حذف الملف الفعلي إن كان داخل /uploads/
  if ($media['file'] && !preg_match('~^https?://~', $media['file'])) {
    $path = __DIR__ . '/../public' . $media['file']; // media['file'] مثل /uploads/xxx.jpg
    if (is_file($path)) @unlink($path);
  }
  $pdo->prepare('DELETE FROM product_media WHERE id=:id')->execute([':id'=>$id]);
}

header('Location: product_edit.php?id=' . $product_id);
exit;