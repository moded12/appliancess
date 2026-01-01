<?php
// المسار: admin/product_view.php
// مهمة الملف: إعادة توجيه آمن إلى صفحة عرض المنتج في الواجهة العامة

declare(strict_types=1);

// تحذير مهم: احفظ هذا الملف UTF-8 بدون BOM ولا تضف أي مسافات أو أسطر قبل هذا السطر.

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// اجلب base_url من config.php بدون تحميل أية ملفات أخرى قد تُخرج نصوص
$configFile = dirname(__DIR__) . '/config.php';
$baseUrl = '';
if (is_file($configFile)) {
    $cfg = require $configFile; // يجب أن يُرجِع مصفوفة فقط
    $baseUrl = isset($cfg['base_url']) ? rtrim($cfg['base_url'], '/') : '';
}

// مسار الوجهة
if ($id > 0 && $baseUrl !== '') {
    $target = $baseUrl . '/public/product.php?id=' . $id;
} elseif ($baseUrl !== '') {
    $target = $baseUrl . '/public/index.php';
} else {
    // fallback إذا تعذّر قراءة الإعدادات
    $target = '../public/product.php?id=' . $id;
}

// إرسال التوجيه مع منع الكاش
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $target, true, 302);
    exit;
}

// في حال أُرسِلت رؤوس مسبقاً (BOM أو غيره) نستعمل ميتا-ريفريش كبديل
$esc = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta http-equiv="refresh" content="0;url=<?= $esc ?>">
  <title>عرض المنتج</title>
</head>
<body>
  <p>جارٍ تحويلك لعرض المنتج… إن لم يتحول تلقائياً <a href="<?= $esc ?>">اضغط هنا</a>.</p>
</body>
</html>