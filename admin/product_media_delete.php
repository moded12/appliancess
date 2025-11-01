<?php require_once __DIR__.'/../includes/functions.php'; require_login(); require_once __DIR__.'/../includes/db.php';
$base=rtrim($config['base_url'],'/'); $id=(int)($_GET['id']??0); $pid=(int)($_GET['pid']??0);
if($id){
  $q=$pdo->prepare('SELECT file FROM product_media WHERE id=:id'); $q->execute([':id'=>$id]); $f=$q->fetchColumn();
  $pdo->prepare('DELETE FROM product_media WHERE id=:id')->execute([':id'=>$id]);
  $path=__DIR__.'/../public/assets/uploads/'.$f; if($f && is_file($path)) @unlink($path);
}
header('Location: '.$base.'/admin/product_edit.php?id='.$pid);
