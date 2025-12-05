# دليل تكامل بوابات الدفع (Payment Integration Guide)

## نظرة عامة

يوفر هذا التكامل دعماً لبوابتي دفع:
- **Stripe**: لقبول بطاقات الائتمان (Visa، MasterCard، إلخ)
- **PayPal**: لقبول الدفع عبر حساب PayPal أو بطاقة ائتمان

## المتطلبات

- PHP 7.4 أو أحدث
- cURL extension
- SSL certificate (HTTPS) للإنتاج
- حساب Stripe (https://stripe.com)
- حساب PayPal Business (https://www.paypal.com)

## التثبيت

### 1. تثبيت التبعيات (اختياري)

```bash
composer install
```

هذا يثبت Stripe PHP SDK. إذا لم ترغب باستخدام Composer، النظام يعمل أيضاً عبر cURL.

### 2. تنفيذ Migration قاعدة البيانات

```bash
mysql -u username -p database_name < migrations/payments.sql
```

أو قم بتنفيذ محتوى الملف `migrations/payments.sql` يدوياً في phpMyAdmin.

### 3. تهيئة متغيرات البيئة

أنشئ ملف `.env` في جذر المشروع (أو أضف لملفك الحالي):

```env
# Stripe Configuration
STRIPE_SECRET_KEY=sk_test_XXXXXXXXXXXXXXXXXXXX
STRIPE_PUBLISHABLE_KEY=pk_test_XXXXXXXXXXXXXXXXXXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXXXXXXXXXXXXXXXXXX

# PayPal Configuration
PAYPAL_CLIENT_ID=XXXXXXXXXXXXXXXXXXXX
PAYPAL_SECRET=XXXXXXXXXXXXXXXXXXXX
PAYPAL_MODE=sandbox
```

> ⚠️ **تحذير أمني**: لا ترفع مفاتيح الإنتاج إلى المستودع. استخدم `.gitignore` لحماية ملف `.env`.

## الحصول على مفاتيح Sandbox

### Stripe Sandbox Keys

1. سجّل دخولك إلى [Stripe Dashboard](https://dashboard.stripe.com)
2. تأكد من تفعيل وضع "Test mode" (أعلى يمين الشاشة)
3. اذهب إلى **Developers** → **API keys**
4. انسخ:
   - **Publishable key**: يبدأ بـ `pk_test_`
   - **Secret key**: يبدأ بـ `sk_test_`

### Stripe Webhook Secret

1. اذهب إلى **Developers** → **Webhooks**
2. اضغط **Add endpoint**
3. أدخل URL: `https://yourdomain.com/public/stripe_webhook.php`
4. اختر الأحداث:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
5. بعد الإنشاء، انسخ **Signing secret**: يبدأ بـ `whsec_`

### PayPal Sandbox Keys

1. سجّل دخولك إلى [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/)
2. اذهب إلى **Apps & Credentials**
3. تأكد من اختيار **Sandbox**
4. أنشئ تطبيق جديد أو استخدم Default Application
5. انسخ:
   - **Client ID**
   - **Secret** (اضغط Show لعرضه)

### PayPal Sandbox Test Accounts

1. في PayPal Developer Dashboard، اذهب إلى **Sandbox** → **Accounts**
2. أنشئ حساب Personal للاختبار
3. استخدم بريد وكلمة مرور الحساب للدفع في صفحة PayPal

## اختبار الدفع

### اختبار Stripe

استخدم بطاقات الاختبار التالية:

| رقم البطاقة | الوصف |
|-------------|--------|
| 4242 4242 4242 4242 | دفع ناجح |
| 4000 0000 0000 9995 | رفض - رصيد غير كافٍ |
| 4000 0000 0000 0002 | رفض - عام |

- تاريخ الانتهاء: أي تاريخ مستقبلي (مثل 12/28)
- CVV: أي 3 أرقام (مثل 123)
- الرمز البريدي: أي رقم (مثل 12345)

### اختبار PayPal

1. استخدم حساب Sandbox الذي أنشأته
2. سجّل الدخول بالبريد وكلمة المرور الوهميين
3. أكمل الدفع

### اختبار Webhooks محلياً

#### Stripe CLI

```bash
# تثبيت Stripe CLI
# macOS
brew install stripe/stripe-cli/stripe

# Windows (Scoop)
scoop bucket add stripe https://github.com/stripe/scoop-stripe-cli.git
scoop install stripe

# تسجيل الدخول
stripe login

# إعادة توجيه Webhooks محلياً
stripe listen --forward-to localhost/public/stripe_webhook.php

# اختبار حدث
stripe trigger checkout.session.completed
```

#### PayPal Webhook Simulator

1. اذهب إلى PayPal Developer Dashboard
2. **Webhooks** → **Webhooks Simulator**
3. اختر نوع الحدث وأرسله

## سير عمل الدفع

### Stripe Flow

```
1. العميل يختار Stripe في صفحة Checkout
2. النظام ينشئ Order في قاعدة البيانات (status: waiting_payment)
3. النظام يُنشئ Stripe Checkout Session
4. إعادة توجيه العميل إلى صفحة Stripe
5. العميل يُدخل بيانات البطاقة ويدفع
6. Stripe يُعيد العميل إلى stripe_return.php
7. stripe_return.php يتحقق من الجلسة ويُحدث الطلب
8. (بديل) Stripe يُرسل Webhook لتأكيد الدفع
```

### PayPal Flow

```
1. العميل يختار PayPal في صفحة Checkout
2. النظام ينشئ Order في قاعدة البيانات (status: waiting_payment)
3. النظام يُنشئ PayPal Order عبر API
4. إعادة توجيه العميل إلى صفحة PayPal
5. العميل يوافق على الدفع
6. PayPal يُعيد العميل إلى paypal_return.php
7. paypal_return.php يُنفذ Capture ويُحدث الطلب
8. (بديل) PayPal يُرسل Webhook لتأكيد الدفع
```

## إعداد URLs في بوابات الدفع

### Stripe Webhook URL
```
https://yourdomain.com/public/stripe_webhook.php
```

### PayPal Webhook URL
```
https://yourdomain.com/public/paypal_webhook.php
```

### Return URLs (يتم تعيينها تلقائياً)
```
Success (Stripe): https://yourdomain.com/public/stripe_return.php
Success (PayPal): https://yourdomain.com/public/paypal_return.php
Cancel: https://yourdomain.com/public/checkout.php?cancelled=1
```

## جدول payments

```sql
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway ENUM('paypal', 'stripe', 'cod', 'cliq') NOT NULL,
  transaction_id VARCHAR(255) NULL,
  amount DECIMAL(10, 2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  gateway_response TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);
```

## الانتقال للإنتاج

### Stripe

1. في Stripe Dashboard، أوقف Test mode
2. احصل على مفاتيح Live:
   - `pk_live_XXXX` (Publishable)
   - `sk_live_XXXX` (Secret)
3. أنشئ Webhook endpoint جديد للإنتاج
4. حدّث متغيرات البيئة

### PayPal

1. في PayPal Developer، انتقل إلى **Live**
2. أنشئ تطبيق Live أو استخدم الموجود
3. احصل على Client ID و Secret
4. حدّث `PAYPAL_MODE=live` في متغيرات البيئة

## استكشاف الأخطاء

### Stripe

| الخطأ | السبب | الحل |
|-------|-------|------|
| `Stripe secret key is not configured` | المفتاح غير موجود | تحقق من `.env` |
| `Invalid API Key` | مفتاح خاطئ | تحقق من نسخ المفتاح كاملاً |
| Webhook signature failed | secret خاطئ | تحقق من `STRIPE_WEBHOOK_SECRET` |

### PayPal

| الخطأ | السبب | الحل |
|-------|-------|------|
| `PayPal credentials not configured` | المفاتيح غير موجودة | تحقق من `.env` |
| `INVALID_CLIENT` | Client ID خاطئ | تحقق من النسخ |
| `AUTHENTICATION_FAILURE` | Secret خاطئ | تحقق من Secret |

### السجلات (Logs)

تجد سجلات Webhooks في:
```
logs/stripe_webhook.log
logs/paypal_webhook.log
```

## الأمان

- ✅ جميع الاتصالات عبر HTTPS
- ✅ التحقق من توقيع Webhooks
- ✅ استخدام Prepared Statements لمنع SQL Injection
- ✅ لا تُخزن بيانات البطاقات في قاعدة البيانات
- ✅ المفاتيح الحساسة في متغيرات البيئة فقط
- ✅ حماية CSRF للنماذج

## استرداد الأموال (Refunds)

### Stripe Refund

```php
// عبر Stripe Dashboard أو API
\Stripe\Refund::create([
    'payment_intent' => 'pi_XXXXX',
    'amount' => 1000, // بالسنتات (10.00 USD)
]);
```

### PayPal Refund

يتم عبر PayPal Dashboard:
1. اذهب إلى Activity
2. اختر المعاملة
3. اضغط Issue a refund

## الدعم

للمساعدة في التكامل:
- [Stripe Documentation](https://stripe.com/docs)
- [PayPal Developer Documentation](https://developer.paypal.com/docs/)
