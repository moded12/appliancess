<?php
// مؤقت للتشخيص — بعد الانتهاء أعد تسمية أو احذف الملف
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// مكان لكتابة لوق محلي إن لم تتمكن من الوصول لسجلات السيرفر
$logPath = __DIR__ . '/../storage/logs/payment_init.log';
@mkdir(dirname($logPath), 0755, true);

try {
    if (!isset($_GET['order'])) {
        header('Location: /public/checkout.php');
        exit;
    }
    $order_id = (int)$_GET['order'];
    if ($order_id <= 0) {
        throw new Exception('Order id invalid');
    }

    // تحقق من وجود الملفات الأساسية
    $dbFile = __DIR__ . '/../includes/db.php';
    $paymentsFile = __DIR__ . '/../includes/payments.php';
    $missing = [];
    if (!file_exists($dbFile)) $missing[] = 'includes/db.php';
    if (!file_exists($paymentsFile)) $missing[] = 'includes/payments.php';

    if ($missing) {
        $msg = 'Missing required file(s): ' . implode(', ', $missing);
        file_put_contents($logPath, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
        throw new Exception($msg);
    }

    require_once $dbFile;
    require_once $paymentsFile;

    // تأكد أن $pdo موجود
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $msg = 'PDO connection ($pdo) not available. Check includes/db.php';
        file_put_contents($logPath, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
        throw new Exception($msg);
    }

    // جلب الطلب للتأكد من عمل الاتصال
    $stmt = $pdo->prepare("SELECT id, total, currency FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        $msg = "Order not found: $order_id";
        file_put_contents($logPath, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
        throw new Exception($msg);
    }

    echo "<h2>Order OK — ready to start payment</h2>";
    echo "<p>Order ID: " . htmlspecialchars($order['id']) . "</p>";
    echo "<p>Amount: " . htmlspecialchars($order['total']) . " " . htmlspecialchars($order['currency'] ?? 'USD') . "</p>";
    echo "<p>لا يظهر هنا خطأ — الاتصال بقاعدة البيانات يعمل وملفات الدفع موجودة.</p>";

} catch (Throwable $e) {
    $err = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
    // سجل في ملف محلي
    file_put_contents($logPath, $err, FILE_APPEND);
    // واطبع رسالة مبسطة للمستخدم
    echo "حدث خطأ في الخادم. الرجاء المحاولة لاحقًا.";
    // وللمساعدة: أطبع مكان ملف اللوق الذي يحتوي على التفاصيل
    echo "<p>Log file: " . htmlspecialchars($logPath) . "</p>";
    exit;
}
?>