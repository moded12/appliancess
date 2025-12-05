<?php
// المسار: admin/products.php

require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/db.php';

session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('e')) { function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }

$statusMsg = '';
if (isset($_GET['status'])) {
  $map = [
    'deleted' => 'تم حذف المنتج بنجاح.',
    'error' => 'حدث خطأ أثناء الحذف.',
    'not_found' => 'المنتج غير موجود.',
    'csrf_fail' => 'فشل التحقق الأمني.',
    'bad_id' => 'معرّف المنتج غير صالح.',
    'invalid_method' => 'طريقة غير مسموحة.',
  ];
  $statusMsg = $map[$_GET['status']] ?? '';
}

// جلب المنتجات
$stmt = $pdo->query("SELECT p.*, c.name AS category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    ORDER BY p.id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إدارة المنتجات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      .badge-featured{background:#f59e0b;}
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">إدارة المنتجات</h1>

    <?php if ($statusMsg): ?>
      <div class="alert alert-info"><?= e($statusMsg) ?></div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-3 flex-wrap">
      <a href="product_add.php" class="btn btn-success rounded-pill"><i class="bi bi-plus-lg"></i> منتج جديد</a>
      <a href="categories.php" class="btn btn-outline-info rounded-pill"><i class="bi bi-tags"></i> الأقسام</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>القسم</th>
                    <th>السعر</th>
                    <th>المخزون</th>
                    <th>مميز؟</th>
                    <th class="text-end">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['category_name'] ?? 'غير مصنف') ?></td>
                    <td><?= number_format((float)$p['price'], 2) ?></td>
                    <td><?= (int)$p['stock'] ?></td>
                    <td>
                      <?php if (!empty($p['is_featured'])): ?>
                        <span class="badge badge-featured text-dark">مميز</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="product_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="bi bi-pencil-square"></i> تعديل
                        </a>
                        <form method="post" action="product_delete.php" class="d-inline"
                              onsubmit="return confirm('هل أنت متأكد من حذف المنتج؟ هذا الإجراء لا يمكن التراجع عنه');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill">
                              <i class="bi bi-trash"></i> حذف
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                  <tr><td colspan="7" class="text-center text-muted">لا توجد منتجات.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="dashboard.php" class="btn btn-secondary mt-4 rounded-pill"><i class="bi bi-arrow-left"></i> عودة للوحة التحكم</a>
</div>
</body>
</html>