<?php
// includes/payments.php
// دوال مساعدة لإنشاء جلسات Stripe وطلبات PayPal وتسجيل المدفوعات.
// يتوقع هذا الملف أن يكون $pdo معرفًا (PDO instance) في includes/db.php

// تحميل composer autoload إن وجد (يُستخدم لStripe SDK)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * site_url - يبني رابطًا نسبيًا صحيحًا حتى لو كان التطبيق داخل مجلد فرعي (مثل /xx/public)
 * - يُعيد رابطًا يعتمد على SCRIPT_NAME ويحاول التعامل مع وجود مجلد public.
 * - يستقبل $path إما مسار يبدأ بـ'/' أو مسار نسبي.
 */
function site_url($path = '/') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    // مثال SCRIPT_NAME = /xx/public/payment_init.php  -> نريد base = https://host/xx/public
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // استخدم scriptDir مباشرة كوننا نخدم من مجلد public؛ هذا يجعل URLs تعمل داخل نفس مجلد public
    $base = rtrim($protocol . '://' . $host . $scriptDir, '/');

    // إذا path يبدأ بـ '/' نفترض أنه بالنسبة لمجلد التطبيق root under public, فنبنيه فوق base
    if (strpos($path, '/') === 0) {
        return $base . $path;
    } else {
        return $base . '/' . ltrim($path, '/');
    }
}

/**
 * write_log - يسجل رسائل بسيطة إلى storage/logs/payments.log إن أمكن
 */
function write_log($msg) {
    $logDir = __DIR__ . '/../storage/logs';
    @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/payments.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * record_payment - سجل دفعة في جدول payments (يعيد id السجل)
 */
function record_payment($pdo, $order_id, $gateway, $transaction_id, $amount, $currency, $status = 'pending', $raw_response = null) {
    try {
        $sql = "INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, raw_response) 
                VALUES (:order_id, :gateway, :transaction_id, :amount, :currency, :status, :raw_response)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':order_id' => $order_id,
            ':gateway' => $gateway,
            ':transaction_id' => $transaction_id,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $status,
            ':raw_response' => $raw_response
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        write_log("record_payment error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * update_payment_status - تحديث سجل الدفع والحالة في جدول orders عند الحاجة
 */
function update_payment_status($pdo, $order_id, $gateway, $status = 'paid', $transaction_id = null, $raw_response = null) {
    try {
        // تحديث جدول payments: آخر سجل للـ order_id و gateway
        $sql = "UPDATE payments SET status = :status, transaction_id = COALESCE(:tx, transaction_id), raw_response = :raw, updated_at = NOW()
                WHERE order_id = :order_id AND gateway = :gateway
                ORDER BY created_at DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':tx' => $transaction_id,
            ':raw' => $raw_response,
            ':order_id' => $order_id,
            ':gateway' => $gateway
        ]);

        // إذا الحالة مدفوعة نحدّث الطلب
        if ($status === 'paid') {
            $stmt2 = $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'completed' WHERE id = :id");
            $stmt2->execute([':id' => $order_id]);
        }
        return true;
    } catch (Throwable $e) {
        write_log("update_payment_status error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * get_order_currency_safe - يعيد عملة الطلب أو DEFAULT_CURRENCY إذا لم تكن موجودة
 */
function get_order_currency_safe($order) {
    if (!empty($order['currency'])) {
        return $order['currency'];
    }
    if (defined('DEFAULT_CURRENCY')) {
        return DEFAULT_CURRENCY;
    }
    return 'USD';
}

/**
 * create_stripe_session - ينشئ Stripe Checkout Session (يتطلب مكتبة stripe/stripe-php)
 * يعيد رابط إعادة التوجيه إلى Stripe Checkout.
 */
function create_stripe_session($pdo, $order) {
    if (!isset($order['id'])) throw new Exception('Order id missing');
    $amountCents = (int) round($order['total'] * 100);
    $currency = strtolower(get_order_currency_safe($order));

    if (!class_exists('\Stripe\Stripe')) {
        write_log('Stripe SDK not installed.');
        throw new Exception('Stripe SDK not installed. Run: composer require stripe/stripe-php');
    }

    try {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        // استخدم site_url بحيث يكون النسبي صحيحًا داخل مجلد public
        $success = site_url('/thank_you.php?session_id={CHECKOUT_SESSION_ID}');
        $cancel = site_url('/checkout.php?canceled=1');

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => ['name' => "Order #{$order['id']}"],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'metadata' => ['order_id' => $order['id']],
        ]);

        // سجل الدفع كمبدئي (نستخدم session id كمعرّف مؤقت)
        record_payment($pdo, $order['id'], 'stripe', $session->id, $order['total'], strtoupper($currency), 'pending', json_encode($session));
        // إرجاع رابط الجلسة إذا توفر أو session id لعرض صفحة تحويل محلياً
        return $session->url ?? $session->id;
    } catch (Throwable $e) {
        write_log("create_stripe_session error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * paypal_get_access_token - يحصل توكن الوصول من PayPal (sandbox/live حسب PAYPAL_MODE)
 */
function paypal_get_access_token($clientId, $secret, $mode) {
    $url = ($mode === 'live') ? 'https://api-m.paypal.com/v1/oauth2/token' : 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        $data = json_decode($resp, true);
        return $data['access_token'] ?? null;
    }
    write_log("paypal_get_access_token HTTP $code - $resp");
    throw new Exception("PayPal token error: HTTP $code");
}

/**
 * create_paypal_order - ينشئ طلب دفع في PayPal ويعيد رابط الموافقة
 */
function create_paypal_order($pdo, $order) {
    if (!isset($order['id'])) throw new Exception('Order id missing');
    $modeBase = PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $token = paypal_get_access_token(PAYPAL_CLIENT_ID, PAYPAL_SECRET, PAYPAL_MODE);

    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string)$order['id'],
            'amount' => [
                'currency_code' => get_order_currency_safe($order),
                'value' => number_format((float)$order['total'], 2, '.', '')
            ]
        ]],
        'application_context' => [
            'cancel_url' => site_url('/checkout.php?canceled=1'),
            'return_url' => site_url('/paypal_return.php')
        ]
    ];

    $ch = curl_init("$modeBase/v2/checkout/orders");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $token"
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $data = json_decode($resp, true);
        $paypalOrderId = $data['id'] ?? null;
        // سجل الدفعة كمبدئي
        record_payment($pdo, $order['id'], 'paypal', $paypalOrderId, $order['total'], get_order_currency_safe($order), 'pending', $resp);
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return $link['href'];
            }
        }
        write_log('create_paypal_order: approval link not found. Response: ' . $resp);
        throw new Exception('Approval URL not found in PayPal response.');
    } else {
        write_log("create_paypal_order HTTP $code - $resp");
        throw new Exception("PayPal create order failed: HTTP $code");
    }
}
?>