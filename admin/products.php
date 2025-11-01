<?php require_once __DIR__.'/../includes/functions.php'; require_login(); require_once __DIR__.'/../includes/db.php';
$title='المنتجات'; include __DIR__.'/../includes/header.php'; $base=rtrim($config['base_url'],'/');
if(isset($_GET['delete'])){ $id=(int)$_GET['delete']; $pdo->prepare('DELETE FROM products WHERE id=:id')->execute([':id'=>$id]); echo '<div class="alert alert-success">تم حذف المنتج.</div>'; }
$prods=$pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC')->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h5 m-0">المنتجات</h1>
  <a class="btn btn-brand rounded-pill" href="<?= $base ?>/admin/product_edit.php"><i class="bi bi-plus-lg"></i> إضافة منتج</a>
</div>
<div class="table-responsive glass p-2">
<table class="table table-hover align-middle m-0">
  <thead><tr><th>#</th><th>الاسم</th><th>القسم</th><th>السعر</th><th>المخزون</th><th class="text-end">إجراءات</th></tr></thead>
  <tbody>
    <?php foreach ($prods as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><?= e($p['name']) ?></td>
        <td><?= e($p['category_name'] ?? '—') ?></td>
        <td>$<?= number_format((float)$p['price'],2) ?></td>
        <td><?= (int)$p['stock'] ?></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= $base ?>/admin/product_edit.php?id=<?= (int)$p['id'] ?>"><i class="bi bi-pencil-square"></i> تعديل</a>
          <a class="btn btn-sm btn-outline-danger rounded-pill" href="<?= $base ?>/admin/products.php?delete=<?= (int)$p['id'] ?>" onclick="return confirm('حذف المنتج؟')"><i class="bi bi-trash"></i> حذف</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
