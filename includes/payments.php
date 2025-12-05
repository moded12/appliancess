<?php
/**
 * Payment Gateway Functions
 * المسار: includes/payments.php
 * 
 * This file contains functions for handling Stripe and PayPal payments.
 * Supports sandbox/test mode for both gateways.
 */

declare(strict_types=1);

/**
 * Get the site URL with a given path
 * 
 * @param string $path The path to append to the base URL
 * @return string The full URL
 */
function site_url(string $path = ''): string
{
    $config = require __DIR__ . '/../config.php';
    $base = rtrim($config['base_url'] ?? '', '/');
    $path = ltrim($path, '/');
    return $base . '/public/' . $path;
}

/**
 * Record a payment in the database
 * 
 * @param PDO $pdo Database connection
 * @param int $order_id Order ID
 * @param string $gateway Payment gateway name (stripe, paypal, etc.)
 * @param string|null $transaction_id Transaction ID from the gateway
 * @param float $amount Payment amount
 * @param string $currency Currency code (USD, JOD, etc.)
 * @param string $status Payment status (pending, completed, failed, refunded, cancelled)
 * @param string|null $raw_response Raw JSON response from gateway
 * @return int|false The payment record ID or false on failure
 */
function record_payment(
    PDO $pdo,
    int $order_id,
    string $gateway,
    ?string $transaction_id,
    float $amount,
    string $currency,
    string $status,
    ?string $raw_response = null
) {
    try {
        $sql = "INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, raw_response, created_at)
                VALUES (:order_id, :gateway, :transaction_id, :amount, :currency, :status, :raw_response, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':order_id'       => $order_id,
            ':gateway'        => $gateway,
            ':transaction_id' => $transaction_id,
            ':amount'         => $amount,
            ':currency'       => $currency,
            ':status'         => $status,
            ':raw_response'   => $raw_response
        ]);
        
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('record_payment error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update a payment record status
 * 
 * @param PDO $pdo Database connection
 * @param int $payment_id Payment record ID
 * @param string $status New status
 * @param string|null $transaction_id Transaction ID (if updated)
 * @param string|null $raw_response Raw response from gateway
 * @return bool Success status
 */
function update_payment_status(
    PDO $pdo,
    int $payment_id,
    string $status,
    ?string $transaction_id = null,
    ?string $raw_response = null
): bool {
    try {
        $fields = ['status = :status', 'updated_at = NOW()'];
        $params = [':status' => $status, ':id' => $payment_id];
        
        if ($transaction_id !== null) {
            $fields[] = 'transaction_id = :transaction_id';
            $params[':transaction_id'] = $transaction_id;
        }
        
        if ($raw_response !== null) {
            $fields[] = 'raw_response = :raw_response';
            $params[':raw_response'] = $raw_response;
        }
        
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('update_payment_status error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get payment record by order_id and gateway
 * 
 * @param PDO $pdo Database connection
 * @param int $order_id Order ID
 * @param string $gateway Gateway name
 * @return array|null Payment record or null
 */
function get_payment_by_order(PDO $pdo, int $order_id, string $gateway): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id AND gateway = :gateway ORDER BY id DESC LIMIT 1");
    $stmt->execute([':order_id' => $order_id, ':gateway' => $gateway]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Get all payments for an order
 * 
 * @param PDO $pdo Database connection
 * @param int $order_id Order ID
 * @return array Payment records
 */
function get_payments_by_order(PDO $pdo, int $order_id): array
{
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY created_at DESC");
    $stmt->execute([':order_id' => $order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create a Stripe Checkout Session
 * 
 * @param PDO $pdo Database connection
 * @param array $order Order data with 'id', 'total', and optionally 'items'
 * @return array ['success' => bool, 'session_id' => string|null, 'url' => string|null, 'error' => string|null]
 */
function create_stripe_session(PDO $pdo, array $order): array
{
    $config = require __DIR__ . '/../config.php';
    
    $secretKey = $config['stripe_secret_key'] ?? '';
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Stripe secret key not configured'];
    }
    
    $orderId = (int) ($order['id'] ?? 0);
    $amount = (float) ($order['total'] ?? 0);
    $currency = strtolower($config['default_currency'] ?? 'usd');
    
    if ($orderId <= 0 || $amount <= 0) {
        return ['success' => false, 'error' => 'Invalid order data'];
    }
    
    // Convert amount to cents (Stripe uses smallest currency unit)
    $amountCents = (int) round($amount * 100);
    
    try {
        // Check if stripe-php is available via Composer
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }
        
        if (class_exists('\\Stripe\\Stripe')) {
            // Use Stripe PHP SDK
            \Stripe\Stripe::setApiKey($secretKey);
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Order #' . $orderId,
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => site_url('order_view.php?id=' . $orderId . '&payment=success'),
                'cancel_url' => site_url('checkout.php?payment=cancelled'),
                'metadata' => [
                    'order_id' => $orderId,
                ],
            ]);
            
            // Record payment as pending
            record_payment(
                $pdo,
                $orderId,
                'stripe',
                $session->id,
                $amount,
                strtoupper($currency),
                'pending',
                json_encode(['session_id' => $session->id, 'created' => time()])
            );
            
            return [
                'success' => true,
                'session_id' => $session->id,
                'url' => $session->url
            ];
        } else {
            // Fallback: Use cURL if SDK not available
            return create_stripe_session_curl($pdo, $order, $secretKey, $currency, $amountCents);
        }
    } catch (\Exception $e) {
        error_log('create_stripe_session error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create Stripe session using cURL (fallback if SDK not available)
 */
function create_stripe_session_curl(PDO $pdo, array $order, string $secretKey, string $currency, int $amountCents): array
{
    $orderId = (int) $order['id'];
    $amount = (float) $order['total'];
    
    $data = [
        'payment_method_types[]' => 'card',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][product_data][name]' => 'Order #' . $orderId,
        'line_items[0][price_data][unit_amount]' => $amountCents,
        'line_items[0][quantity]' => 1,
        'mode' => 'payment',
        'success_url' => site_url('order_view.php?id=' . $orderId . '&payment=success'),
        'cancel_url' => site_url('checkout.php?payment=cancelled'),
        'metadata[order_id]' => $orderId,
    ];
    
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && !empty($result['id'])) {
        record_payment(
            $pdo,
            $orderId,
            'stripe',
            $result['id'],
            $amount,
            strtoupper($currency),
            'pending',
            $response
        );
        
        return [
            'success' => true,
            'session_id' => $result['id'],
            'url' => $result['url'] ?? null
        ];
    }
    
    return ['success' => false, 'error' => $result['error']['message'] ?? 'Stripe API error'];
}

/**
 * Get PayPal Access Token
 * 
 * @param string $clientId PayPal Client ID
 * @param string $secret PayPal Secret
 * @param string $mode 'sandbox' or 'live'
 * @return string|null Access token or null on failure
 */
function paypal_get_access_token(string $clientId, string $secret, string $mode = 'sandbox'): ?string
{
    $baseUrl = $mode === 'live' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $ch = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    error_log('PayPal get_access_token error: ' . $response);
    return null;
}

/**
 * Create a PayPal Order
 * 
 * @param PDO $pdo Database connection
 * @param array $order Order data with 'id' and 'total'
 * @return array ['success' => bool, 'order_id' => string|null, 'approval_url' => string|null, 'error' => string|null]
 */
function create_paypal_order(PDO $pdo, array $order): array
{
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    
    if (empty($clientId) || empty($secret)) {
        return ['success' => false, 'error' => 'PayPal credentials not configured'];
    }
    
    $orderId = (int) ($order['id'] ?? 0);
    $amount = (float) ($order['total'] ?? 0);
    $currency = strtoupper($config['default_currency'] ?? 'USD');
    
    if ($orderId <= 0 || $amount <= 0) {
        return ['success' => false, 'error' => 'Invalid order data'];
    }
    
    // Get access token
    $accessToken = paypal_get_access_token($clientId, $secret, $mode);
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to get PayPal access token'];
    }
    
    $baseUrl = $mode === 'live' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $returnUrl = site_url('paypal_return.php?order_id=' . $orderId);
    $cancelUrl = site_url('checkout.php?payment=cancelled');
    
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string) $orderId,
            'description' => 'Order #' . $orderId,
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($amount, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'brand_name' => $config['app_name'] ?? 'Store',
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'user_action' => 'PAY_NOW',
        ],
    ];
    
    $ch = curl_init($baseUrl . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && !empty($result['id'])) {
        // Find approval URL
        $approvalUrl = null;
        foreach ($result['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        // Record payment as pending
        record_payment(
            $pdo,
            $orderId,
            'paypal',
            $result['id'],
            $amount,
            $currency,
            'pending',
            $response
        );
        
        return [
            'success' => true,
            'paypal_order_id' => $result['id'],
            'approval_url' => $approvalUrl
        ];
    }
    
    $errorMsg = $result['message'] ?? ($result['error_description'] ?? 'PayPal API error');
    error_log('create_paypal_order error: ' . $response);
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Capture a PayPal Order (complete the payment)
 * 
 * @param string $paypalOrderId PayPal Order ID
 * @return array ['success' => bool, 'capture_id' => string|null, 'status' => string|null, 'response' => array|null, 'error' => string|null]
 */
function capture_paypal_order(string $paypalOrderId): array
{
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    
    if (empty($clientId) || empty($secret)) {
        return ['success' => false, 'error' => 'PayPal credentials not configured'];
    }
    
    $accessToken = paypal_get_access_token($clientId, $secret, $mode);
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to get PayPal access token'];
    }
    
    $baseUrl = $mode === 'live' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $ch = curl_init($baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture');
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
    
    if ($httpCode === 201 || $httpCode === 200) {
        $captureId = null;
        $captures = $result['purchase_units'][0]['payments']['captures'] ?? [];
        if (!empty($captures)) {
            $captureId = $captures[0]['id'] ?? null;
        }
        
        return [
            'success' => true,
            'capture_id' => $captureId,
            'status' => $result['status'] ?? 'UNKNOWN',
            'response' => $result
        ];
    }
    
    $errorMsg = $result['message'] ?? ($result['error_description'] ?? 'PayPal capture error');
    error_log('capture_paypal_order error: ' . $response);
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Update order payment status
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @param string $paymentStatus Payment status (pending, paid, failed)
 * @param string $orderStatus Order status (new, processing, shipped, completed, cancelled)
 * @return bool Success
 */
function update_order_payment_status(PDO $pdo, int $orderId, string $paymentStatus, string $orderStatus): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = :ps, status = :os WHERE id = :id");
        return $stmt->execute([
            ':ps' => $paymentStatus,
            ':os' => $orderStatus,
            ':id' => $orderId
        ]);
    } catch (PDOException $e) {
        error_log('update_order_payment_status error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify Stripe webhook signature
 * 
 * @param string $payload Raw request body
 * @param string $sigHeader Stripe-Signature header
 * @param string $endpointSecret Webhook endpoint secret
 * @return array|null Decoded event or null if verification fails
 */
function verify_stripe_webhook(string $payload, string $sigHeader, string $endpointSecret): ?array
{
    try {
        // Check if stripe-php is available
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }
        
        if (class_exists('\\Stripe\\Webhook')) {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            return json_decode(json_encode($event), true);
        } else {
            // Manual verification fallback
            return verify_stripe_webhook_manual($payload, $sigHeader, $endpointSecret);
        }
    } catch (\Exception $e) {
        error_log('verify_stripe_webhook error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Manual Stripe webhook signature verification (without SDK)
 */
function verify_stripe_webhook_manual(string $payload, string $sigHeader, string $endpointSecret): ?array
{
    $parts = explode(',', $sigHeader);
    $timestamp = null;
    $signature = null;
    
    foreach ($parts as $part) {
        $keyValue = explode('=', $part, 2);
        if (count($keyValue) === 2) {
            if ($keyValue[0] === 't') {
                $timestamp = $keyValue[1];
            } elseif ($keyValue[0] === 'v1') {
                $signature = $keyValue[1];
            }
        }
    }
    
    if (!$timestamp || !$signature) {
        return null;
    }
    
    // Check timestamp (allow 5 minutes tolerance)
    if (abs(time() - (int)$timestamp) > 300) {
        return null;
    }
    
    // Compute expected signature
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSig = hash_hmac('sha256', $signedPayload, $endpointSecret);
    
    if (!hash_equals($expectedSig, $signature)) {
        return null;
    }
    
    return json_decode($payload, true);
}
