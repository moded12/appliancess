// المسار: admin/users.php

<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/db.php';

// حماية لوحة الأدمن
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// جلب المستخدمين/الأدمن من قاعدة البيانات
$stmt = $pdo->query("SELECT id, email, role, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إدارة الأدمن/المستخدمين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">إدارة الأدمن والمستخدمين</h1>
    <a href="user_add.php" class="btn btn-success rounded-pill mb-3"><i class="bi bi-person-plus"></i> إضافة أدمن جديد</a>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>البريد الإلكتروني</th>
                    <th>الدور</th>
                    <th>تاريخ الإنشاء</th>
                    <th class="text-end">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int)$user['id'] ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <span class="badge <?= $user['role']=='admin' ? 'bg-success' : 'bg-primary' ?>">
                            <?= $user['role'] ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                    <td class="text-end">
                        <a href="user_edit.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="bi bi-pencil-square"></i> تعديل
                        </a>
                        <a href="user_delete.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill"
                           onclick="return confirm('هل أنت متأكد من حذف هذا الأدمن؟');">
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