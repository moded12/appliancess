// المسار: admin/orders.php

<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/db.php';

// حماية الأدمن
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// جلب الطلبات من قاعدة البيانات مع بيانات الزبون
$stmt = $pdo->query("SELECT o.*, c.name AS customer_name, c.phone AS customer_phone 
                     FROM orders o
                     LEFT JOIN customers c ON o.customer_id = c.id
                     ORDER BY o.created_at DESC");
$orders = $stmt->fetchAll();
?>

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إدارة الطلبات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">إدارة الطلبات</h1>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الزبون</th>
                    <th>الهاتف</th>
                    <th>السعر الكلي</th>
                    <th>طريقة الدفع</th>
                    <th>حالة الدفع</th>
                    <th>حالة الطلب</th>
                    <th class="text-end">تاريخ الإنشاء</th>
                    <th class="text-end">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= (int)$order['id'] ?></td>
                    <td><?= htmlspecialchars($order['customer_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($order['customer_phone'] ?? '-') ?></td>
                    <td>$<?= number_format((float)$order['total'], 2) ?></td>
                    <td><?= htmlspecialchars($order['payment_method']) ?></td>
                    <td>
                        <span class="badge <?= $order['payment_status']=='paid' ? 'bg-success' : ($order['payment_status']=='failed' ? 'bg-danger' : 'bg-warning') ?>">
                            <?= $order['payment_status'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge 
                            <?php
                                switch($order['status']) {
                                    case 'completed': echo 'bg-success'; break;
                                    case 'shipped': echo 'bg-info'; break;
                                    case 'cancelled': echo 'bg-danger'; break;
                                    case 'processing': echo 'bg-primary'; break;
                                    default: echo 'bg-secondary';
                                }
                            ?>">
                            <?= $order['status'] ?>
                        </span>
                    </td>
                    <td class="text-end"><?= date('Y-m-d', strtotime($order['created_at'])) ?></td>
                    <td class="text-end">
                        <a href="order_view.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="bi bi-eye"></i> تفاصيل
                        </a>
                        <a href="order_delete.php?id=<?= (int)$order['id'] ?>" 
                           class="btn btn-sm btn-outline-danger rounded-pill" 
                           onclick="return confirm('هل أنت متأكد من حذف الطلب؟');">
                            <i class="bi bi-trash"></i> حذف
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <a href="dashboard.php" class="btn btn-secondary mt-4 rounded-pill"><i class="bi bi-arrow-left"></i> عودة للوحة التحكم</a>
</div>
</body>
</html>