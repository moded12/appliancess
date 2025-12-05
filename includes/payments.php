<?php
/**
 * Payment Gateway Helper Functions
 * 
 * This file provides integration with Stripe and PayPal payment gateways.
 * All API keys must be set via environment variables.
 */

declare(strict_types=1);

/**
 * Get the site URL for callback/redirect URLs
 */
function site_url(string $path = ''): string {
    $config = require __DIR__ . '/../config.php';
    $base = rtrim($config['base_url'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Record a payment transaction in the database
 */
function record_payment(PDO $pdo, array $data): int {
    $sql = "INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, raw_response, created_at) 
            VALUES (:order_id, :gateway, :transaction_id, :amount, :currency, :status, :raw_response, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':order_id'       => $data['order_id'],
        ':gateway'        => $data['gateway'],
        ':transaction_id' => $data['transaction_id'] ?? null,
        ':amount'         => $data['amount'],
        ':currency'       => $data['currency'] ?? 'USD',
        ':status'         => $data['status'] ?? 'pending',
        ':raw_response'   => isset($data['raw_response']) ? json_encode($data['raw_response'], JSON_UNESCAPED_UNICODE) : null,
    ]);
    
    return (int)$pdo->lastInsertId();
}

/**
 * Update a payment record status
 */
function update_payment_status(PDO $pdo, int $paymentId, string $status, ?string $transactionId = null, ?array $rawResponse = null): bool {
    $sql = "UPDATE payments SET status = :status";
    $params = [':status' => $status, ':id' => $paymentId];
    
    if ($transactionId !== null) {
        $sql .= ", transaction_id = :transaction_id";
        $params[':transaction_id'] = $transactionId;
    }
    
    if ($rawResponse !== null) {
        $sql .= ", raw_response = :raw_response";
        $params[':raw_response'] = json_encode($rawResponse, JSON_UNESCAPED_UNICODE);
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Update payment by order_id and gateway
 */
function update_payment_by_order(PDO $pdo, int $orderId, string $gateway, string $status, ?string $transactionId = null, ?array $rawResponse = null): bool {
    $sql = "UPDATE payments SET status = :status, updated_at = NOW()";
    $params = [':status' => $status, ':order_id' => $orderId, ':gateway' => $gateway];
    
    if ($transactionId !== null) {
        $sql .= ", transaction_id = :transaction_id";
        $params[':transaction_id'] = $transactionId;
    }
    
    if ($rawResponse !== null) {
        $sql .= ", raw_response = :raw_response";
        $params[':raw_response'] = json_encode($rawResponse, JSON_UNESCAPED_UNICODE);
    }
    
    $sql .= " WHERE order_id = :order_id AND gateway = :gateway";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get order details by ID
 */
function get_order(PDO $pdo, int $orderId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    return $order ?: null;
}

/**
 * Update order payment status
 */
function update_order_payment(PDO $pdo, int $orderId, string $paymentStatus, ?string $status = null): bool {
    $sql = "UPDATE orders SET payment_status = :payment_status";
    $params = [':payment_status' => $paymentStatus, ':id' => $orderId];
    
    if ($status !== null) {
        $sql .= ", status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get payments for an order
 */
function get_order_payments(PDO $pdo, int $orderId): array {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY created_at DESC");
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// STRIPE INTEGRATION
// ============================================================

/**
 * Create a Stripe Checkout Session
 * 
 * @param PDO $pdo Database connection
 * @param array $order Order data with 'id', 'total', and optionally 'currency'
 * @return array Response with 'success', 'session_id', 'checkout_url' or 'error'
 */
function create_stripe_session(PDO $pdo, array $order): array {
    $config = require __DIR__ . '/../config.php';
    
    $secretKey = $config['stripe_secret_key'] ?? '';
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Stripe secret key not configured'];
    }
    
    // Require stripe-php library
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        return ['success' => false, 'error' => 'Stripe library not installed. Run: composer install'];
    }
    require_once $autoloadPath;
    
    try {
        \Stripe\Stripe::setApiKey($secretKey);
        
        $orderId = (int)$order['id'];
        $amount = (float)$order['total'];
        $currency = strtolower($config['default_currency'] ?? 'usd');
        
        // Convert amount to cents (Stripe uses smallest currency unit)
        $amountInCents = (int)round($amount * 100);
        
        // Create Checkout Session
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Order #' . $orderId,
                        'description' => 'Payment for order #' . $orderId,
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => site_url('public/order_view.php?id=' . $orderId . '&payment=success'),
            'cancel_url' => site_url('public/order_view.php?id=' . $orderId . '&payment=cancelled'),
            'client_reference_id' => (string)$orderId,
            'metadata' => [
                'order_id' => $orderId,
            ],
        ]);
        
        // Record payment as pending
        record_payment($pdo, [
            'order_id' => $orderId,
            'gateway' => 'stripe',
            'transaction_id' => $session->id,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'raw_response' => ['session_id' => $session->id],
        ]);
        
        return [
            'success' => true,
            'session_id' => $session->id,
            'checkout_url' => $session->url,
        ];
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Stripe error: ' . $e->getMessage()];
    }
}

/**
 * Verify Stripe webhook signature
 */
function verify_stripe_webhook(string $payload, string $sigHeader): ?object {
    $config = require __DIR__ . '/../config.php';
    $webhookSecret = $config['stripe_webhook_secret'] ?? '';
    
    if (empty($webhookSecret)) {
        return null;
    }
    
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        return null;
    }
    require_once $autoloadPath;
    
    try {
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        return null;
    }
}

// ============================================================
// PAYPAL INTEGRATION
// ============================================================

/**
 * Get PayPal API base URL based on mode
 */
function paypal_api_url(): string {
    $config = require __DIR__ . '/../config.php';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    return $mode === 'live' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
}

/**
 * Get PayPal OAuth access token
 */
function paypal_get_access_token(string $clientId, string $secret, string $modeUrl): ?string {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $modeUrl . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Create a PayPal Order
 * 
 * @param PDO $pdo Database connection
 * @param array $order Order data with 'id', 'total'
 * @return array Response with 'success', 'paypal_order_id', 'approval_url' or 'error'
 */
function create_paypal_order(PDO $pdo, array $order): array {
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    
    if (empty($clientId) || empty($secret)) {
        return ['success' => false, 'error' => 'PayPal credentials not configured'];
    }
    
    $apiUrl = paypal_api_url();
    
    // Get access token
    $accessToken = paypal_get_access_token($clientId, $secret, $apiUrl);
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to obtain PayPal access token'];
    }
    
    $orderId = (int)$order['id'];
    $amount = number_format((float)$order['total'], 2, '.', '');
    $currency = strtoupper($config['default_currency'] ?? 'USD');
    
    // Create PayPal order
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string)$orderId,
            'description' => 'Order #' . $orderId,
            'custom_id' => (string)$orderId,
            'amount' => [
                'currency_code' => $currency,
                'value' => $amount,
            ],
        ]],
        'application_context' => [
            'brand_name' => $config['app_name'] ?? 'Store',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'return_url' => site_url('public/paypal_return.php?order_id=' . $orderId),
            'cancel_url' => site_url('public/order_view.php?id=' . $orderId . '&payment=cancelled'),
        ],
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . '/v2/checkout/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($orderData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 || !$response) {
        $errorData = json_decode($response, true);
        return [
            'success' => false, 
            'error' => 'PayPal API error: ' . ($errorData['message'] ?? 'Unknown error'),
        ];
    }
    
    $paypalOrder = json_decode($response, true);
    $paypalOrderId = $paypalOrder['id'] ?? null;
    
    // Find approval URL
    $approvalUrl = null;
    foreach (($paypalOrder['links'] ?? []) as $link) {
        if ($link['rel'] === 'approve') {
            $approvalUrl = $link['href'];
            break;
        }
    }
    
    if (!$paypalOrderId || !$approvalUrl) {
        return ['success' => false, 'error' => 'Invalid PayPal response'];
    }
    
    // Record payment as pending
    record_payment($pdo, [
        'order_id' => $orderId,
        'gateway' => 'paypal',
        'transaction_id' => $paypalOrderId,
        'amount' => (float)$amount,
        'currency' => $currency,
        'status' => 'pending',
        'raw_response' => $paypalOrder,
    ]);
    
    return [
        'success' => true,
        'paypal_order_id' => $paypalOrderId,
        'approval_url' => $approvalUrl,
    ];
}

/**
 * Capture a PayPal payment
 */
function capture_paypal_payment(string $paypalOrderId): array {
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    
    if (empty($clientId) || empty($secret)) {
        return ['success' => false, 'error' => 'PayPal credentials not configured'];
    }
    
    $apiUrl = paypal_api_url();
    
    // Get access token
    $accessToken = paypal_get_access_token($clientId, $secret, $apiUrl);
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to obtain PayPal access token'];
    }
    
    // Capture the payment
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture',
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
    curl_close($ch);
    
    $captureData = json_decode($response, true);
    
    if ($httpCode === 201 && isset($captureData['status']) && $captureData['status'] === 'COMPLETED') {
        // Extract capture ID
        $captureId = null;
        if (isset($captureData['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $captureId = $captureData['purchase_units'][0]['payments']['captures'][0]['id'];
        }
        
        return [
            'success' => true,
            'status' => 'COMPLETED',
            'capture_id' => $captureId,
            'raw_response' => $captureData,
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Capture failed: ' . ($captureData['message'] ?? 'Unknown error'),
        'raw_response' => $captureData,
    ];
}

/**
 * Verify PayPal webhook signature
 */
function verify_paypal_webhook(array $headers, string $body): bool {
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    $webhookId = getenv('PAYPAL_WEBHOOK_ID') ?: '';
    
    if (empty($clientId) || empty($secret) || empty($webhookId)) {
        // If webhook ID not set, we can't verify
        // In production, this should always be verified
        return false;
    }
    
    $apiUrl = paypal_api_url();
    $accessToken = paypal_get_access_token($clientId, $secret, $apiUrl);
    
    if (!$accessToken) {
        return false;
    }
    
    $verifyData = [
        'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
        'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
        'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'webhook_id' => $webhookId,
        'webhook_event' => json_decode($body, true),
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . '/v1/notifications/verify-webhook-signature',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($verifyData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return isset($data['verification_status']) && $data['verification_status'] === 'SUCCESS';
}
