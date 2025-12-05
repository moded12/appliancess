<?php
// المسار: public/set_currency.php
session_start();
$config = require __DIR__ . '/../config.php';

// قائمة العملات المسموح بها
$allowed = array_keys($config['currency_rates'] ?? [$config['currency_code'] ?? 'JOD' => 1]);
$code = strtoupper(trim($_GET['c'] ?? ''));

$ref = $_SERVER['HTTP_REFERER'] ?? ($config['base_url'] . '/public/index.php');

if ($code && in_array($code, $allowed, true)) {
    $_SESSION['currency'] = $code;
}

header('Location: ' . $ref);
exit;