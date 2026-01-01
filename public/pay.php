<?php
// public/pay.php (نسخة عرضية/مُحسنة لعرض رسائل جميلة وتسجيل الأخطاء)
// بعد الإصلاح احفظ النسخة الأصلية أو أعد النسخة الأصلية إلى مكانها.

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

$logFile = __DIR__ . '/../storage/logs/payments_debug.log';
function dbg_log($msg) {
    global $logFile;
    @mkdir(dirname($logFile), 0755, true);
    file_put_contents($logFile, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
}

try {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/payments.php';
} catch (Throwable $e) {
    dbg_log("Require error: " . $e->getMessage());
    $userMsg = "حدث خطأ تقني أثناء بدء الدفعة. تم تسجيل التفاصيل لدى الدعم.";
    show_page($userMsg, true);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./checkout.php');
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$gateway = $_POST['gateway'] ?? '';

if (!$order_id || !$gateway) {
    header('Location: ./checkout.php?error=invalid');
    exit;
}

// جلب الطلب
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    dbg_log("DB query error: " . $e->getMessage());
    show_page("حدث خطأ في قاعدة البيانات أثناء بدء الدفع. تم تسجيل الخطأ.", true);
    exit;
}

if (!$order) {
    header('Location: ./checkout.php?error=order_not_found');
    exit;
}

try {
    if ($gateway === 'stripe') {
        $redirect = create_stripe_session($pdo, $order);
        if (strpos($redirect, 'http') === 0) {
            header('Location: ' . $redirect);
            exit;
        } else {
            $_SESSION['stripe_session_id'] = $redirect;
            header('Location: ./stripe_redirect.php');
            exit;
        }
    } elseif ($gateway === 'paypal') {
        $approveUrl = create_paypal_order($pdo, $order);
        header('Location: ' . $approveUrl);
        exit;
    } else {
        header('Location: ./checkout.php?error=invalid_gateway');
        exit;
    }
} catch (Throwable $e) {
    dbg_log("Payment init exception ({$gateway}): " . $e->getMessage() . " -- trace: " . $e->getTraceAsString());
    show_page("خطأ أثناء بدء عملية الدفع. تم تسجيل التفاصيل لفحصها.", true);
    exit;
}

/**
 * عرض صفحة رسالة مبسطة وأنيقة
 */
function show_page($message, $isError = false) {
    $color = $isError ? '#c0392b' : '#27ae60';
    $title = $isError ? 'حدث خطأ' : 'نجح الإجراء';
    $backUrl = './checkout.php';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;direction:rtl;padding:30px;background:#f7f7f7;color:#333} .card{max-width:700px;margin:40px auto;padding:20px;background:#fff;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.05)} h1{color:' . $color . ';margin-top:0} a.btn{display:inline-block;padding:10px 14px;margin-top:12px;background:#3498db;color:#fff;text-decoration:none;border-radius:4px}</style>';
    echo '</head><body><div class="card"><h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
    echo '<a class="btn" href="'.htmlspecialchars($backUrl).'">العودة لصفحة الدفع</a>';
    echo '</div></body></html>';
}
?>