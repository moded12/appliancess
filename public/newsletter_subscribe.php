<?php
// المسار: public/newsletter_subscribe.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
  exit;
}

$email = trim((string)($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'بريد إلكتروني غير صالح']);
  exit;
}

$store = __DIR__ . '/../includes/newsletter_subscribers.csv';
$line = [date('c'), $email, $_SERVER['REMOTE_ADDR'] ?? ''];
$csvLine = implode(',', array_map(function($v){ return '"'.str_replace('"','""',$v).'"'; }, $line)).PHP_EOL;
if (!file_exists(dirname($store))) @mkdir(dirname($store), 0755, true);
file_put_contents($store, $csvLine, FILE_APPEND | LOCK_EX);

echo json_encode(['ok'=>true]);