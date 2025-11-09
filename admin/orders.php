<?php require_once __DIR__.'/../includes/functions.php'; require_login(); require_once __DIR__.'/../includes/db.php';
$title='الطلبات'; include __DIR__.'/../includes/header.php'; $base=rtrim($config['base_url'],'/');
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['id'])){
  if(!csrf_check($_POST['csrf'] ?? '')){ die('CSRF'); }
  $id=(int)$_POST['id']; $status=$_POST['status']??'new'; $pay=$_POST['payment_status']??'pending';
  $pdo->prepare('UPDATE orders SET status=:s, payment_status=:p WHERE id=:id')->execute([':s'=>$status, ':p'=>$pay, ':id'=>$id]);
  echo '<div class="alert alert-success">تم التحديث.</div>';
}
$orders=$pdo->query('SELECT o.*, c.name, c.phone FROM orders o LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC')->fetchAll();
?>
<h1 class="h5 mb-3">الطلبات</h1>
<div class="table-responsive glass p-2">
<table class="table table-hover align-middle m-0">
  <thead><tr><th>#</th><th>العميل</th><th>الهاتف</th><th>الإجمالي</th><th>الدفع</th><th>الحالة</th><th class="text-end">إجراءات</th></tr></thead>
  <tbody>
  <?php foreach($orders as $o): ?>
    <tr>
      <td>#<?= (int)$o['id'] ?></td>
      <td><?= e($o['name']) ?></td>
      <td><?= e($o['phone']) ?></td>
      <td>$<?= number_format((float)$o['total'],2) ?></td>
      <td><?= e($o['payment_status']) ?> (<?= e($o['payment_method']) ?>)</td>
      <td><?= e($o['status']) ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= $base ?>/invoice.php?id=<?= (int)$o['id'] ?>" target="_blank">فاتورة</a>
        <button class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="collapse" data-bs-target="#u<?= (int)$o['id'] ?>">تحديث</button>
      </td>
    </tr>
    <tr class="collapse" id="u<?= (int)$o['id'] ?>">
      <td colspan="7">
        <form method="post" class="d-flex gap-2 align-items-center">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
          <select name="payment_status" class="form-select" style="width:160px"><?php foreach(['pending','paid','failed'] as $ps): ?><option value="<?= $ps ?>" <?= $ps===$o['payment_status']?'selected':'' ?>><?= $ps ?></option><?php endforeach; ?></select>
          <select name="status" class="form-select" style="width:180px"><?php foreach(['new','processing','shipped','completed','cancelled'] as $st): ?><option value="<?= $st ?>" <?= $st===$o['status']?'selected':'' ?>><?= $st ?></option><?php endforeach; ?></select>
          <button class="btn btn-brand rounded-pill">حفظ</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
