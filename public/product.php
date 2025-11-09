<?php
$title='تفاصيل المنتج';
include __DIR__.'/../includes/header.php';
$base=rtrim($config['base_url'],'/'); $id=max(1,(int)($_GET['id']??0));
$stmt=$pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=:id');
$stmt->execute([':id'=>$id]); $p=$stmt->fetch();
if(!$p){ echo '<div class="alert alert-danger">المنتج غير موجود.</div>'; include __DIR__.'/../includes/footer.php'; exit; }
$med=$pdo->prepare('SELECT * FROM product_media WHERE product_id=:id ORDER BY sort_order,id'); $med->execute([':id'=>$p['id']]); $gallery=$med->fetchAll();
?>
<div class="row g-4">
  <div class="col-md-6">
    <div id="mainMedia">
      <?php if($p['main_image']): ?>
        <img class="img-fluid rounded-4 shadow" src="<?= $base ?>/assets/uploads/<?= e($p['main_image']) ?>" alt="<?= e($p['name']) ?>">
      <?php elseif(!empty($gallery) && $gallery[0]['media_type']==='video'): ?>
        <div class="media-16x9"><video src="<?= $base ?>/assets/uploads/<?= e($gallery[0]['file']) ?>" autoplay muted loop playsinline></video></div>
      <?php else: ?>
        <img class="img-fluid rounded-4 shadow" src="https://placehold.co/1000x700?text=No+Image" alt="<?= e($p['name']) ?>">
      <?php endif; ?>
    </div>
    <?php if(!empty($gallery)): ?>
      <div class="row g-2 mt-2">
        <?php foreach($gallery as $i=>$g): ?>
          <?php if($g['media_type']==='image'): ?>
            <div class="col-3"><img class="thumb <?= $i==0?'active':'' ?>" data-type="image" data-main-src="<?= $base ?>/assets/uploads/<?= e($g['file']) ?>" src="<?= $base ?>/assets/uploads/<?= e($g['file']) ?>" alt=""></div>
          <?php else: ?>
            <div class="col-3"><img class="thumb <?= $i==0?'active':'' ?>" data-type="video" data-main-src="<?= $base ?>/assets/uploads/<?= e($g['file']) ?>" src="https://placehold.co/200x120?text=Video" alt=""></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-6">
    <h1 class="h3 fw-bold mb-1"><?= e($p['name']) ?></h1>
    <div class="text-muted mb-2"><?= e($p['category_name'] ?? 'غير مصنف') ?></div>
    <div class="d-flex align-items-center gap-3 mb-3">
      <span class="display-6 fw-bolder text-success"><?= price($p['price']) ?></span>
      <span class="badge <?= $p['stock']>0?'bg-success':'bg-secondary' ?>"><?= $p['stock']>0?'متوفر':'غير متوفر' ?></span>
    </div>
    <p class="mb-3"><?= nl2br(e($p['description'])) ?></p>
    <form method="post" action="<?= $base ?>/product_add.php" class="d-flex align-items-center gap-2">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <input class="form-control" style="width:120px" type="number" name="qty" min="1" value="1">
      <button class="btn btn-brand rounded-pill"><i class="bi bi-cart-plus"></i> أضِف للسلة</button>
      <a class="btn btn-outline-secondary rounded-pill" href="<?= $base ?>/cart.php"><i class="bi bi-cart"></i> عرض السلة</a>
    </form>
    <table class="table specs mt-4">
      <tr><td class="text-muted">المعرف</td><td>#<?= (int)$p['id'] ?></td></tr>
      <tr><td class="text-muted">القسم</td><td><?= e($p['category_name'] ?? '—') ?></td></tr>
      <tr><td class="text-muted">التاريخ</td><td><?= e($p['created_at']) ?></td></tr>
    </table>
  </div>
</div>
<hr class="my-4">
<?php
$sim=[];
if($p['category_id']){
  $q=$pdo->prepare('SELECT id,name,price,main_image FROM products WHERE category_id=:cid AND id<>:id ORDER BY id DESC LIMIT 8');
  $q->execute([':cid'=>$p['category_id'],':id'=>$p['id']]); $sim=$q->fetchAll();
}
if($sim): ?>
<h2 class="h5 mb-3">منتجات مشابهة</h2>
<div class="row g-3">
  <?php foreach($sim as $s): ?>
    <div class="col-6 col-md-3">
      <div class="card product-card h-100 fade-in">
        <img src="<?= $s['main_image'] ? $base.'/assets/uploads/'.e($s['main_image']) : 'https://placehold.co/600x400?text=No+Image' ?>" class="card-img-top" alt="<?= e($s['name']) ?>">
        <div class="card-body d-flex flex-column">
          <a class="stretched-link text-decoration-none" href="<?= $base ?>/product.php?id=<?= (int)$s['id'] ?>"><h3 class="h6 card-title mb-1"><?= e($s['name']) ?></h3></a>
          <span class="price mt-auto"><?= price($s['price']) ?></span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php include __DIR__.'/../includes/footer.php'; ?>
