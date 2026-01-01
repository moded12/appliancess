<?php
// المسار: admin/fix_main_images.php
// يملأ products.main_image بأول صورة من product_media إذا كانت فارغة
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
session_start();
if (empty($_SESSION['admin'])) { exit('Not authorized'); }

$updated = 0;
$sql = "
  SELECT p.id, pm.file
  FROM products p
  JOIN product_media pm ON pm.product_id = p.id
  WHERE p.main_image IS NULL OR p.main_image=''
    AND pm.media_type='image'
  GROUP BY p.id
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$upd = $pdo->prepare('UPDATE products SET main_image=:f WHERE id=:id');
foreach ($rows as $r) {
  $upd->execute([':f'=>$r['file'], ':id'=>$r['id']]);
  $updated++;
}
echo 'تم تعيين صورة رئيسية لعدد: '.$updated.' منتج.';