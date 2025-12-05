<?php
// includes/cart.php
// سلة تسوق مبنية على الـ session مع دوال مرتّبة وآمنة.
// PHP 7.4+
//
// ملاحظات:
// - يفضّل استخدام cart_add_by_id(PDO $pdo, int $productId, int $qty) لقراءة الاسم/السعر من DB.
// - القيم تُحفظ في $_SESSION['cart'] بالشكل: [ product_id => ['name'=>'', 'price'=>float, 'qty'=>int] ]
// - استخدم cart_totals() للحصول على {count, total}.
// - لا تطبع أي شيء هنا. استخدمه من داخل صفحاتك أو Endpoints AJAX.

/** ابدأ الجلسة إن لم تكن بدأت */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** أقصى كمية مسموحة لكل منتج (قابل للتعديل حسب احتياجك) */
const CART_MAX_QTY_PER_ITEM = 999;

/** استرجاع السلة كاملة */
function cart_get(): array {
    return $_SESSION['cart'] ?? [];
}

/** تفريغ السلة */
function cart_clear(): void {
    unset($_SESSION['cart']);
}

/**
 * إضافة عنصر للسلة عبر تمرير البيانات يدويًا (اسم/سعر).
 * ملاحظة: يفضّل استخدام cart_add_by_id بدلًا منها.
 */
function cart_add_item(int $productId, string $name, float $price, int $qty = 1): void {
    $productId = max(0, (int)$productId);
    $qty = max(1, (int)$qty);
    $price = (float)$price;

    if ($productId === 0) return;

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = [
            'name'  => (string)$name,
            'price' => $price,
            'qty'   => 0
        ];
    }

    $newQty = $_SESSION['cart'][$productId]['qty'] + $qty;
    $_SESSION['cart'][$productId]['qty'] = (int)min($newQty, CART_MAX_QTY_PER_ITEM);
}

/**
 * إضافة عنصر للسلة بالاعتماد على قاعدة البيانات (موصى به)
 * يبحث المنتج في جدول products ويقرأ الاسم/السعر الحقيقيين.
 *
 * @param PDO $pdo اتصال PDO مضبوط مسبقًا
 * @param int $productId معرّف المنتج
 * @param int $qty الكمية المضافة (>=1)
 * @return bool true إذا تمت الإضافة، false إذا المنتج غير موجود/غير متاح
 */
function cart_add_by_id(PDO $pdo, int $productId, int $qty = 1): bool {
    $productId = max(0, (int)$productId);
    $qty = max(1, (int)$qty);
    if ($productId === 0) return false;

    // عدّل الاستعلام وفقًا لهيكل جدولك (الحقلان name/price شائعان)
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $productId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) return false;

    cart_add_item((int)$p['id'], (string)$p['name'], (float)$p['price'], $qty);
    return true;
}

/** تحديث كمية عنصر معيّن (0 يحذفه) */
function cart_update(int $productId, int $qty): void {
    $productId = max(0, (int)$productId);
    $qty = max(0, (int)$qty);

    if (!isset($_SESSION['cart'][$productId])) return;

    if ($qty === 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }

    $_SESSION['cart'][$productId]['qty'] = (int)min($qty, CART_MAX_QTY_PER_ITEM);
}

/** حذف عنصر من السلة */
function cart_remove(int $productId): void {
    $productId = max(0, (int)$productId);
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

/** إرجاع العدد الإجمالي للعناصر والمجموع المالي */
function cart_totals(): array {
    $count = 0;
    $total = 0.0;

    foreach (cart_get() as $row) {
        $qty = (int)($row['qty'] ?? 0);
        $price = (float)($row['price'] ?? 0.0);
        $count += $qty;
        $total += ($qty * $price);
    }

    // أعد كلاً من العدد والمجموع (بدون ضرائب/شحن — أضفهما في طبقة الحساب النهائية إن احتجت)
    return ['count' => $count, 'total' => (float)$total];
}

/**
 * جلب عناصر السلة مع بيانات حديثة من DB (اختياري)
 * تُفيد إذا أردت عرض صورة/توفر المنتج بما يتوافق مع DB الحالية.
 *
 * @return array [ [product_id, name, price, qty, db_row?], ... ]
 */
function cart_items_with_fresh_db(PDO $pdo): array {
    $items = cart_get();
    if (!$items) return [];

    $ids = array_map('intval', array_keys($items));
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT id, name, price FROM products WHERE id IN ($in)";
    $stm = $pdo->prepare($sql);
    $stm->execute($ids);
    $rows = [];
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $pid = (int)$r['id'];
        if (!isset($items[$pid])) continue;
        $row = $items[$pid];
        // price هنا هو سعر السلة المخزّن؛ يمكنك اختيار الكتابة فوقه بسعر DB لو أردت
        $rows[] = [
            'product_id' => $pid,
            'name'       => $row['name'],
            'price'      => $row['price'],
            'qty'        => $row['qty'],
            'db_row'     => $r, // معلومات حديثة من DB (اختياري لعرض صورة/توفر/سعر محدث)
        ];
    }
    return $rows;
}
