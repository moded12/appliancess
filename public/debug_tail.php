<?php
// مؤقت: اعرض آخر N سطر من ملف اللوق payments_debug.log
// ضع الملف في /public ثم ادخل: https://your-domain/xx/public/debug_tail.php
// احذف الملف بعد الحصول على الناتج (لأسباب أمنية).

$log = __DIR__ . '/../storage/logs/payments_debug.log';
if (!file_exists($log)) {
    echo "Log not found: " . htmlspecialchars($log);
    exit;
}

$lines = 200; // عدد الأسطر التي تريد إظهارها
$fp = fopen($log, 'r');
if (!$fp) { echo "Cannot open log file."; exit; }

$pos = -2; $data = ''; $lineCount = 0;
fseek($fp, 0, SEEK_END);
$size = ftell($fp);

while ($lineCount < $lines && abs($pos) < $size) {
    fseek($fp, $pos, SEEK_END);
    $char = fgetc($fp);
    if ($char === "\n") $lineCount++;
    $data = $char . $data;
    $pos--;
}
fclose($fp);

echo "<pre>" . htmlspecialchars($data) . "</pre>";
?>