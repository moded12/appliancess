<?php
/**
 * includes/payments.php
 * دوال مساعدة لبوابات الدفع (Stripe و PayPal)
 */

declare(strict_types=1);

/**
 * الحصول على رابط الموقع الكامل
 */
function site_url(string $path = ''): string
{
    $config = require __DIR__ . '/../config.php';
    $base = rtrim($config['base_url'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * تسجيل سجل دفع جديد أو تحديث موجود
 */
function record_payment(
    PDO $pdo,
    int $orderId,
    string $gateway,
    ?string $transactionId,
    float $amount,
    string $currency,
    string $status,
    ?array $rawResponse = null
): int {
    // تحقق من وجود سجل سابق لنفس الطلب والبوابة
    $existing = $pdo->prepare(
        "SELECT id FROM payments WHERE order_id = :order_id AND gateway = :gateway LIMIT 1"
    );
    $existing->execute([':order_id' => $orderId, ':gateway' => $gateway]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    $rawJson = $rawResponse ? json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    if ($row) {
        // تحديث السجل الموجود
        $update = $pdo->prepare(
            "UPDATE payments SET 
                transaction_id = :tid,
                amount = :amount,
                currency = :currency,
                status = :status,
                raw_response = :raw,
                updated_at = NOW()
             WHERE id = :id"
        );
        $update->execute([
            ':tid' => $transactionId,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $status,
            ':raw' => $rawJson,
            ':id' => $row['id']
        ]);
        return (int)$row['id'];
    } else {
        // إنشاء سجل جديد
        $insert = $pdo->prepare(
            "INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, raw_response, created_at)
             VALUES (:order_id, :gateway, :tid, :amount, :currency, :status, :raw, NOW())"
        );
        $insert->execute([
            ':order_id' => $orderId,
            ':gateway' => $gateway,
            ':tid' => $transactionId,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $status,
            ':raw' => $rawJson
        ]);
        return (int)$pdo->lastInsertId();
    }
}

/**
 * تحديث حالة الدفع في جدول payments
 */
function update_payment_status(PDO $pdo, int $paymentId, string $status, ?string $transactionId = null, ?array $rawResponse = null): bool
{
    $rawJson = $rawResponse ? json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    
    $sql = "UPDATE payments SET status = :status, updated_at = NOW()";
    $params = [':status' => $status, ':id' => $paymentId];
    
    if ($transactionId !== null) {
        $sql .= ", transaction_id = :tid";
        $params[':tid'] = $transactionId;
    }
    if ($rawJson !== null) {
        $sql .= ", raw_response = :raw";
        $params[':raw'] = $rawJson;
    }
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * تحديث حالة الطلب في جدول orders
 */
function update_order_payment_status(PDO $pdo, int $orderId, string $paymentStatus, ?string $orderStatus = null): bool
{
    $sql = "UPDATE orders SET payment_status = :ps";
    $params = [':ps' => $paymentStatus, ':id' => $orderId];
    
    if ($orderStatus !== null) {
        $sql .= ", status = :os";
        $params[':os'] = $orderStatus;
    }
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * جلب طلب حسب المعرف
 */
function get_order(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    return $order ?: null;
}

/**
 * جلب سجلات الدفع للطلب
 */
function get_payments_for_order(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM payments WHERE order_id = :order_id ORDER BY created_at DESC"
    );
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== Stripe Functions ====================

/**
 * إنشاء جلسة Stripe Checkout
 * يُرجع مصفوفة تحتوي على session_id و checkout_url
 */
function create_stripe_session(PDO $pdo, array $order): array
{
    $config = require __DIR__ . '/../config.php';
    
    $secretKey = $config['stripe_secret_key'] ?? '';
    if (empty($secretKey)) {
        throw new Exception('Stripe secret key is not configured');
    }

    // التحقق من وجود Stripe SDK
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception('Composer autoload not found. Run: composer install');
    }
    require_once $autoloadPath;

    \Stripe\Stripe::setApiKey($secretKey);

    $orderId = (int)$order['id'];
    $amount = (float)$order['total'];
    $currency = strtolower($config['default_payment_currency'] ?? 'usd');

    // تحويل المبلغ إلى أصغر وحدة (سنتات)
    $amountInCents = (int)round($amount * 100);

    // جلب بنود الطلب لعرضها في Stripe Checkout
    $items = $pdo->prepare("SELECT name, price, qty FROM order_items WHERE order_id = :oid");
    $items->execute([':oid' => $orderId]);
    $orderItems = $items->fetchAll(PDO::FETCH_ASSOC);

    $lineItems = [];
    foreach ($orderItems as $item) {
        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => $item['name'],
                ],
                'unit_amount' => (int)round((float)$item['price'] * 100),
            ],
            'quantity' => (int)$item['qty'],
        ];
    }

    // إذا لم توجد بنود، استخدم سطر واحد بالإجمالي
    if (empty($lineItems)) {
        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => 'Order #' . $orderId,
                ],
                'unit_amount' => $amountInCents,
            ],
            'quantity' => 1,
        ];
    }

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => site_url('public/order_view.php?id=' . $orderId . '&payment=success'),
        'cancel_url' => site_url('public/checkout.php?payment=cancelled'),
        'metadata' => [
            'order_id' => (string)$orderId,
        ],
        'client_reference_id' => (string)$orderId,
    ]);

    // تسجيل سجل دفع أولي
    record_payment(
        $pdo,
        $orderId,
        'stripe',
        $session->id,
        $amount,
        strtoupper($currency),
        'pending',
        ['session_id' => $session->id, 'status' => 'created']
    );

    // تحديث حالة الطلب
    update_order_payment_status($pdo, $orderId, 'pending', 'waiting_payment');

    return [
        'session_id' => $session->id,
        'checkout_url' => $session->url,
    ];
}

// ==================== PayPal Functions ====================

/**
 * الحصول على Access Token من PayPal
 */
function paypal_get_access_token(): string
{
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    
    if (empty($clientId) || empty($secret)) {
        throw new Exception('PayPal credentials are not configured');
    }

    $baseUrl = ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('PayPal cURL error: ' . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception('PayPal auth failed with HTTP ' . $httpCode . ': ' . $response);
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        throw new Exception('PayPal auth response missing access_token');
    }

    return $data['access_token'];
}

/**
 * إنشاء طلب PayPal Order
 * يُرجع مصفوفة تحتوي على paypal_order_id و approval_url
 */
function create_paypal_order(PDO $pdo, array $order): array
{
    $config = require __DIR__ . '/../config.php';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    $currency = strtoupper($config['default_payment_currency'] ?? 'USD');
    
    $baseUrl = ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';

    $accessToken = paypal_get_access_token();
    
    $orderId = (int)$order['id'];
    $amount = number_format((float)$order['total'], 2, '.', '');

    // جلب بنود الطلب
    $items = $pdo->prepare("SELECT name, price, qty FROM order_items WHERE order_id = :oid");
    $items->execute([':oid' => $orderId]);
    $orderItems = $items->fetchAll(PDO::FETCH_ASSOC);

    $paypalItems = [];
    $itemTotal = 0;
    foreach ($orderItems as $item) {
        $unitAmount = number_format((float)$item['price'], 2, '.', '');
        $qty = (int)$item['qty'];
        $paypalItems[] = [
            'name' => mb_substr($item['name'], 0, 127),
            'quantity' => (string)$qty,
            'unit_amount' => [
                'currency_code' => $currency,
                'value' => $unitAmount,
            ],
        ];
        $itemTotal += (float)$unitAmount * $qty;
    }

    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'order_' . $orderId,
            'description' => 'Order #' . $orderId,
            'amount' => [
                'currency_code' => $currency,
                'value' => $amount,
                'breakdown' => [
                    'item_total' => [
                        'currency_code' => $currency,
                        'value' => number_format($itemTotal, 2, '.', ''),
                    ],
                ],
            ],
            'items' => $paypalItems,
        ]],
        'application_context' => [
            'brand_name' => $config['app_name'] ?? 'Store',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'return_url' => site_url('public/paypal_return.php?order_id=' . $orderId),
            'cancel_url' => site_url('public/checkout.php?payment=cancelled'),
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/v2/checkout/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Prefer: return=representation',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('PayPal cURL error: ' . $error);
    }

    if ($httpCode !== 201 && $httpCode !== 200) {
        throw new Exception('PayPal create order failed with HTTP ' . $httpCode . ': ' . $response);
    }

    $data = json_decode($response, true);
    if (empty($data['id'])) {
        throw new Exception('PayPal response missing order id');
    }

    // البحث عن رابط الموافقة
    $approvalUrl = null;
    foreach (($data['links'] ?? []) as $link) {
        if ($link['rel'] === 'approve') {
            $approvalUrl = $link['href'];
            break;
        }
    }

    if (!$approvalUrl) {
        throw new Exception('PayPal response missing approval URL');
    }

    // تسجيل سجل دفع أولي
    record_payment(
        $pdo,
        $orderId,
        'paypal',
        $data['id'],
        (float)$amount,
        $currency,
        'pending',
        $data
    );

    // تحديث حالة الطلب
    update_order_payment_status($pdo, $orderId, 'pending', 'waiting_payment');

    return [
        'paypal_order_id' => $data['id'],
        'approval_url' => $approvalUrl,
    ];
}

/**
 * التقاط (Capture) دفعة PayPal بعد موافقة العميل
 */
function capture_paypal_order(PDO $pdo, string $paypalOrderId, int $orderId): array
{
    $config = require __DIR__ . '/../config.php';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    
    $baseUrl = ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';

    $accessToken = paypal_get_access_token();

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Prefer: return=representation',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('PayPal cURL error: ' . $error);
    }

    $data = json_decode($response, true);

    if ($httpCode !== 201 && $httpCode !== 200) {
        // تحديث سجل الدفع بالفشل
        record_payment(
            $pdo,
            $orderId,
            'paypal',
            $paypalOrderId,
            0,
            'USD',
            'failed',
            $data
        );
        update_order_payment_status($pdo, $orderId, 'failed');
        throw new Exception('PayPal capture failed with HTTP ' . $httpCode . ': ' . $response);
    }

    $status = $data['status'] ?? '';
    $captureId = null;
    $capturedAmount = 0;
    $currency = 'USD';

    // استخراج معلومات الـ capture
    if (!empty($data['purchase_units'][0]['payments']['captures'][0])) {
        $capture = $data['purchase_units'][0]['payments']['captures'][0];
        $captureId = $capture['id'] ?? null;
        $capturedAmount = (float)($capture['amount']['value'] ?? 0);
        $currency = $capture['amount']['currency_code'] ?? 'USD';
    }

    $paymentStatus = ($status === 'COMPLETED') ? 'paid' : 'pending';
    $orderPaymentStatus = ($status === 'COMPLETED') ? 'paid' : 'pending';
    $orderStatus = ($status === 'COMPLETED') ? 'processing' : null;

    // تحديث سجل الدفع
    record_payment(
        $pdo,
        $orderId,
        'paypal',
        $captureId ?? $paypalOrderId,
        $capturedAmount,
        $currency,
        $paymentStatus,
        $data
    );

    // تحديث حالة الطلب
    update_order_payment_status($pdo, $orderId, $orderPaymentStatus, $orderStatus);

    return [
        'status' => $status,
        'capture_id' => $captureId,
        'amount' => $capturedAmount,
        'currency' => $currency,
        'raw_response' => $data,
    ];
}

/**
 * التحقق من توقيع Stripe Webhook
 */
function verify_stripe_webhook_signature(string $payload, string $sigHeader): ?\Stripe\Event
{
    $config = require __DIR__ . '/../config.php';
    $webhookSecret = $config['stripe_webhook_secret'] ?? '';
    
    if (empty($webhookSecret)) {
        return null;
    }

    // التحقق من وجود Stripe SDK
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        return null;
    }
    require_once $autoloadPath;

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        return $event;
    } catch (\Exception $e) {
        return null;
    }
}
