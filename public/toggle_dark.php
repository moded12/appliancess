<?php
// المسار: public/toggle_dark.php
// تبديل وضع العرض (نهاري/ليلي) مع إعادة توجيه آمنة وسريعة.

// ملاحظة مهمة: احفظ هذا الملف بترميز UTF-8 "من دون BOM" وتأكد أن أول بايت في الملف هو "<" الخاص بـ <?php.
if (session_status() !== PHP_SESSION_ACTIVE) {
    // في بعض الاستضافات، فشل الجلسة قد يسبب 500. هذا يُقلل احتماله.
    ini_set('session.use_strict_mode', '1');
    @session_start();
} else {
    @session_regenerate_id(true);
}

// بدّل العلم في الجلسة
$_SESSION['dark'] = isset($_SESSION['dark']) ? !$_SESSION['dark'] : true;

// حدد وجهة آمنة لإعادة التوجيه.
// افتراضياً: index.php في نفس المجلد.
$redirect = 'index.php';

// إذا كان هناك Referer من نفس المضيف، استخدمه.
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref) {
    $refHost = parse_url($ref, PHP_URL_HOST);
    $srvHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost && $srvHost && strcasecmp($refHost, $srvHost) === 0) {
        $redirect = $ref;
    }
}

// حاول إرسال رؤوس إعادة التوجيه مع حجب الكاش
// استخدم مخزن مؤقت لمنع أي مخرجات سابقة من كسر الرؤوس
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $redirect, true, 302);
    exit;
}

// في حال تم إرسال رؤوس مسبقاً (BOM أو مسافات)، استخدم ميتا-ريفريش كحل بديل.
$esc = htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta http-equiv="refresh" content="0;url=<?= $esc ?>">
  <title>إعادة التوجيه…</title>
</head>
<body>
  <p>جارٍ التحويل… إن لم يتم، <a href="<?= $esc ?>">اضغط هنا</a>.</p>
</body>
</html>