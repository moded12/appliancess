<?php
require __DIR__.'/../includes/db.php'; require __DIR__.'/../includes/functions.php'; require __DIR__.'/../includes/cart.php';
$base=rtrim($config['base_url'],'/'); $id=(int)($_POST['id']??0); $qty=max(1,(int)($_POST['qty']??1));
$st=$pdo->prepare('SELECT id,name,price FROM products WHERE id=:id'); $st->execute([':id'=>$id]); $p=$st->fetch();
if($p){ cart_add($p['id'],$p['name'],(float)$p['price'],$qty); }
header('Location: '.$base.'/cart.php');
