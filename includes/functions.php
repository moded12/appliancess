<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$config = require __DIR__.'/../config.php';
$BASE = rtrim($config['base_url'], '/');

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function price($n){ return '$'.number_format((float)$n, 2); }
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_check($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }
