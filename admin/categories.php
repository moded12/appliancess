<?php require_once __DIR__.'/../includes/functions.php'; require_login(); require_once __DIR__.'/../includes/db.php';
$title='الأقسام'; include __DIR__.'/../includes/header.php'; $base=rtrim($config['base_url'],'/');
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_check($_POST['csrf'] ?? '')){ die('CSRF'); }
  $name=trim($_POST['name']??''); if($name){ $slug=slugify($name);
    $pdo->prepare('INSERT INTO categories (name,slug) VALUES (:n,:s) ON DUPLICATE KEY UPDATE name=:n')->execute([':n'=>$name,':s'=>$slug]);
    echo '<div class="alert alert-success">تم الحفظ.</div>';
  }
}
if(isset($_GET['delete'])){ $id=(int)$_GET['delete']; $pdo->prepare('DELETE FROM categories WHERE id=:id')->execute([':id'=>$id]); echo '<div class="alert alert-success">تم الحذف.</div>'; }
$cats=$pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
?>
<h1 class="h5 mb-3">الأقسام</h1>
<form class="row g-2 mb-3 glass p-3" method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="col-md-6"><input class="form-control" name="name" placeholder="اسم القسم الجديد" required></div>
  <div class="col-md-3"><button class="btn btn-brand rounded-pill">إضافة</button></div>
</form>
<div class="table-responsive glass p-2">
<table class="table table-hover align-middle m-0">
  <thead><tr><th>#</th><th>الاسم</th><th>الرابط</th><th class="text-end">إجراءات</th></tr></thead>
  <tbody><?php foreach($cats as $c): ?><tr>
    <td><?= (int)$c['id'] ?></td><td><?= e($c['name']) ?></td><td><code><?= e($c['slug']) ?></code></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-danger rounded-pill" href="<?= $base ?>/admin/categories.php?delete=<?= (int)$c['id'] ?>" onclick="return confirm('حذف القسم؟')"><i class="bi bi-trash"></i> حذف</a></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
