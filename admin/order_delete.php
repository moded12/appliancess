<?php
// admin/order_delete.php
// حذف آمن مع PDO و transactions
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
// حماية الأدمن — عدّل مفتاح الجلسة لو يختلف عندك
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php'; // يجب أن يعرف هذا الملف $pdo (PDO instance)

// تحقق من معرف الطلب
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: orders.php?msg=invalid_id');
    exit;
}

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection not available.');
    }

    // ابدأ معاملة
    $pdo->beginTransaction();

    // حذف عناصر الطلب إن وجد جدول مثل order_items أو order_products
    $possibleDetails = ['order_items', 'order_products', 'order_details'];
    foreach ($possibleDetails as $table) {
        // تحقق أن الجدول موجود (خيارى) — هنا نحاول تنفيذ الحذف بصمت إن لم يوجد سيُرمى استثناء ويتم التقاطه
        try {
            $sql = "DELETE FROM `$table` WHERE order_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
        } catch (Exception $ex) {
            // تخطّي إذا كان الجدول غير موجود أو حدث خطأ ملحوظ لكن نكمل محاولة حذف الطلب الرئيسي
        }
    }

    // حذف الطلب نفسه (تأكد أن اسم جدول الطلبات في قاعدة بياناتك هو orders)
    $stmt = $pdo->prepare("DELETE FROM `orders` WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $deleted = $stmt->rowCount();

    $pdo->commit();

    if ($deleted > 0) {
        header('Location: orders.php?msg=deleted');
    } else {
        header('Location: orders.php?msg=not_found');
    }
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order delete error (id=' . $id . '): ' . $e->getMessage());
    // لا تعرض تفاصيل الخطأ للمستخدم، فقط أعد التوجيه أو أظهر رسالة عامة
    header('Location: orders.php?msg=error');
    exit;
}
?>