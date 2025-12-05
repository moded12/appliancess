# Payment Gateway Integration Guide
# دليل تكامل بوابات الدفع

This document provides comprehensive instructions for setting up and testing the Stripe and PayPal payment integrations.

يوفر هذا المستند تعليمات شاملة لإعداد واختبار تكامل بوابتي الدفع Stripe و PayPal.

---

## Table of Contents | جدول المحتويات

1. [Prerequisites | المتطلبات الأساسية](#prerequisites--المتطلبات-الأساسية)
2. [Environment Variables | متغيرات البيئة](#environment-variables--متغيرات-البيئة)
3. [Installation | التثبيت](#installation--التثبيت)
4. [Database Migration | ترحيل قاعدة البيانات](#database-migration--ترحيل-قاعدة-البيانات)
5. [Stripe Setup | إعداد Stripe](#stripe-setup--إعداد-stripe)
6. [PayPal Setup | إعداد PayPal](#paypal-setup--إعداد-paypal)
7. [Webhook Configuration | إعداد Webhooks](#webhook-configuration--إعداد-webhooks)
8. [Testing | الاختبار](#testing--الاختبار)
9. [Troubleshooting | استكشاف الأخطاء](#troubleshooting--استكشاف-الأخطاء)

---

## Prerequisites | المتطلبات الأساسية

- PHP 7.4 or higher | PHP 7.4 أو أعلى
- Composer installed | تثبيت Composer
- cURL extension enabled | تمكين امتداد cURL
- SSL certificate for production | شهادة SSL للإنتاج

---

## Environment Variables | متغيرات البيئة

Create a `.env` file or set these environment variables on your server:

قم بإنشاء ملف `.env` أو تعيين متغيرات البيئة هذه على خادمك:

```bash
# Stripe Configuration
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# PayPal Configuration
PAYPAL_CLIENT_ID=your_sandbox_client_id
PAYPAL_SECRET=your_sandbox_secret
PAYPAL_MODE=sandbox  # or 'live' for production
PAYPAL_WEBHOOK_ID=your_webhook_id  # Optional, for webhook verification

# Default Currency
DEFAULT_CURRENCY=USD
```

### Example .env File | ملف .env نموذجي

```bash
# Stripe Sandbox Keys (Replace with your actual keys)
STRIPE_PUBLISHABLE_KEY=pk_test_51ABC...xyz
STRIPE_SECRET_KEY=sk_test_51ABC...xyz
STRIPE_WEBHOOK_SECRET=whsec_abc123...

# PayPal Sandbox Keys (Replace with your actual keys)
PAYPAL_CLIENT_ID=AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPp
PAYPAL_SECRET=1234567890abcdef1234567890abcdef
PAYPAL_MODE=sandbox

# Currency
DEFAULT_CURRENCY=USD
```

**⚠️ Important | مهم:** Never commit real API keys to the repository. | لا تقم أبداً بإضافة مفاتيح API الحقيقية إلى المستودع.

---

## Installation | التثبيت

1. Install PHP dependencies using Composer:
   تثبيت التبعيات باستخدام Composer:

```bash
cd /path/to/appliancess
composer install
```

2. Set up environment variables as described above.
   قم بإعداد متغيرات البيئة كما هو موضح أعلاه.

---

## Database Migration | ترحيل قاعدة البيانات

Run the payments migration to create the `payments` table:

قم بتشغيل ترحيل المدفوعات لإنشاء جدول `payments`:

```bash
mysql -u your_user -p your_database < migrations/payments.sql
```

Or execute the SQL directly:

أو قم بتنفيذ SQL مباشرة:

```sql
-- Run migrations/payments.sql content
```

---

## Stripe Setup | إعداد Stripe

### Getting Sandbox Keys | الحصول على مفاتيح الـ Sandbox

1. Go to [Stripe Dashboard](https://dashboard.stripe.com/)
   اذهب إلى [لوحة تحكم Stripe](https://dashboard.stripe.com/)

2. Sign up or log in
   قم بالتسجيل أو تسجيل الدخول

3. Make sure you're in **Test mode** (toggle in the top-right)
   تأكد أنك في **وضع الاختبار** (المفتاح في أعلى اليمين)

4. Go to **Developers** → **API keys**
   اذهب إلى **المطورون** → **مفاتيح API**

5. Copy your **Publishable key** and **Secret key**
   انسخ **المفتاح العام** و **المفتاح السري**

### Setting Up Stripe Webhooks | إعداد Stripe Webhooks

#### Using Stripe CLI (Recommended for Local Testing) | باستخدام Stripe CLI (موصى به للاختبار المحلي)

1. Install Stripe CLI:
   تثبيت Stripe CLI:

```bash
# macOS
brew install stripe/stripe-cli/stripe

# Linux
curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
sudo apt update
sudo apt install stripe
```

2. Login to Stripe:
   تسجيل الدخول إلى Stripe:

```bash
stripe login
```

3. Forward webhooks to your local server:
   توجيه webhooks إلى خادمك المحلي:

```bash
stripe listen --forward-to localhost/xx/public/stripe_webhook.php
```

4. Copy the webhook signing secret displayed
   انسخ السر الظاهر للتوقيع

#### Using Stripe Dashboard (For Production) | باستخدام لوحة تحكم Stripe (للإنتاج)

1. Go to **Developers** → **Webhooks**
   اذهب إلى **المطورون** → **Webhooks**

2. Click **Add endpoint**
   انقر **إضافة نقطة نهاية**

3. Enter your webhook URL: `https://yourdomain.com/public/stripe_webhook.php`
   أدخل رابط الـ webhook: `https://yourdomain.com/public/stripe_webhook.php`

4. Select events to listen to:
   اختر الأحداث للاستماع إليها:
   - `checkout.session.completed`
   - `checkout.session.expired`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`

5. Click **Add endpoint** and copy the **Signing secret**
   انقر **إضافة نقطة نهاية** وانسخ **سر التوقيع**

---

## PayPal Setup | إعداد PayPal

### Getting Sandbox Credentials | الحصول على بيانات اعتماد الـ Sandbox

1. Go to [PayPal Developer Dashboard](https://developer.paypal.com/)
   اذهب إلى [لوحة مطوري PayPal](https://developer.paypal.com/)

2. Log in with your PayPal account
   سجل الدخول بحسابك في PayPal

3. Go to **Apps & Credentials**
   اذهب إلى **التطبيقات وبيانات الاعتماد**

4. Make sure **Sandbox** is selected
   تأكد من اختيار **Sandbox**

5. Click **Create App**
   انقر **إنشاء تطبيق**

6. Enter an app name and create
   أدخل اسم التطبيق وأنشئه

7. Copy the **Client ID** and **Secret**
   انسخ **معرف العميل** و **السر**

### Setting Up PayPal Webhooks | إعداد PayPal Webhooks

1. In your app settings, scroll to **Webhooks**
   في إعدادات تطبيقك، انتقل إلى **Webhooks**

2. Click **Add Webhook**
   انقر **إضافة Webhook**

3. Enter your webhook URL: `https://yourdomain.com/public/paypal_webhook.php`
   أدخل رابط الـ webhook: `https://yourdomain.com/public/paypal_webhook.php`

4. Select events:
   اختر الأحداث:
   - `CHECKOUT.ORDER.APPROVED`
   - `CHECKOUT.ORDER.COMPLETED`
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`
   - `PAYMENT.CAPTURE.REFUNDED`

5. Copy the **Webhook ID** for verification
   انسخ **معرف الـ Webhook** للتحقق

### PayPal Sandbox Test Accounts | حسابات اختبار PayPal Sandbox

1. Go to **Sandbox** → **Accounts**
   اذهب إلى **Sandbox** → **الحسابات**

2. Create test buyer and seller accounts
   أنشئ حسابات مشتري وبائع للاختبار

3. Use test buyer credentials when testing payments
   استخدم بيانات المشتري الاختباري عند اختبار المدفوعات

---

## Webhook Configuration | إعداد Webhooks

### Stripe Webhook Events | أحداث Stripe Webhook

| Event | Description | الوصف |
|-------|-------------|-------|
| `checkout.session.completed` | Payment successful | الدفع ناجح |
| `checkout.session.expired` | Session expired | انتهت صلاحية الجلسة |
| `payment_intent.payment_failed` | Payment failed | فشل الدفع |
| `charge.refunded` | Payment refunded | تم استرداد المبلغ |

### PayPal Webhook Events | أحداث PayPal Webhook

| Event | Description | الوصف |
|-------|-------------|-------|
| `CHECKOUT.ORDER.APPROVED` | Order approved by buyer | تمت الموافقة على الطلب |
| `PAYMENT.CAPTURE.COMPLETED` | Payment captured | تم تحصيل الدفع |
| `PAYMENT.CAPTURE.DENIED` | Payment denied | تم رفض الدفع |
| `PAYMENT.CAPTURE.REFUNDED` | Payment refunded | تم استرداد المبلغ |

---

## Testing | الاختبار

### Stripe Test Cards | بطاقات اختبار Stripe

| Card Number | Scenario | السيناريو |
|-------------|----------|-----------|
| `4242 4242 4242 4242` | Successful payment | دفع ناجح |
| `4000 0000 0000 0002` | Card declined | بطاقة مرفوضة |
| `4000 0000 0000 9995` | Insufficient funds | رصيد غير كافٍ |

Use any future expiration date and any 3-digit CVC.
استخدم أي تاريخ انتهاء مستقبلي وأي رقم CVC من 3 أرقام.

### PayPal Sandbox Testing | اختبار PayPal Sandbox

1. Use sandbox buyer account credentials
   استخدم بيانات حساب المشتري الـ sandbox

2. Log in with test buyer email and password
   سجل الدخول ببريد وكلمة مرور المشتري الاختباري

3. Complete the payment flow
   أكمل عملية الدفع

### Testing Webhooks Locally | اختبار Webhooks محلياً

#### Stripe CLI

```bash
# Forward webhooks to local server
stripe listen --forward-to http://localhost/xx/public/stripe_webhook.php

# Trigger test events
stripe trigger checkout.session.completed
```

#### PayPal

Use [ngrok](https://ngrok.com/) or similar tunneling service:

```bash
ngrok http 80
# Use the generated URL as your webhook endpoint
```

---

## Troubleshooting | استكشاف الأخطاء

### Common Issues | مشاكل شائعة

1. **"Stripe library not installed"**
   Run `composer install` in the project root.
   قم بتشغيل `composer install` في جذر المشروع.

2. **"PayPal credentials not configured"**
   Check that environment variables are set correctly.
   تأكد من إعداد متغيرات البيئة بشكل صحيح.

3. **Webhook signature verification failed**
   - Ensure webhook secret is correct
   - Check that raw request body is being used
   تأكد من صحة سر الـ webhook واستخدام جسم الطلب الخام.

4. **Payment created but order not updated**
   - Check webhook endpoint is accessible
   - Verify database connection
   تحقق من إمكانية الوصول لنقطة الـ webhook والاتصال بقاعدة البيانات.

### Debug Mode | وضع التصحيح

Enable debug mode in `config.php` to see detailed errors:

```php
'debug' => true
```

### Log Files | ملفات السجل

Check PHP error logs for webhook processing issues:

```bash
tail -f /var/log/php_errors.log
```

---

## Security Considerations | اعتبارات الأمان

1. **Never expose API keys** - Always use environment variables
   لا تكشف أبداً مفاتيح API - استخدم دائماً متغيرات البيئة

2. **Always verify webhooks** - Use signature verification
   تحقق دائماً من webhooks - استخدم التحقق من التوقيع

3. **Use HTTPS** - Required for payment processing
   استخدم HTTPS - مطلوب لمعالجة المدفوعات

4. **Validate order data** - Verify amounts before processing
   تحقق من بيانات الطلب - تأكد من المبالغ قبل المعالجة

---

## Support | الدعم

For issues with payment gateways:

- Stripe: [Stripe Support](https://support.stripe.com/)
- PayPal: [PayPal Developer Support](https://developer.paypal.com/support/)

للمشاكل المتعلقة ببوابات الدفع:
- Stripe: [دعم Stripe](https://support.stripe.com/)
- PayPal: [دعم مطوري PayPal](https://developer.paypal.com/support/)

---

## Merchant Information | معلومات التاجر

PayPal Merchant Email (Reference): `ajourisat@yahoo.com`

This email should be linked to the PayPal Developer sandbox seller account.
يجب ربط هذا البريد بحساب البائع في PayPal Developer sandbox.
