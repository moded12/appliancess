<?php require_once __DIR__.'/../includes/functions.php'; require_login(); require_once __DIR__.'/../includes/db.php';
$title='تحرير منتج'; $base=rtrim($config['base_url'],'/'); $cats=$pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$id=isset($_GET['id'])?(int)$_GET['id']:0; $product=null; $product_media=[];
if($id){ $st=$pdo->prepare('SELECT * FROM products WHERE id=:id'); $st->execute([':id'=>$id]); $product=$st->fetch();
  $m=$pdo->prepare('SELECT * FROM product_media WHERE product_id=:id ORDER BY sort_order,id'); $m->execute([':id'=>$id]); $product_media=$m->fetchAll();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_check($_POST['csrf'] ?? '')){ die('CSRF'); }
  $name=trim($_POST['name']??''); $slug=slugify($name);
  $data=[':name'=>$name, ':slug'=>$slug, ':description'=>trim($_POST['description']??''), ':price'=>(float)($_POST['price']??0), ':stock'=>(int)($_POST['stock']??0), ':category_id'=>!empty($_POST['category_id'])?(int)$_POST['category_id']:null];
  if($id){
    $data[':id']=$id;
    $pdo->prepare('UPDATE products SET name=:name, slug=:slug, description=:description, price=:price, stock=:stock, category_id=:category_id WHERE id=:id')->execute($data);
  } else {
    $pdo->prepare('INSERT INTO products (name, slug, description, price, stock, category_id, main_image) VALUES (:name, :slug, :description, :price, :stock, :category_id, NULL)')->execute($data);
    $id=(int)$pdo->lastInsertId();
  }
  if(!empty($_FILES['images']['name'][0])){
    $dir=__DIR__.'/../public/assets/uploads'; if(!is_dir($dir)) mkdir($dir,0775,true);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $ins=$pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:pid,"image",:f,:o)'); $o=0;
    foreach($_FILES['images']['tmp_name'] as $i=>$tmp){
      if(!is_uploaded_file($tmp)) continue;
      $mime=mime_content_type($tmp); if(!isset($allowed[$mime])) continue;
      $ext=$allowed[$mime]; $namef=bin2hex(random_bytes(8)).'.'.$ext;
      move_uploaded_file($tmp,$dir.'/'.$namef);
      if($o==0){ $pdo->prepare('UPDATE products SET main_image=:img WHERE id=:id')->execute([':img'=>$namef, ':id'=>$id]); }
      $ins->execute([':pid'=>$id, ':f'=>$namef, ':o'=>$o++]);
    }
  }
  if(!empty($_FILES['video']['tmp_name'])){
    $dir=__DIR__.'/../public/assets/uploads'; if(!is_dir($dir)) mkdir($dir,0775,true);
    $allowedv=['video/mp4'=>'mp4','video/webm'=>'webm'];
    $mime=mime_content_type($_FILES['video']['tmp_name']); if(isset($allowedv[$mime])){
      $namev=bin2hex(random_bytes(8)).'.'.$allowedv[$mime];
      move_uploaded_file($_FILES['video']['tmp_name'],$dir.'/'.$namev);
      $pdo->prepare('INSERT INTO product_media (product_id,media_type,file,sort_order) VALUES (:pid,"video",:f,0)')->execute([':pid'=>$id, ':f'=>$namev]);
    }
  }
  header('Location: '.$base.'/admin/products.php'); exit;
}
include __DIR__.'/../includes/header.php'; ?>
<h1 class="h5 mb-3"><?= $id ? 'تعديل' : 'إضافة' ?> منتج</h1>
<form method="post" enctype="multipart/form-data" class="card shadow-sm p-3 glass">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">الاسم</label><input class="form-control" name="name" required value="<?= e($product['name']??'') ?>"></div>
    <div class="col-md-3"><label class="form-label">السعر ($)</label><input class="form-control" type="number" step="0.01" name="price" required value="<?= e($product['price']??'0.00') ?>"></div>
    <div class="col-md-3"><label class="form-label">المخزون</label><input class="form-control" type="number" name="stock" required value="<?= e($product['stock']??'0') ?>"></div>
    <div class="col-md-6">
      <label class="form-label">القسم</label>
      <select class="form-select" name="category_id">
        <option value="">— لا شيء —</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= isset($product['category_id']) && $product['category_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6"><label class="form-label">صور المنتج (عدة صور)</label><input class="form-control" type="file" accept="image/*" name="images[]" multiple></div>
    <div class="col-md-6"><label class="form-label">فيديو المنتج (MP4/WebM)</label><input class="form-control" type="file" accept="video/mp4,video/webm" name="video"></div>
    <div class="col-12"><label class="form-label">الوصف</label><textarea class="form-control" name="description" rows="5"><?= e($product['description']??'') ?></textarea></div>
  </div>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-brand rounded-pill"><?= $id ? 'حفظ التغييرات' : 'إنشاء المنتج' ?></button>
    <a class="btn btn-secondary rounded-pill" href="<?= $base ?>/admin/products.php">إلغاء</a>
  </div>
</form>
<div class="mt-4">
  <h2 class="h6">وسائط المنتج</h2>
  <?php if (empty($product_media)): ?>
    <div class="text-muted">لا توجد وسائط بعد.</div>
  <?php else: ?>
    <div class="row g-2">
      <?php foreach ($product_media as $m): ?>
        <div class="col-6 col-md-3">
          <div class="card p-2">
            <?php if ($m['media_type']==='image'): ?>
              <img class="w-100 rounded" src="<?= $base ?>/assets/uploads/<?= e($m['file']) ?>" alt="" style="height:120px;object-fit:cover">
            <?php else: ?>
              <div class="media-16x9"><video src="<?= $base ?>/assets/uploads/<?= e($m['file']) ?>" muted></video></div>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-danger rounded-pill mt-2" href="<?= $base ?>/admin/product_media_delete.php?id=<?= (int)$m['id'] ?>&pid=<?= (int)($id ?? 0) ?>" onclick="return confirm('حذف الوسيط؟')">حذف</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
