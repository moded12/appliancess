<?php
// المسار: admin/product_feature_toggle.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'])) {
    header('Location: product_edit.php?id=' . (int)($_POST['id'] ?? 0) . '&msg=csrf');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: product_edit.php?id=' . $id . '&msg=bad_id');
    exit;
}

try {
    $st = $pdo->prepare('SELECT is_featured FROM products WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { header('Location: product_edit.php?id=' . $id . '&msg=not_found'); exit; }
    $cur = (int)$row['is_featured'];
    $new = $cur ? 0 : 1;
    $up = $pdo->prepare('UPDATE products SET is_featured = :new WHERE id = :id');
    $up->execute([':new' => $new, ':id' => $id]);

    header('Location: product_edit.php?id=' . $id . '&toggled=' . $new);
    exit;
} catch (Throwable $e) {
    @file_put_contents(__DIR__.'/../includes/log_feature_toggle.log', date('c').' - Toggle product '.$id.' - '.$e->getMessage().PHP_EOL, FILE_APPEND);
    header('Location: product_edit.php?id=' . $id . '&msg=error');
    exit;
}