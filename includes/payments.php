<?php
/**
 * المسار: includes/payments.php
 * 
 * ملف دوال الدفع - يحتوي على دوال التكامل مع Stripe و PayPal
 * 
 * الدوال المتوفرة:
 * - Stripe: createStripeCheckoutSession(), verifyStripeWebhook()
 * - PayPal: createPayPalOrder(), capturePayPalOrder(), verifyPayPalWebhook()
 * - Database: savePaymentRecord(), updatePaymentStatus(), getPaymentByOrderId()
 */

declare(strict_types=1);

// تحميل Composer autoloader إن وجد
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

/**
 * الحصول على إعدادات بوابة الدفع
 */
function getPaymentConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

/**
 * الحصول على base URL للتطبيق
 */
function getBaseUrl(): string
{
    $config = getPaymentConfig();
    return rtrim($config['base_url'] ?? '', '/');
}

// ============================================================
// STRIPE FUNCTIONS
// ============================================================

/**
 * إنشاء جلسة Stripe Checkout
 * 
 * @param int $orderId معرّف الطلب
 * @param array $items عناصر السلة
 * @param float $total المبلغ الإجمالي
 * @param string $currency العملة (افتراضياً USD)
 * @return array ['success' => bool, 'checkout_url' => string, 'session_id' => string, 'error' => string]
 */
function createStripeCheckoutSession(int $orderId, array $items, float $total, string $currency = 'USD'): array
{
    $config = getPaymentConfig();
    $stripeSecretKey = $config['stripe']['secret_key'] ?? '';
    
    if (empty($stripeSecretKey)) {
        return [
            'success' => false,
            'error' => 'Stripe secret key is not configured'
        ];
    }

    // استخدام Stripe SDK إن وجد
    if (class_exists('\Stripe\Stripe')) {
        return createStripeCheckoutWithSDK($orderId, $items, $total, $currency, $stripeSecretKey);
    }

    // استخدام cURL كبديل
    return createStripeCheckoutWithCurl($orderId, $items, $total, $currency, $stripeSecretKey);
}

/**
 * إنشاء جلسة Stripe باستخدام SDK
 */
function createStripeCheckoutWithSDK(int $orderId, array $items, float $total, string $currency, string $secretKey): array
{
    try {
        \Stripe\Stripe::setApiKey($secretKey);
        
        $baseUrl = getBaseUrl();
        
        // تحضير عناصر السلة لـ Stripe
        $lineItems = [];
        foreach ($items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => $item['name'] ?? 'Product',
                    ],
                    'unit_amount' => (int) round(($item['price'] ?? 0) * 100), // Stripe يستخدم السنتات
                ],
                'quantity' => (int) ($item['qty'] ?? 1),
            ];
        }
        
        $checkoutSession = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $baseUrl . '/public/stripe_return.php?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
            'cancel_url' => $baseUrl . '/public/checkout.php?cancelled=1&order_id=' . $orderId,
            'metadata' => [
                'order_id' => (string) $orderId,
            ],
            'client_reference_id' => (string) $orderId,
        ]);
        
        return [
            'success' => true,
            'checkout_url' => $checkoutSession->url,
            'session_id' => $checkoutSession->id,
        ];
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'error' => 'Stripe error: ' . $e->getMessage()
        ];
    }
}

/**
 * إنشاء جلسة Stripe باستخدام cURL (بديل للـ SDK)
 */
function createStripeCheckoutWithCurl(int $orderId, array $items, float $total, string $currency, string $secretKey): array
{
    $baseUrl = getBaseUrl();
    
    // تحضير البيانات
    $data = [
        'payment_method_types[]' => 'card',
        'mode' => 'payment',
        'success_url' => $baseUrl . '/public/stripe_return.php?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
        'cancel_url' => $baseUrl . '/public/checkout.php?cancelled=1&order_id=' . $orderId,
        'metadata[order_id]' => (string) $orderId,
        'client_reference_id' => (string) $orderId,
    ];
    
    // إضافة العناصر
    $i = 0;
    foreach ($items as $item) {
        $data["line_items[$i][price_data][currency]"] = strtolower($currency);
        $data["line_items[$i][price_data][product_data][name]"] = $item['name'] ?? 'Product';
        $data["line_items[$i][price_data][unit_amount]"] = (int) round(($item['price'] ?? 0) * 100);
        $data["line_items[$i][quantity]"] = (int) ($item['qty'] ?? 1);
        $i++;
    }
    
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['url'])) {
        return [
            'success' => true,
            'checkout_url' => $result['url'],
            'session_id' => $result['id'] ?? '',
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['error']['message'] ?? 'Unknown Stripe error'
    ];
}

/**
 * استرجاع جلسة Stripe Checkout
 */
function retrieveStripeSession(string $sessionId): array
{
    $config = getPaymentConfig();
    $secretKey = $config['stripe']['secret_key'] ?? '';
    
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Stripe not configured'];
    }
    
    if (class_exists('\Stripe\Stripe')) {
        try {
            \Stripe\Stripe::setApiKey($secretKey);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            return [
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'payment_intent' => $session->payment_intent,
                    'amount_total' => $session->amount_total,
                    'currency' => $session->currency,
                    'customer_email' => $session->customer_details->email ?? null,
                    'metadata' => (array) $session->metadata,
                ]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // استخدام cURL كبديل
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['id'])) {
        return [
            'success' => true,
            'session' => [
                'id' => $result['id'],
                'payment_status' => $result['payment_status'] ?? 'unpaid',
                'payment_intent' => $result['payment_intent'] ?? null,
                'amount_total' => $result['amount_total'] ?? 0,
                'currency' => $result['currency'] ?? 'usd',
                'customer_email' => $result['customer_details']['email'] ?? null,
                'metadata' => $result['metadata'] ?? [],
            ]
        ];
    }
    
    return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to retrieve session'];
}

/**
 * التحقق من توقيع Stripe Webhook
 */
function verifyStripeWebhook(string $payload, string $signature): array
{
    $config = getPaymentConfig();
    $webhookSecret = $config['stripe']['webhook_secret'] ?? '';
    
    if (empty($webhookSecret)) {
        return ['valid' => false, 'error' => 'Webhook secret not configured'];
    }
    
    if (class_exists('\Stripe\Webhook')) {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
            return [
                'valid' => true,
                'event' => [
                    'type' => $event->type,
                    'data' => $event->data->object->toArray(),
                ]
            ];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['valid' => false, 'error' => 'Invalid signature: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
    
    // التحقق اليدوي من التوقيع
    $elements = explode(',', $signature);
    $sigData = [];
    foreach ($elements as $element) {
        [$key, $value] = explode('=', $element, 2);
        $sigData[trim($key)] = trim($value);
    }
    
    $timestamp = $sigData['t'] ?? '';
    $v1Signature = $sigData['v1'] ?? '';
    
    if (empty($timestamp) || empty($v1Signature)) {
        return ['valid' => false, 'error' => 'Invalid signature format'];
    }
    
    // التحقق من عمر الطلب (5 دقائق كحد أقصى)
    if (abs(time() - (int) $timestamp) > 300) {
        return ['valid' => false, 'error' => 'Timestamp too old'];
    }
    
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
    
    if (!hash_equals($expectedSignature, $v1Signature)) {
        return ['valid' => false, 'error' => 'Signature mismatch'];
    }
    
    $event = json_decode($payload, true);
    return [
        'valid' => true,
        'event' => [
            'type' => $event['type'] ?? '',
            'data' => $event['data']['object'] ?? [],
        ]
    ];
}

// ============================================================
// PAYPAL FUNCTIONS
// ============================================================

/**
 * الحصول على PayPal API base URL
 */
function getPayPalBaseUrl(): string
{
    $config = getPaymentConfig();
    $mode = $config['paypal']['mode'] ?? 'sandbox';
    return $mode === 'live' 
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

/**
 * الحصول على PayPal Access Token
 */
function getPayPalAccessToken(): array
{
    $config = getPaymentConfig();
    $clientId = $config['paypal']['client_id'] ?? '';
    $clientSecret = $config['paypal']['client_secret'] ?? '';
    
    if (empty($clientId) || empty($clientSecret)) {
        return ['success' => false, 'error' => 'PayPal credentials not configured'];
    }
    
    $baseUrl = getPayPalBaseUrl();
    
    $ch = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['access_token'])) {
        return [
            'success' => true,
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'] ?? 32400,
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['error_description'] ?? 'Failed to get PayPal access token'
    ];
}

/**
 * إنشاء طلب PayPal
 * 
 * @param int $orderId معرّف الطلب
 * @param array $items عناصر السلة
 * @param float $total المبلغ الإجمالي
 * @param string $currency العملة
 * @return array ['success' => bool, 'approval_url' => string, 'paypal_order_id' => string, 'error' => string]
 */
function createPayPalOrder(int $orderId, array $items, float $total, string $currency = 'USD'): array
{
    $tokenResult = getPayPalAccessToken();
    if (!$tokenResult['success']) {
        return $tokenResult;
    }
    
    $accessToken = $tokenResult['access_token'];
    $baseUrl = getPayPalBaseUrl();
    $appBaseUrl = getBaseUrl();
    
    // تحضير عناصر الطلب
    $paypalItems = [];
    $itemsTotal = 0;
    
    foreach ($items as $item) {
        $unitPrice = round((float) ($item['price'] ?? 0), 2);
        $qty = (int) ($item['qty'] ?? 1);
        $itemsTotal += $unitPrice * $qty;
        
        $paypalItems[] = [
            'name' => mb_substr($item['name'] ?? 'Product', 0, 127),
            'quantity' => (string) $qty,
            'unit_amount' => [
                'currency_code' => $currency,
                'value' => number_format($unitPrice, 2, '.', ''),
            ],
        ];
    }
    
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => (string) $orderId,
                'description' => 'Order #' . $orderId,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($total, 2, '.', ''),
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => $currency,
                            'value' => number_format($itemsTotal, 2, '.', ''),
                        ],
                    ],
                ],
                'items' => $paypalItems,
            ],
        ],
        'application_context' => [
            'brand_name' => getPaymentConfig()['app_name'] ?? 'Store',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'return_url' => $appBaseUrl . '/public/paypal_return.php?order_id=' . $orderId,
            'cancel_url' => $appBaseUrl . '/public/checkout.php?cancelled=1&order_id=' . $orderId,
        ],
    ];
    
    $ch = curl_init($baseUrl . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($orderData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'PayPal-Request-Id: order-' . $orderId . '-' . time(),
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['id'])) {
        // البحث عن رابط الموافقة
        $approvalUrl = '';
        foreach ($result['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        return [
            'success' => true,
            'paypal_order_id' => $result['id'],
            'approval_url' => $approvalUrl,
            'status' => $result['status'] ?? 'CREATED',
        ];
    }
    
    $errorMessage = 'PayPal error';
    if (!empty($result['details'])) {
        $errorMessage = implode(', ', array_map(fn($d) => $d['description'] ?? $d['issue'] ?? '', $result['details']));
    } elseif (!empty($result['message'])) {
        $errorMessage = $result['message'];
    }
    
    return ['success' => false, 'error' => $errorMessage];
}

/**
 * تنفيذ/استلام PayPal Order
 */
function capturePayPalOrder(string $paypalOrderId): array
{
    $tokenResult = getPayPalAccessToken();
    if (!$tokenResult['success']) {
        return $tokenResult;
    }
    
    $accessToken = $tokenResult['access_token'];
    $baseUrl = getPayPalBaseUrl();
    
    $ch = curl_init($baseUrl . '/v2/checkout/orders/' . urlencode($paypalOrderId) . '/capture');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && ($result['status'] ?? '') === 'COMPLETED') {
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];
        return [
            'success' => true,
            'paypal_order_id' => $result['id'],
            'transaction_id' => $capture['id'] ?? $result['id'],
            'status' => $result['status'],
            'amount' => $capture['amount']['value'] ?? '0.00',
            'currency' => $capture['amount']['currency_code'] ?? 'USD',
            'payer_email' => $result['payer']['email_address'] ?? null,
        ];
    }
    
    $errorMessage = 'PayPal capture failed';
    if (!empty($result['details'])) {
        $errorMessage = implode(', ', array_map(fn($d) => $d['description'] ?? $d['issue'] ?? '', $result['details']));
    } elseif (!empty($result['message'])) {
        $errorMessage = $result['message'];
    }
    
    return [
        'success' => false,
        'error' => $errorMessage,
        'status' => $result['status'] ?? 'UNKNOWN'
    ];
}

/**
 * الحصول على تفاصيل طلب PayPal
 */
function getPayPalOrderDetails(string $paypalOrderId): array
{
    $tokenResult = getPayPalAccessToken();
    if (!$tokenResult['success']) {
        return $tokenResult;
    }
    
    $accessToken = $tokenResult['access_token'];
    $baseUrl = getPayPalBaseUrl();
    
    $ch = curl_init($baseUrl . '/v2/checkout/orders/' . urlencode($paypalOrderId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['id'])) {
        return ['success' => true, 'order' => $result];
    }
    
    return ['success' => false, 'error' => $result['message'] ?? 'Failed to get order details'];
}

/**
 * التحقق من صحة PayPal Webhook
 */
function verifyPayPalWebhook(string $payload, array $headers): array
{
    $config = getPaymentConfig();
    
    // في بيئة sandbox يمكن تخطي التحقق
    if (($config['paypal']['mode'] ?? 'sandbox') === 'sandbox') {
        $event = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valid' => false, 'error' => 'Invalid JSON payload'];
        }
        return [
            'valid' => true,
            'event' => [
                'type' => $event['event_type'] ?? '',
                'data' => $event['resource'] ?? [],
            ],
            'warning' => 'Webhook verification skipped in sandbox mode'
        ];
    }
    
    // التحقق الكامل في بيئة الإنتاج
    $tokenResult = getPayPalAccessToken();
    if (!$tokenResult['success']) {
        return ['valid' => false, 'error' => 'Failed to get access token'];
    }
    
    $baseUrl = getPayPalBaseUrl();
    
    $verifyPayload = [
        'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
        'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
        'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'webhook_id' => $config['paypal']['webhook_id'] ?? '',
        'webhook_event' => json_decode($payload, true),
    ];
    
    $ch = curl_init($baseUrl . '/v1/notifications/verify-webhook-signature');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($verifyPayload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $tokenResult['access_token'],
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && ($result['verification_status'] ?? '') === 'SUCCESS') {
        $event = json_decode($payload, true);
        return [
            'valid' => true,
            'event' => [
                'type' => $event['event_type'] ?? '',
                'data' => $event['resource'] ?? [],
            ]
        ];
    }
    
    return ['valid' => false, 'error' => 'Webhook signature verification failed'];
}

// ============================================================
// DATABASE FUNCTIONS
// ============================================================

/**
 * حفظ سجل دفع جديد
 */
function savePaymentRecord(PDO $pdo, int $orderId, string $gateway, float $amount, string $currency = 'USD', string $status = 'pending', ?string $transactionId = null, ?array $gatewayResponse = null): int
{
    $stmt = $pdo->prepare("
        INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, gateway_response, created_at)
        VALUES (:order_id, :gateway, :transaction_id, :amount, :currency, :status, :gateway_response, NOW())
    ");
    
    $stmt->execute([
        ':order_id' => $orderId,
        ':gateway' => $gateway,
        ':transaction_id' => $transactionId,
        ':amount' => $amount,
        ':currency' => $currency,
        ':status' => $status,
        ':gateway_response' => $gatewayResponse ? json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE) : null,
    ]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * تحديث حالة الدفع
 */
function updatePaymentStatus(PDO $pdo, int $paymentId, string $status, ?string $transactionId = null, ?array $gatewayResponse = null): bool
{
    $sql = "UPDATE payments SET status = :status, updated_at = NOW()";
    $params = [':status' => $status, ':id' => $paymentId];
    
    if ($transactionId !== null) {
        $sql .= ", transaction_id = :transaction_id";
        $params[':transaction_id'] = $transactionId;
    }
    
    if ($gatewayResponse !== null) {
        $sql .= ", gateway_response = :gateway_response";
        $params[':gateway_response'] = json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE);
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * الحصول على سجل دفع بمعرّف الطلب
 */
function getPaymentByOrderId(PDO $pdo, int $orderId, ?string $gateway = null): ?array
{
    $sql = "SELECT * FROM payments WHERE order_id = :order_id";
    $params = [':order_id' => $orderId];
    
    if ($gateway !== null) {
        $sql .= " AND gateway = :gateway";
        $params[':gateway'] = $gateway;
    }
    
    $sql .= " ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * الحصول على سجل دفع بمعرّف المعاملة
 */
function getPaymentByTransactionId(PDO $pdo, string $transactionId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_id = :transaction_id LIMIT 1");
    $stmt->execute([':transaction_id' => $transactionId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * تحديث حالة الدفع في جدول الطلبات
 */
function updateOrderPaymentStatus(PDO $pdo, int $orderId, string $paymentStatus, ?string $gateway = null, ?string $transactionId = null): bool
{
    $sql = "UPDATE orders SET payment_status = :payment_status, updated_at = NOW()";
    $params = [':payment_status' => $paymentStatus, ':id' => $orderId];
    
    if ($gateway !== null) {
        $sql .= ", gateway = :gateway";
        $params[':gateway'] = $gateway;
    }
    
    if ($transactionId !== null) {
        $sql .= ", transaction_id = :transaction_id";
        $params[':transaction_id'] = $transactionId;
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * الحصول على طلب مع بيانات الدفع
 */
function getOrderWithPayment(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare("
        SELECT o.*, p.id as payment_id, p.transaction_id as payment_transaction_id, 
               p.status as payment_record_status, p.gateway_response
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.id
        WHERE o.id = :id
        ORDER BY p.id DESC
        LIMIT 1
    ");
    $stmt->execute([':id' => $orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}
