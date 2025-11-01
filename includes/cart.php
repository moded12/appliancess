<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function cart_get(){ return $_SESSION['cart'] ?? []; }
function cart_add($pid,$name,$price,$qty=1){
  $pid=(int)$pid; $qty=max(1,(int)$qty);
  if(!isset($_SESSION['cart'])) $_SESSION['cart']=[];
  if(!isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid]=['name'=>$name,'price'=>(float)$price,'qty'=>0];
  $_SESSION['cart'][$pid]['qty'] += $qty;
}
function cart_update($pid,$qty){ $pid=(int)$pid; $qty=(int)$qty; if(isset($_SESSION['cart'][$pid])){ if($qty<=0) unset($_SESSION['cart'][$pid]); else $_SESSION['cart'][$pid]['qty']=$qty; } }
function cart_remove($pid){ $pid=(int)$pid; if(isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]); }
function cart_clear(){ unset($_SESSION['cart']); }
function cart_totals(){ $t=0;$c=0; foreach(cart_get() as $it){ $t+=$it['price']*$it['qty']; $c+=$it['qty']; } return ['count'=>$c,'total'=>$t]; }
