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
  'debug'        => false
];