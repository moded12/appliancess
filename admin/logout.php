<?php
// المسار: admin/logout.php
// تسجيل خروج آمن للأدمن

// تأكد من عدم وجود أي مخرجات قبل هذا السطر (لا مسافات ولا BOM)
declare(strict_types=1);

// تفعيل عرض الأخطاء مؤقتاً عند الحاجة (يمكن إزالته في الإنتاج)
// ini_set('display_errors', '1'); error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // تشديد وضع الجلسة
    ini_set('session.use_strict_mode', '1');
    session_start();
} else {
    // نحافظ على أمان الجلسة
    session_regenerate_id(true);
}

// إن كانت هناك متغيرات أخرى (cart, token) لا نريد مسحها، نستهدف فقط مفاتيح الأدمن
$adminKeys = ['admin', 'admin_id', 'admin_email'];
foreach ($adminKeys as $k) {
    if (isset($_SESSION[$k])) {
        unset($_SESSION[$k]);
    }
}

// يمكن اختيارياً تفريغ كل الجلسة إذا أردت إنهاء كامل السياق:
if (!empty($_GET['full']) && $_GET['full'] === '1') {
    $_SESSION = [];

    // حذف كوكي الجلسة
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// إعادة توليد معرف جديد بعد إزالة البيانات (دفاع ضد Fixation)
session_regenerate_id(true);

// تحديد وجهة إعادة التوجيه
$redirect = 'login.php';

// إذا وجد Referer داخلي (من نفس النطاق) نستخدمه للعودة إلى صفحة الدخول أو الرئيسية
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref) {
    $refHost = parse_url($ref, PHP_URL_HOST);
    $thisHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost && $thisHost && strcasecmp($refHost, $thisHost) === 0) {
        // نتأكد ألا نعود إلى صفحة حساسة بعد logout (مثل dashboard)
        if (stripos($ref, 'dashboard.php') !== false || stripos($ref, 'products.php') !== false) {
            // ابقِ على login
        } else {
            // ممكن توجيه لصفحة عمومية
            // $redirect = $ref;
        }
    }
}

// إرسال التوجيه
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $redirect);
    exit;
}

// في حال headers أُرسلت (بسبب BOM)، استخدم ميتا-ريفريش:
?>
<!doctype html><html lang="ar" dir="rtl"><head>
<meta charset="utf-8">
<meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
<title>تسجيل الخروج</title>
</head>
<body>
<p>جارٍ تسجيل الخروج… إن لم تُنقل تلقائياً <a href="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">اضغط هنا</a>.</p>
</body></html>