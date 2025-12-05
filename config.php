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

  // ===== إعدادات بوابات الدفع (Payment Gateways) =====
  // Stripe Settings
  // يفضل استخدام متغيرات البيئة (.env) في الإنتاج
  // STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET
  'stripe' => [
    'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    'webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
  ],

  // PayPal Settings
  // يفضل استخدام متغيرات البيئة (.env) في الإنتاج
  // PAYPAL_CLIENT_ID, PAYPAL_SECRET, PAYPAL_MODE
  'paypal' => [
    'client_id'     => getenv('PAYPAL_CLIENT_ID') ?: '',
    'client_secret' => getenv('PAYPAL_SECRET') ?: '',
    'mode'          => getenv('PAYPAL_MODE') ?: 'sandbox', // 'sandbox' or 'live'
  ],
];