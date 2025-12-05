<?php
// المسار: config.php

return [
  'db_host'      => 'localhost',
  'db_name'      => 'appliance',
  'db_user'      => 'appliance',
  'db_pass'      => 'Tvvcrtv1610@',

  // الاسم الجديد
  'app_name'     => 'العجوري للأجهزة المنزلية',

  // الأساس
  'base_url'     => 'https://www.shneler.com/xx',

  // العملة
  'currency_code'     => 'JOD',
  'currency_symbol'   => 'د.أ',
  'currency_position' => 'after',
  'currency_rates'    => ['JOD'=>1, 'USD'=>1.41],
  'currency_symbols'  => ['JOD'=>'د.أ','USD'=>'$'],

  // إعدادات إضافية
  'views_badge_threshold' => 5,
  'enable_slider'         => false, // تعطيل السلايدر الآن

  'timezone'     => 'Asia/Amman',
  'session_name' => 'appliance_session',
  'debug'        => false,

  // =====================================
  // إعدادات بوابات الدفع (Payment Gateways)
  // =====================================
  // Stripe Settings (sandbox/test mode)
  // Set these environment variables on your server
  'stripe_publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
  'stripe_secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
  'stripe_webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',

  // PayPal Settings (sandbox mode)
  // Set these environment variables on your server
  'paypal_client_id'       => getenv('PAYPAL_CLIENT_ID') ?: '',
  'paypal_secret'          => getenv('PAYPAL_SECRET') ?: '',
  'paypal_mode'            => getenv('PAYPAL_MODE') ?: 'sandbox', // sandbox or live
  'paypal_merchant_email'  => getenv('PAYPAL_MERCHANT_EMAIL') ?: 'ajourisat@yahoo.com',

  // Default currency for payment gateways (USD recommended for international payments)
  'default_payment_currency' => getenv('DEFAULT_CURRENCY') ?: 'USD',
];