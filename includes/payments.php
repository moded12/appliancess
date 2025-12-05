<?php
/**
 * Payment Functions - دوال المدفوعات
 * 
 * يحتوي على دوال التعامل مع بوابات الدفع (Stripe & PayPal)
 * 
 * المسار: includes/payments.php
 */

declare(strict_types=1);

// تحميل الإعدادات
$_payments_config = null;
function get_payments_config(): array {
    global $_payments_config;
    if ($_payments_config === null) {
        $_payments_config = require __DIR__ . '/../config.php';
    }
    return $_payments_config;
}

/**
 * الحصول على رابط الموقع الأساسي
 */
function site_url(string $path = ''): string {
    $config = get_payments_config();
    $base = rtrim($config['base_url'] ?? '', '/');
    if ($path !== '') {
        $path = '/' . ltrim($path, '/');
    }
    return $base . $path;
}

/**
 * تسجيل عملية دفع في قاعدة البيانات
 * 
 * @param PDO $pdo
 * @param int $orderId
 * @param string $gateway
 * @param string|null $transactionId
 * @param float $amount
 * @param string $currency
 * @param string $status
 * @param mixed $rawResponse
 * @return int|false Payment ID أو false في حالة الفشل
 */
function record_payment(
    PDO $pdo,
    int $orderId,
    string $gateway,
    ?string $transactionId,
    float $amount,
    string $currency,
    string $status,
    $rawResponse = null
) {
    try {
        $sql = "INSERT INTO payments 
                (order_id, gateway, transaction_id, amount, currency, status, raw_response, created_at)
                VALUES (:order_id, :gateway, :transaction_id, :amount, :currency, :status, :raw_response, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':order_id'       => $orderId,
            ':gateway'        => $gateway,
            ':transaction_id' => $transactionId,
            ':amount'         => $amount,
            ':currency'       => $currency,
            ':status'         => $status,
            ':raw_response'   => is_string($rawResponse) ? $rawResponse : json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Payment recording failed: " . $e->getMessage());
        return false;
    }
}

/**
 * تحديث حالة الدفع
 */
function update_payment_status(PDO $pdo, int $paymentId, string $status, $rawResponse = null): bool {
    try {
        $sql = "UPDATE payments SET status = :status, raw_response = :raw_response, updated_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id'           => $paymentId,
            ':status'       => $status,
            ':raw_response' => is_string($rawResponse) ? $rawResponse : json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (PDOException $e) {
        error_log("Payment status update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * تحديث حالة الدفع بناءً على transaction_id
 */
function update_payment_by_transaction(PDO $pdo, string $transactionId, string $status, $rawResponse = null): bool {
    try {
        $sql = "UPDATE payments SET status = :status, raw_response = :raw_response, updated_at = NOW() WHERE transaction_id = :txn";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':txn'          => $transactionId,
            ':status'       => $status,
            ':raw_response' => is_string($rawResponse) ? $rawResponse : json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (PDOException $e) {
        error_log("Payment update by transaction failed: " . $e->getMessage());
        return false;
    }
}

/**
 * تحديث حالة الطلب
 */
function update_order_payment_status(PDO $pdo, int $orderId, string $paymentStatus): bool {
    try {
        $sql = "UPDATE orders SET payment_status = :ps, updated_at = NOW() WHERE id = :id";
        
        // التحقق من وجود عمود updated_at
        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([':ps' => $paymentStatus, ':id' => $orderId]);
        } catch (PDOException $e) {
            // إذا لم يكن عمود updated_at موجوداً
            $sql = "UPDATE orders SET payment_status = :ps WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([':ps' => $paymentStatus, ':id' => $orderId]);
        }
    } catch (PDOException $e) {
        error_log("Order payment status update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * جلب معلومات الطلب
 */
function get_order_by_id(PDO $pdo, int $orderId): ?array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ?: null;
    } catch (PDOException $e) {
        error_log("Get order failed: " . $e->getMessage());
        return null;
    }
}

/**
 * جلب سجلات الدفع للطلب
 */
function get_payments_by_order(PDO $pdo, int $orderId): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY created_at DESC");
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get payments failed: " . $e->getMessage());
        return [];
    }
}

// =============================================================================
// Stripe Functions - دوال Stripe
// =============================================================================

/**
 * إنشاء جلسة Stripe Checkout
 * 
 * @param PDO $pdo
 * @param int $orderId
 * @param float $amount
 * @param string $currency
 * @param string $successUrl
 * @param string $cancelUrl
 * @return array ['success' => bool, 'session_id' => string|null, 'url' => string|null, 'error' => string|null]
 */
function create_stripe_session(
    PDO $pdo,
    int $orderId,
    float $amount,
    string $currency,
    string $successUrl,
    string $cancelUrl
): array {
    $config = get_payments_config();
    $secretKey = $config['stripe_secret_key'] ?? '';
    
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Stripe secret key not configured'];
    }
    
    // استخدام Stripe PHP SDK إذا كان متاحاً
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        
        try {
            \Stripe\Stripe::setApiKey($secretKey);
            
            // جلب بنود الطلب
            $stmt = $pdo->prepare("SELECT oi.*, p.name as product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :oid");
            $stmt->execute([':oid' => $orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $lineItems = [];
            foreach ($items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $item['product_name'] ?? ('Product #' . $item['product_id']),
                        ],
                        'unit_amount' => (int)round(((float)$item['price']) * 100), // Stripe uses cents
                    ],
                    'quantity' => (int)$item['qty'],
                ];
            }
            
            // إذا لم تكن هناك بنود، أنشئ بنداً واحداً بالمبلغ الكلي
            if (empty($lineItems)) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => 'Order #' . $orderId,
                        ],
                        'unit_amount' => (int)round($amount * 100),
                    ],
                    'quantity' => 1,
                ];
            }
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'order_id' => $orderId,
                ],
            ]);
            
            // تسجيل عملية الدفع
            record_payment($pdo, $orderId, 'stripe', $session->id, $amount, $currency, 'pending', [
                'session_id' => $session->id,
                'created' => date('Y-m-d H:i:s'),
            ]);
            
            return [
                'success' => true,
                'session_id' => $session->id,
                'url' => $session->url,
            ];
        } catch (\Exception $e) {
            error_log("Stripe session creation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Fallback: استخدام cURL إذا لم يكن SDK متاحاً
    return create_stripe_session_curl($pdo, $orderId, $amount, $currency, $successUrl, $cancelUrl, $secretKey);
}

/**
 * إنشاء جلسة Stripe عبر cURL (fallback)
 */
function create_stripe_session_curl(
    PDO $pdo,
    int $orderId,
    float $amount,
    string $currency,
    string $successUrl,
    string $cancelUrl,
    string $secretKey
): array {
    $data = [
        'payment_method_types[]' => 'card',
        'line_items[0][price_data][currency]' => strtolower($currency),
        'line_items[0][price_data][product_data][name]' => 'Order #' . $orderId,
        'line_items[0][price_data][unit_amount]' => (int)round($amount * 100),
        'line_items[0][quantity]' => 1,
        'mode' => 'payment',
        'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $cancelUrl,
        'metadata[order_id]' => $orderId,
    ];
    
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && !empty($result['id'])) {
        record_payment($pdo, $orderId, 'stripe', $result['id'], $amount, $currency, 'pending', $result);
        
        return [
            'success' => true,
            'session_id' => $result['id'],
            'url' => $result['url'] ?? null,
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['error']['message'] ?? 'Stripe API error',
    ];
}

/**
 * التحقق من توقيع Stripe Webhook
 */
function verify_stripe_webhook_signature(string $payload, string $sigHeader, string $webhookSecret): bool {
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        
        try {
            \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            return true;
        } catch (\Exception $e) {
            error_log("Stripe webhook verification failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Manual verification fallback
    $elements = explode(',', $sigHeader);
    $timestamp = null;
    $signature = null;
    
    foreach ($elements as $element) {
        $kv = explode('=', $element, 2);
        if (count($kv) === 2) {
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            } elseif ($kv[0] === 'v1') {
                $signature = $kv[1];
            }
        }
    }
    
    if ($timestamp === null || $signature === null) {
        return false;
    }
    
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
    
    return hash_equals($expectedSignature, $signature);
}

// =============================================================================
// PayPal Functions - دوال PayPal
// =============================================================================

/**
 * الحصول على رابط PayPal API بناءً على الوضع (sandbox/live)
 */
function paypal_api_url(): string {
    $config = get_payments_config();
    $mode = $config['paypal_mode'] ?? 'sandbox';
    return ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
}

/**
 * الحصول على Access Token من PayPal
 */
function paypal_get_access_token(): ?string {
    $config = get_payments_config();
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    
    if (empty($clientId) || empty($secret)) {
        error_log("PayPal credentials not configured");
        return null;
    }
    
    $url = paypal_api_url() . '/v1/oauth2/token';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }
    
    error_log("PayPal token request failed: " . $response);
    return null;
}

/**
 * إنشاء طلب PayPal
 * 
 * @param PDO $pdo
 * @param int $orderId
 * @param float $amount
 * @param string $currency
 * @param string $returnUrl
 * @param string $cancelUrl
 * @return array ['success' => bool, 'order_id' => string|null, 'approval_url' => string|null, 'error' => string|null]
 */
function create_paypal_order(
    PDO $pdo,
    int $orderId,
    float $amount,
    string $currency,
    string $returnUrl,
    string $cancelUrl
): array {
    $accessToken = paypal_get_access_token();
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to get PayPal access token'];
    }
    
    $config = get_payments_config();
    $merchantEmail = $config['paypal_merchant_email'] ?? '';
    
    $url = paypal_api_url() . '/v2/checkout/orders';
    
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'order_' . $orderId,
                'description' => 'Order #' . $orderId,
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'custom_id' => (string)$orderId,
            ],
        ],
        'application_context' => [
            'brand_name' => $config['app_name'] ?? 'Store',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ],
    ];
    
    // إضافة payee إذا كان البريد محدداً
    if (!empty($merchantEmail)) {
        $orderData['purchase_units'][0]['payee'] = [
            'email_address' => $merchantEmail,
        ];
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($orderData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Prefer: return=representation',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && !empty($result['id'])) {
        // البحث عن رابط الموافقة
        $approvalUrl = null;
        foreach (($result['links'] ?? []) as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        // تسجيل عملية الدفع
        record_payment($pdo, $orderId, 'paypal', $result['id'], $amount, $currency, 'pending', $result);
        
        return [
            'success' => true,
            'order_id' => $result['id'],
            'approval_url' => $approvalUrl,
        ];
    }
    
    $errorMsg = $result['message'] ?? ($result['error_description'] ?? 'PayPal API error');
    error_log("PayPal order creation failed: " . $response);
    
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * التقاط دفعة PayPal (Capture)
 */
function capture_paypal_order(PDO $pdo, string $paypalOrderId): array {
    $accessToken = paypal_get_access_token();
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to get PayPal access token'];
    }
    
    $url = paypal_api_url() . '/v2/checkout/orders/' . $paypalOrderId . '/capture';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && ($result['status'] ?? '') === 'COMPLETED') {
        // استخراج order_id من custom_id
        $customId = null;
        foreach (($result['purchase_units'] ?? []) as $unit) {
            if (!empty($unit['payments']['captures'][0]['custom_id'])) {
                $customId = $unit['payments']['captures'][0]['custom_id'];
                break;
            }
            if (!empty($unit['custom_id'])) {
                $customId = $unit['custom_id'];
                break;
            }
        }
        
        // تحديث سجل الدفع
        update_payment_by_transaction($pdo, $paypalOrderId, 'completed', $result);
        
        // تحديث حالة الطلب إذا كان لدينا order_id
        if ($customId) {
            update_order_payment_status($pdo, (int)$customId, 'paid');
        }
        
        return [
            'success' => true,
            'capture_id' => $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
            'status' => 'COMPLETED',
            'order_id' => $customId,
            'response' => $result,
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message'] ?? 'PayPal capture failed',
        'response' => $result,
    ];
}

/**
 * الحصول على تفاصيل طلب PayPal
 */
function get_paypal_order_details(string $paypalOrderId): ?array {
    $accessToken = paypal_get_access_token();
    if (!$accessToken) {
        return null;
    }
    
    $url = paypal_api_url() . '/v2/checkout/orders/' . $paypalOrderId;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

/**
 * التحقق من توقيع PayPal Webhook
 * يستخدم PayPal Webhook Signature Verification API
 */
function verify_paypal_webhook(string $payload, array $headers): bool {
    $config = get_payments_config();
    $webhookId = $config['paypal_webhook_id'] ?? '';
    
    // إذا لم يكن webhook_id محدداً، نرفض الطلب
    if (empty($webhookId)) {
        error_log("PayPal webhook_id not configured - rejecting webhook for security");
        return false;
    }
    
    // تحويل الـ headers إلى lowercase
    $normalizedHeaders = [];
    foreach ($headers as $key => $value) {
        $normalizedHeaders[strtolower($key)] = $value;
    }
    
    // التحقق من وجود الـ headers الأساسية
    $requiredHeaders = [
        'paypal-transmission-id',
        'paypal-transmission-time',
        'paypal-transmission-sig',
        'paypal-cert-url',
        'paypal-auth-algo',
    ];
    
    foreach ($requiredHeaders as $header) {
        if (empty($normalizedHeaders[$header])) {
            error_log("Missing PayPal webhook header: " . $header);
            return false;
        }
    }
    
    // استخدام PayPal API للتحقق من التوقيع
    $accessToken = paypal_get_access_token();
    if (!$accessToken) {
        error_log("Failed to get PayPal access token for webhook verification");
        return false;
    }
    
    $verifyUrl = paypal_api_url() . '/v1/notifications/verify-webhook-signature';
    
    $verifyData = [
        'auth_algo' => $normalizedHeaders['paypal-auth-algo'],
        'cert_url' => $normalizedHeaders['paypal-cert-url'],
        'transmission_id' => $normalizedHeaders['paypal-transmission-id'],
        'transmission_sig' => $normalizedHeaders['paypal-transmission-sig'],
        'transmission_time' => $normalizedHeaders['paypal-transmission-time'],
        'webhook_id' => $webhookId,
        'webhook_event' => json_decode($payload, true),
    ];
    
    $ch = curl_init($verifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($verifyData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("PayPal webhook verification request failed with HTTP code: " . $httpCode);
        return false;
    }
    
    $result = json_decode($response, true);
    $verificationStatus = $result['verification_status'] ?? '';
    
    if ($verificationStatus !== 'SUCCESS') {
        error_log("PayPal webhook verification failed: " . $verificationStatus);
        return false;
    }
    
    return true;
}
