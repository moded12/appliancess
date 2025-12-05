<?php
// المسار: admin/slider_delete.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD']!=='POST' || !csrf_check($_POST['csrf'] ?? '')) {
  header('Location: slider.php?err=csrf');
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: slider.php?err=bad'); exit; }

try {
  $pdo->prepare('DELETE FROM homepage_slider WHERE id=:id')->execute([':id'=>$id]);
  header('Location: slider.php?msg=deleted');
} catch (Throwable $e) {
  header('Location: slider.php?err=1');
}