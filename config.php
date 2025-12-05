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

  // =============================================================
  // إعدادات بوابات الدفع - Payment Gateway Settings
  // يُفضّل تعريف هذه القيم عبر متغيّرات البيئة (environment variables)
  // لأغراض الأمان. القيم أدناه للتوضيح فقط ويجب عدم إدخال مفاتيح حقيقية.
  // =============================================================

  // Stripe Settings (استخدم مفاتيح sandbox/test للتطوير)
  'stripe_publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
  'stripe_secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
  'stripe_webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',

  // PayPal Settings (استخدم sandbox للتطوير)
  'paypal_client_id'       => getenv('PAYPAL_CLIENT_ID') ?: '',
  'paypal_secret'          => getenv('PAYPAL_SECRET') ?: '',
  'paypal_mode'            => getenv('PAYPAL_MODE') ?: 'sandbox', // sandbox أو live
  'paypal_merchant_email'  => getenv('PAYPAL_MERCHANT_EMAIL') ?: '', // بريد التاجر في PayPal
  'paypal_webhook_id'      => getenv('PAYPAL_WEBHOOK_ID') ?: '', // معرف webhook للتحقق من التوقيع

  // العملة الافتراضية للدفع
  'default_currency'       => getenv('DEFAULT_CURRENCY') ?: 'USD',
];