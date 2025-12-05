<?php
/**
 * Payment Gateway Integration Functions
 * Supports: Stripe (Visa/MasterCard) and PayPal
 * 
 * This file contains helper functions for payment processing.
 * Uses Stripe SDK if available, cURL for PayPal API calls.
 */

declare(strict_types=1);

/**
 * Get the site URL for payment callbacks
 * 
 * @param string $path Optional path to append
 * @return string Full URL
 */
function site_url(string $path = ''): string {
    $config = require __DIR__ . '/../config.php';
    $base = rtrim($config['base_url'] ?? '', '/');
    if ($path) {
        $path = '/' . ltrim($path, '/');
    }
    return $base . $path;
}

/**
 * Record a payment transaction in the database
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @param string $gateway Payment gateway (stripe, paypal, cod, cliq)
 * @param float $amount Payment amount
 * @param string $currency Currency code
 * @param string $status Payment status
 * @param string|null $transactionId External transaction ID
 * @param array|null $rawResponse Raw API response for debugging
 * @return int|false Payment record ID or false on failure
 */
function record_payment(
    PDO $pdo,
    int $orderId,
    string $gateway,
    float $amount,
    string $currency = 'USD',
    string $status = 'pending',
    ?string $transactionId = null,
    ?array $rawResponse = null
) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, raw_response, created_at)
            VALUES (:order_id, :gateway, :transaction_id, :amount, :currency, :status, :raw_response, NOW())
        ");
        $stmt->execute([
            ':order_id' => $orderId,
            ':gateway' => $gateway,
            ':transaction_id' => $transactionId,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $status,
            ':raw_response' => $rawResponse ? json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('record_payment error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing payment record
 * 
 * @param PDO $pdo Database connection
 * @param int $paymentId Payment record ID
 * @param string $status New status
 * @param string|null $transactionId Transaction ID to set
 * @param array|null $rawResponse Raw API response
 * @return bool Success
 */
function update_payment(
    PDO $pdo,
    int $paymentId,
    string $status,
    ?string $transactionId = null,
    ?array $rawResponse = null
): bool {
    try {
        $sql = "UPDATE payments SET status = :status, updated_at = NOW()";
        $params = [':status' => $status, ':id' => $paymentId];
        
        if ($transactionId !== null) {
            $sql .= ", transaction_id = :transaction_id";
            $params[':transaction_id'] = $transactionId;
        }
        
        if ($rawResponse !== null) {
            $sql .= ", raw_response = :raw_response";
            $params[':raw_response'] = json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('update_payment error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update payment by order ID (useful for webhook handlers)
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @param string $gateway Gateway filter
 * @param string $status New status
 * @param string|null $transactionId Transaction ID
 * @param array|null $rawResponse Raw response
 * @return bool Success
 */
function update_payment_by_order(
    PDO $pdo,
    int $orderId,
    string $gateway,
    string $status,
    ?string $transactionId = null,
    ?array $rawResponse = null
): bool {
    try {
        $sql = "UPDATE payments SET status = :status, updated_at = NOW()";
        $params = [':status' => $status, ':order_id' => $orderId, ':gateway' => $gateway];
        
        if ($transactionId !== null) {
            $sql .= ", transaction_id = :transaction_id";
            $params[':transaction_id'] = $transactionId;
        }
        
        if ($rawResponse !== null) {
            $sql .= ", raw_response = :raw_response";
            $params[':raw_response'] = json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        $sql .= " WHERE order_id = :order_id AND gateway = :gateway";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('update_payment_by_order error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get order by ID with validation
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @return array|false Order data or false if not found
 */
function get_order(PDO $pdo, int $orderId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('get_order error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update order payment status
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @param string $paymentStatus New payment status
 * @param string|null $orderStatus New order status
 * @return bool Success
 */
function update_order_status(PDO $pdo, int $orderId, string $paymentStatus, ?string $orderStatus = null): bool {
    try {
        $sql = "UPDATE orders SET payment_status = :payment_status";
        $params = [':payment_status' => $paymentStatus, ':id' => $orderId];
        
        if ($orderStatus !== null) {
            $sql .= ", status = :status";
            $params[':status'] = $orderStatus;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('update_order_status error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a Stripe Checkout Session
 * 
 * @param PDO $pdo Database connection
 * @param array $order Order data
 * @return array Result with 'success', 'checkout_url' or 'error'
 */
function create_stripe_session(PDO $pdo, array $order): array {
    $config = require __DIR__ . '/../config.php';
    
    $secretKey = $config['stripe_secret_key'] ?? '';
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Stripe secret key not configured'];
    }
    
    $orderId = (int) $order['id'];
    $total = (float) $order['total'];
    $currency = strtolower($config['default_payment_currency'] ?? 'usd');
    
    // Convert to cents/smallest unit
    $amountInCents = (int) round($total * 100);
    
    $successUrl = site_url('/public/order_view.php?id=' . $orderId . '&payment=success');
    $cancelUrl = site_url('/public/checkout.php?payment=cancelled&order_id=' . $orderId);
    
    try {
        // Check if Stripe SDK is available
        $composerAutoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        }
        
        if (class_exists('\Stripe\Stripe')) {
            // Use Stripe SDK
            \Stripe\Stripe::setApiKey($secretKey);
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Order #' . $orderId,
                            'description' => 'Order from ' . ($config['app_name'] ?? 'Store'),
                        ],
                        'unit_amount' => $amountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $orderId,
                'metadata' => [
                    'order_id' => $orderId,
                ],
            ]);
            
            // Record the payment as initiated
            record_payment(
                $pdo,
                $orderId,
                'stripe',
                $total,
                strtoupper($currency),
                'initiated',
                $session->id,
                ['session_id' => $session->id, 'created' => $session->created]
            );
            
            return [
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ];
        } else {
            // Fallback: Use cURL (basic implementation)
            return create_stripe_session_curl($pdo, $order, $secretKey, $amountInCents, $currency, $successUrl, $cancelUrl);
        }
    } catch (Throwable $e) {
        error_log('create_stripe_session error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create Stripe session using cURL (fallback when SDK not available)
 */
function create_stripe_session_curl(PDO $pdo, array $order, string $secretKey, int $amountInCents, string $currency, string $successUrl, string $cancelUrl): array {
    $orderId = (int) $order['id'];
    $config = require __DIR__ . '/../config.php';
    
    $data = [
        'payment_method_types[]' => 'card',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][product_data][name]' => 'Order #' . $orderId,
        'line_items[0][price_data][unit_amount]' => $amountInCents,
        'line_items[0][quantity]' => 1,
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'client_reference_id' => (string) $orderId,
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
    
    if ($httpCode === 200 && isset($result['url'])) {
        record_payment(
            $pdo,
            $orderId,
            'stripe',
            (float) $order['total'],
            strtoupper($currency),
            'initiated',
            $result['id'] ?? null,
            $result
        );
        
        return [
            'success' => true,
            'checkout_url' => $result['url'],
            'session_id' => $result['id'] ?? null,
        ];
    }
    
    return ['success' => false, 'error' => $result['error']['message'] ?? 'Stripe API error'];
}

/**
 * Get PayPal access token using client credentials
 * 
 * @return array Result with 'success' and 'access_token' or 'error'
 */
function paypal_get_access_token(): array {
    $config = require __DIR__ . '/../config.php';
    
    $clientId = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    $mode = $config['paypal_mode'] ?? 'sandbox';
    
    if (empty($clientId) || empty($secret)) {
        return ['success' => false, 'error' => 'PayPal credentials not configured'];
    }
    
    $apiBase = ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $ch = curl_init($apiBase . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && isset($result['access_token'])) {
        return [
            'success' => true,
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'] ?? 3600,
        ];
    }
    
    return ['success' => false, 'error' => $result['error_description'] ?? 'PayPal auth failed'];
}

/**
 * Create a PayPal order for checkout
 * 
 * @param PDO $pdo Database connection
 * @param array $order Order data
 * @return array Result with 'success', 'approval_url', 'paypal_order_id' or 'error'
 */
function create_paypal_order(PDO $pdo, array $order): array {
    $config = require __DIR__ . '/../config.php';
    
    // Get access token first
    $authResult = paypal_get_access_token();
    if (!$authResult['success']) {
        return $authResult;
    }
    $accessToken = $authResult['access_token'];
    
    $orderId = (int) $order['id'];
    $total = (float) $order['total'];
    $currency = strtoupper($config['default_payment_currency'] ?? 'USD');
    $mode = $config['paypal_mode'] ?? 'sandbox';
    
    $apiBase = ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $returnUrl = site_url('/public/paypal_return.php?order_id=' . $orderId);
    $cancelUrl = site_url('/public/checkout.php?payment=cancelled&order_id=' . $orderId);
    
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string) $orderId,
            'description' => 'Order #' . $orderId . ' from ' . ($config['app_name'] ?? 'Store'),
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($total, 2, '.', ''),
            ],
            'custom_id' => (string) $orderId,
        ]],
        'application_context' => [
            'brand_name' => $config['app_name'] ?? 'Store',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ],
    ];
    
    $ch = curl_init($apiBase . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'PayPal-Request-Id: order-' . $orderId . '-' . time(),
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && isset($result['id'])) {
        $paypalOrderId = $result['id'];
        
        // Find approval URL
        $approvalUrl = '';
        foreach (($result['links'] ?? []) as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        // Record the payment as initiated
        record_payment(
            $pdo,
            $orderId,
            'paypal',
            $total,
            $currency,
            'initiated',
            $paypalOrderId,
            $result
        );
        
        return [
            'success' => true,
            'approval_url' => $approvalUrl,
            'paypal_order_id' => $paypalOrderId,
        ];
    }
    
    $errorMsg = $result['message'] ?? $result['error_description'] ?? 'PayPal API error';
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Capture a PayPal order after user approval
 * 
 * @param string $paypalOrderId PayPal order ID
 * @return array Result with 'success' and 'capture_id' or 'error'
 */
function capture_paypal_order(string $paypalOrderId): array {
    $config = require __DIR__ . '/../config.php';
    
    $authResult = paypal_get_access_token();
    if (!$authResult['success']) {
        return $authResult;
    }
    $accessToken = $authResult['access_token'];
    
    $mode = $config['paypal_mode'] ?? 'sandbox';
    $apiBase = ($mode === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $ch = curl_init($apiBase . '/v2/checkout/orders/' . $paypalOrderId . '/capture');
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
    
    if ($httpCode === 201 && isset($result['status']) && $result['status'] === 'COMPLETED') {
        $captureId = null;
        if (isset($result['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $captureId = $result['purchase_units'][0]['payments']['captures'][0]['id'];
        }
        
        return [
            'success' => true,
            'status' => 'COMPLETED',
            'capture_id' => $captureId,
            'raw_response' => $result,
        ];
    }
    
    $errorMsg = $result['message'] ?? $result['details'][0]['description'] ?? 'PayPal capture failed';
    return ['success' => false, 'error' => $errorMsg, 'raw_response' => $result];
}

/**
 * Verify Stripe webhook signature
 * 
 * @param string $payload Raw request body
 * @param string $sigHeader Stripe-Signature header
 * @param string $secret Webhook secret
 * @return array Result with 'success' and 'event' or 'error'
 */
function verify_stripe_webhook(string $payload, string $sigHeader, string $secret): array {
    // Check if Stripe SDK is available
    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    }
    
    if (class_exists('\Stripe\Webhook')) {
        try {
            \Stripe\Stripe::setApiKey($GLOBALS['config']['stripe_secret_key'] ?? '');
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            return ['success' => true, 'event' => $event];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['success' => false, 'error' => 'Invalid signature: ' . $e->getMessage()];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Manual signature verification (fallback)
    $elements = explode(',', $sigHeader);
    $timestamp = null;
    $signature = null;
    
    foreach ($elements as $element) {
        $parts = explode('=', $element, 2);
        if (count($parts) === 2) {
            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signature = $parts[1];
            }
        }
    }
    
    if (!$timestamp || !$signature) {
        return ['success' => false, 'error' => 'Invalid signature header format'];
    }
    
    // Check timestamp (allow 5 minutes tolerance)
    if (abs(time() - (int)$timestamp) > 300) {
        return ['success' => false, 'error' => 'Timestamp too old'];
    }
    
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return ['success' => false, 'error' => 'Signature mismatch'];
    }
    
    $event = json_decode($payload, true);
    return ['success' => true, 'event' => $event];
}

/**
 * Get payments for an order
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @return array List of payment records
 */
function get_order_payments(PDO $pdo, int $orderId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM payments 
            WHERE order_id = :order_id 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('get_order_payments error: ' . $e->getMessage());
        return [];
    }
}
