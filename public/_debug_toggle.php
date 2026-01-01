<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
echo "PHP: " . PHP_VERSION . "<br>";
echo "session.save_path: " . ini_get('session.save_path') . "<br>";
echo "headers_sent? " . (headers_sent() ? 'yes' : 'no') . "<br>";
session_start();
$_SESSION['__test__'] = 'ok';
echo "session started OK";