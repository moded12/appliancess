<?php
// المسار: public/checkout.php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // تأكد أنه يحتوي csrf_token / csrf_check
$config = require __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// دوال مساعدة احتياطية
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
function price_fmt(float $v): string {
  // اعتماداً على الإعدادات (من config قد تستخدم عملة تحويل)
  return '$' . number_format($v, 2);
}

// جلب السلة
$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.0;

if ($cart) {
  $ids = array_map('intval', array_keys($cart));
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT id,name,price FROM products WHERE id IN ($in)");
  $st->execute($ids);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $map = [];
  foreach ($rows as $r) $map[$r['id']] = $r;
  foreach ($cart as $pid => $qty) {
    if (!isset($map[$pid])) continue;
    $p = $map[$pid];
    $line = (float)$p['price'] * $qty;
    $total += $line;
    $items[] = [
      'id'=>$pid,'name'=>$p['name'],'price'=>(float)$p['price'],
      'qty'=>$qty,'subtotal'=>$line
    ];
  }
}

$err = '';
$msg = '';
$orderCreated = false;
$orderId = null;
$showPaymentForm = false;

// عند الإرسال
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) {
    $err = 'انتهت صلاحية النموذج. رجاء أعد المحاولة.';
  } elseif (!$items) {
    $err = 'السلة فارغة.';
  } else {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $method  = $_POST['payment_method'] ?? 'cod'; // cod | cliq | card | stripe | paypal

    if ($name==='' || $phone==='' || $address==='') {
      $err = 'يرجى تعبئة جميع الحقول.';
    } elseif (!in_array($method, ['cod','cliq','card','stripe','paypal'], true)) {
      $err = 'طريقة دفع غير صالحة.';
    } else {

      // تجهيز الحالة المبدئية
      $gateway = null;
      $paymentStatus = 'pending';
      $orderStatus = 'new';

      if ($method === 'cliq') {
        $gateway = 'cliq';
        $paymentStatus = 'awaiting_transfer';
        $orderStatus = 'waiting_payment';
      } elseif ($method === 'card') {
        $gateway = 'card_stub';
        $paymentStatus = 'initiated';
        $orderStatus = 'waiting_payment';
      } elseif ($method === 'stripe') {
        $gateway = 'stripe';
        $paymentStatus = 'pending';
        $orderStatus = 'waiting_payment';
      } elseif ($method === 'paypal') {
        $gateway = 'paypal';
        $paymentStatus = 'pending';
        $orderStatus = 'waiting_payment';
      } else { // cod
        $gateway = 'cod';
        $paymentStatus = 'pending';
        $orderStatus = 'new';
      }

      // الحقول الإضافية لطريقة كليك
      $cliqRef   = trim($_POST['cliq_reference'] ?? '');
      $cliqIban  = trim($_POST['cliq_iban'] ?? '');
      $paymentMeta = [];

      if ($method === 'cliq') {
        if ($cliqIban==='') {
          $err = 'يرجى إدخال رقم IBAN أو حساب الاستقبال.';
        } elseif ($cliqRef==='') {
          $err = 'يرجى إدخال مرجع التحويل (Reference).';
        } else {
          $paymentMeta['cliq_reference'] = $cliqRef;
          $paymentMeta['cliq_iban'] = $cliqIban;
        }
      }

      // بطاقة (مجرد نموذج – لا يخزن الرقم كاملاً)
      if ($method === 'card') {
        $cardHolder = trim($_POST['card_holder'] ?? '');
        $cardNumber = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
        $exp        = trim($_POST['card_exp'] ?? '');
        $cvv        = trim($_POST['card_cvv'] ?? '');

        // تحقق أساسي فقط
        if ($cardHolder==='' || $cardNumber==='' || $exp==='' || $cvv==='') {
          $err = 'يرجى تعبئة بيانات البطاقة كاملة.';
        } elseif (strlen($cardNumber) < 12) {
          $err = 'رقم بطاقة غير صحيح.';
        } else {
          // لا تخزن البطاقة الحقيقية – مثال تخزين آخر 4 أرقام فقط
          $paymentMeta['card_last4'] = substr($cardNumber, -4);
          $paymentMeta['card_holder'] = $cardHolder;
          $paymentMeta['card_exp'] = $exp;
          // في التكامل الحقيقي ترسل هذه البيانات لبوابة الدفع وليس لقاعدة البيانات مباشرة
        }
      }

      if (!$err) {
        try {
          $pdo->beginTransaction();

          // جلب/إنشاء عميل
          $custId = null;
          $cst = $pdo->prepare('SELECT id FROM customers WHERE phone=:p LIMIT 1');
          $cst->execute([':p'=>$phone]);
          $rowC = $cst->fetch(PDO::FETCH_ASSOC);
          if ($rowC) {
            $custId = (int)$rowC['id'];
            $upc = $pdo->prepare('UPDATE customers SET name=:n,address=:a WHERE id=:id');
            $upc->execute([':n'=>$name, ':a'=>$address, ':id'=>$custId]);
          } else {
            $insc = $pdo->prepare('INSERT INTO customers (name,phone,address,created_at) VALUES (:n,:p,:a,NOW())');
            $insc->execute([':n'=>$name, ':p'=>$phone, ':a'=>$address]);
            $custId = (int)$pdo->lastInsertId();
          }

          // إنشاء الطلب
          $insO = $pdo->prepare('INSERT INTO orders (customer_id,total,payment_method,payment_status,gateway,status,created_at,payment_meta) 
                                 VALUES (:c,:t,:pm,:ps,:gw,:st,NOW(),:meta)');
          $insO->execute([
            ':c'=>$custId,
            ':t'=>$total,
            ':pm'=>$method,
            ':ps'=>$paymentStatus,
            ':gw'=>$gateway,
            ':st'=>$orderStatus,
            ':meta'=> json_encode($paymentMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
          ]);
          $orderId = (int)$pdo->lastInsertId();

          // بنود الطلب
          $insI = $pdo->prepare('INSERT INTO order_items (order_id,product_id,qty,price) VALUES (:o,:p,:q,:r)');
            foreach ($items as $it) {
              $insI->execute([
                ':o'=>$orderId,
                ':p'=>$it['id'],
                ':q'=>$it['qty'],
                ':r'=>$it['price']
              ]);
              // خصم المخزون
              $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - :q) WHERE id=:id')
                  ->execute([':q'=>$it['qty'], ':id'=>$it['id']]);
            }

          $pdo->commit();

          $_SESSION['cart'] = []; // تفريغ السلة بعد إنشاء الطلب
          $_SESSION['pending_order_id'] = $orderId; // حفظ معرف الطلب للدفع
          $orderCreated = true;

          if ($method === 'cod') {
            $msg = 'تم إنشاء الطلب بنجاح. رقم الطلب: '.$orderId.' سيتم الدفع عند التوصيل.';
          } elseif ($method === 'cliq') {
            $msg = 'تم إنشاء الطلب رقم '.$orderId.' بانتظار التحويل عبر كليك. الرجاء تنفيذ التحويل ثم تزويدنا بمرجع التحويل عند المتابعة.';
          } elseif ($method === 'stripe' || $method === 'paypal') {
            // توجيه لصفحة بدء الدفع عبر البوابات الخارجية
            // Store order details for payment page
            $_SESSION['payment_order'] = [
              'id' => $orderId,
              'total' => $total,
              'gateway' => $method
            ];
            // Show payment initiation form
            $showPaymentForm = true;
          } else { // card
            // تحويل وهمي إلى صفحة بدء الدفع الفعلي
            header('Location: payment_init.php?order='.$orderId);
            exit;
          }

        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $err = 'تعذر إنشاء الطلب حالياً: '.$e->getMessage();
        }
      }
    }
  }
}

// واجهة
$title = 'إتمام الشراء';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title><?= e($title) ?> | <?= e($config['app_name'] ?? 'المتجر') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#f5f7fa;}
    .pay-box{display:none;}
    .pay-box.active{display:block;}
  </style>
</head>
<body>
<div class="container py-4" style="max-width:1100px;">
  <h1 class="h5 mb-3">إتمام الشراء</h1>
  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <?php if ($orderCreated && $orderId && $_SERVER['REQUEST_METHOD']==='POST' && $_POST['payment_method']==='cod'): ?>
    <a href="index.php" class="btn btn-primary rounded-pill"><i class="bi bi-shop"></i> متابعة التسوق</a>
    <a href="order_view.php?id=<?= (int)$orderId ?>" class="btn btn-outline-secondary rounded-pill">عرض الطلب</a>
  <?php elseif ($orderCreated && $_POST['payment_method']==='cliq'): ?>
    <div class="card p-3 mt-3">
      <h2 class="h6">تعليمات الدفع كليك (CliQ)</h2>
      <ol class="small mb-3">
        <li>افتح تطبيق البنك واختر التحويل الفوري (CliQ).</li>
        <li>أدخل رقم IBAN أو حساب الاستقبال: <strong><?= e($_POST['cliq_iban']) ?></strong></li>
        <li>أدخل المبلغ: <strong><?= e(number_format($total,2)) ?> دينار</strong></li>
        <li>اكتب مرجع التحويل (Reference) نفسه الذي أدخلته هنا: <strong><?= e($_POST['cliq_reference']) ?></strong></li>
      </ol>
      <p class="small text-muted">بعد اكتمال التحويل سيتم تحديث حالة الطلب إلى مدفوع يدوياً.</p>
      <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill">الرئيسية</a>
      <a href="order_view.php?id=<?= (int)$orderId ?>" class="btn btn-secondary btn-sm rounded-pill">تفاصيل الطلب</a>
    </div>
  <?php elseif ($showPaymentForm && $orderId): ?>
    <!-- صفحة تأكيد الدفع عبر Stripe/PayPal -->
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-credit-card"></i> إتمام الدفع</h5>
          </div>
          <div class="card-body">
            <div class="alert alert-info">
              <strong>رقم الطلب:</strong> #<?= (int)$orderId ?><br>
              <strong>المبلغ:</strong> <?= price_fmt($total) ?>
            </div>
            
            <?php $paymentGateway = $_SESSION['payment_order']['gateway'] ?? $_POST['payment_method']; ?>
            
            <form method="post" action="pay.php">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
              <input type="hidden" name="gateway" value="<?= e($paymentGateway) ?>">
              
              <?php if ($paymentGateway === 'stripe'): ?>
                <div class="text-center mb-3">
                  <i class="bi bi-credit-card-2-front" style="font-size: 3rem; color: #635bff;"></i>
                  <p class="mt-2">ستتم إعادة توجيهك إلى صفحة الدفع الآمنة عبر Stripe</p>
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-pill">
                  <i class="bi bi-lock-fill"></i> الدفع الآن عبر Stripe
                </button>
              <?php elseif ($paymentGateway === 'paypal'): ?>
                <div class="text-center mb-3">
                  <i class="bi bi-paypal" style="font-size: 3rem; color: #003087;"></i>
                  <p class="mt-2">ستتم إعادة توجيهك إلى PayPal لإتمام الدفع</p>
                </div>
                <button type="submit" class="btn btn-warning w-100 rounded-pill" style="background-color: #ffc439; border-color: #ffc439; color: #003087;">
                  <i class="bi bi-paypal"></i> الدفع عبر PayPal
                </button>
              <?php endif; ?>
            </form>
            
            <div class="mt-3 text-center">
              <a href="order_view.php?id=<?= (int)$orderId ?>" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-eye"></i> عرض الطلب
              </a>
              <a href="index.php" class="btn btn-link">العودة للمتجر</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>

    <?php if (!$items): ?>
      <div class="alert alert-info">سلتك فارغة. <a href="index.php">العودة للمتجر</a></div>
    <?php else: ?>
      <div class="row g-4">
        <div class="col-lg-7">
          <form method="post" id="checkoutForm" class="card p-3">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
              <label class="form-label">الاسم الكامل</label>
              <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">الهاتف</label>
              <input type="tel" name="phone" class="form-control" required value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">العنوان</label>
              <textarea name="address" rows="3" class="form-control" required><?= e($_POST['address'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">طريقة الدفع</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmCod" value="cod" <?= empty($_POST['payment_method'])||$_POST['payment_method']==='cod'?'checked':'' ?>>
                <label class="form-check-label" for="pmCod">الدفع عند التوصيل (COD)</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmCliq" value="cliq" <?= isset($_POST['payment_method'])&&$_POST['payment_method']==='cliq'?'checked':'' ?>>
                <label class="form-check-label" for="pmCliq">الدفع كليك (تحويل فوري)</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmCard" value="card" <?= isset($_POST['payment_method'])&&$_POST['payment_method']==='card'?'checked':'' ?>>
                <label class="form-check-label" for="pmCard">بطاقة فيزا / ماستر كارد (نموذج محلي)</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmStripe" value="stripe" <?= isset($_POST['payment_method'])&&$_POST['payment_method']==='stripe'?'checked':'' ?>>
                <label class="form-check-label" for="pmStripe">
                  <i class="bi bi-credit-card-2-front"></i> Stripe (Visa/MasterCard)
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmPaypal" value="paypal" <?= isset($_POST['payment_method'])&&$_POST['payment_method']==='paypal'?'checked':'' ?>>
                <label class="form-check-label" for="pmPaypal">
                  <i class="bi bi-paypal"></i> PayPal
                </label>
              </div>
            </div>

            <!-- صندوق CliQ -->
            <div class="pay-box <?= isset($_POST['payment_method'])&&$_POST['payment_method']==='cliq'?'active':'' ?>" id="boxCliq">
              <h6 class="fw-semibold">بيانات الدفع كليك</h6>
              <div class="mb-2">
                <label class="form-label">رقم الحساب / IBAN المستلم</label>
                <input type="text" name="cliq_iban" class="form-control" placeholder="مثال: JO12BANK00001234567890123456" value="<?= e($_POST['cliq_iban'] ?? '') ?>">
              </div>
              <div class="mb-2">
                <label class="form-label">مرجع التحويل (Reference)</label>
                <input type="text" name="cliq_reference" class="form-control" placeholder="رقم فريد تستخدمه بالتحويل" value="<?= e($_POST['cliq_reference'] ?? '') ?>">
              </div>
              <div class="small text-muted mb-3">بعد التحويل سنراجع ونعتمد الدفع.</div>
            </div>

            <!-- صندوق البطاقة -->
            <div class="pay-box <?= isset($_POST['payment_method'])&&$_POST['payment_method']==='card'?'active':'' ?>" id="boxCard">
              <h6 class="fw-semibold">بيانات البطاقة (مثال تجريبي)</h6>
              <div class="mb-2">
                <label class="form-label">اسم حامل البطاقة</label>
                <input type="text" name="card_holder" class="form-control" value="<?= e($_POST['card_holder'] ?? '') ?>">
              </div>
              <div class="mb-2">
                <label class="form-label">رقم البطاقة</label>
                <input type="text" name="card_number" inputmode="numeric" class="form-control" placeholder="4111 1111 1111 1111" value="<?= e($_POST['card_number'] ?? '') ?>">
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">تاريخ الانتهاء (MM/YY)</label>
                  <input type="text" name="card_exp" class="form-control" placeholder="12/27" value="<?= e($_POST['card_exp'] ?? '') ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">CVV</label>
                  <input type="text" name="card_cvv" class="form-control" placeholder="123" value="<?= e($_POST['card_cvv'] ?? '') ?>">
                </div>
              </div>
              <div class="alert alert-warning mt-3 mb-2 p-2 small">
                هذا نموذج للتجربة فقط. في الإنتاج يجب إرسال بيانات البطاقة لبوابة دفع آمنة وعدم تخزينها في قاعدة البيانات.
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-success rounded-pill"><i class="bi bi-check2-circle"></i> تأكيد الطلب</button>
              <a href="cart.php" class="btn btn-outline-secondary rounded-pill">عودة للسلة</a>
            </div>
          </form>
        </div>

        <div class="col-lg-5">
          <div class="card p-3">
            <h6 class="fw-semibold mb-2">ملخص السلة</h6>
            <ul class="list-group mb-3">
              <?php foreach ($items as $it): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold"><?= e($it['name']) ?></div>
                    <div class="small text-muted">الكمية: <?= (int)$it['qty'] ?> × <?= price_fmt($it['price']) ?></div>
                  </div>
                  <div><?= price_fmt($it['subtotal']) ?></div>
                </li>
              <?php endforeach; ?>
              <li class="list-group-item d-flex justify-content-between">
                <span class="fw-bold">الإجمالي</span>
                <span class="fw-bold"><?= price_fmt($total) ?></span>
              </li>
            </ul>
            <div class="small text-muted">الأسعار معطاة لأغراض التوضيح. أضف ضريبة/شحن لاحقاً.</div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const radios = document.querySelectorAll('input[name="payment_method"]');
  const boxCliq = document.getElementById('boxCliq');
  const boxCard = document.getElementById('boxCard');

  function updateBoxes() {
    const val = document.querySelector('input[name="payment_method"]:checked')?.value;
    boxCliq.classList.toggle('active', val === 'cliq');
    boxCard.classList.toggle('active', val === 'card');
  }
  radios.forEach(r => r.addEventListener('change', updateBoxes));
  updateBoxes();
</script>
</body>
</html>