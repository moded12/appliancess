# Payment Gateway Integration / تكامل بوابات الدفع

This document explains how to configure and test the Stripe and PayPal payment gateways integrated into the store.

هذا المستند يشرح كيفية إعداد واختبار بوابتي الدفع Stripe و PayPal المدمجتين في المتجر.

---

## Table of Contents / جدول المحتويات

1. [Overview / نظرة عامة](#overview--نظرة-عامة)
2. [Requirements / المتطلبات](#requirements--المتطلبات)
3. [Installation / التثبيت](#installation--التثبيت)
4. [Configuration / الإعداد](#configuration--الإعداد)
5. [Database Migration / ترحيل قاعدة البيانات](#database-migration--ترحيل-قاعدة-البيانات)
6. [Stripe Setup / إعداد Stripe](#stripe-setup--إعداد-stripe)
7. [PayPal Setup / إعداد PayPal](#paypal-setup--إعداد-paypal)
8. [Testing Webhooks / اختبار Webhooks](#testing-webhooks--اختبار-webhooks)
9. [Security Notes / ملاحظات أمنية](#security-notes--ملاحظات-أمنية)

---

## Overview / نظرة عامة

The payment integration supports two gateways:
- **Stripe**: Credit/Debit card payments via Stripe Checkout
- **PayPal**: PayPal account and card payments

تدعم تكامل المدفوعات بوابتين:
- **Stripe**: دفعات بطاقات الائتمان/الخصم عبر Stripe Checkout
- **PayPal**: مدفوعات حساب PayPal والبطاقات

---

## Requirements / المتطلبات

- PHP >= 7.4
- cURL extension enabled
- Composer (for Stripe SDK)
- SSL certificate (HTTPS) - required for production

---

## Installation / التثبيت

### 1. Install Composer Dependencies / تثبيت تبعيات Composer

```bash
cd /path/to/appliancess
composer install
```

This will install the `stripe/stripe-php` SDK.

### 2. Run Database Migration / تشغيل ترحيل قاعدة البيانات

```bash
mysql -u your_user -p your_database < migrations/payments.sql
```

Or via phpMyAdmin, import `migrations/payments.sql`.

---

## Configuration / الإعداد

### Environment Variables / متغيرات البيئة

Set the following environment variables (or add them to your server configuration):

```bash
# Stripe
export STRIPE_PUBLISHABLE_KEY="pk_test_XXXXXXXXXX"
export STRIPE_SECRET_KEY="sk_test_XXXXXXXXXX"
export STRIPE_WEBHOOK_SECRET="whsec_XXXXXXXXXX"

# PayPal
export PAYPAL_CLIENT_ID="your_client_id"
export PAYPAL_SECRET="your_secret"
export PAYPAL_MODE="sandbox"  # Use "live" for production

# Currency
export DEFAULT_CURRENCY="USD"
```

### Config File / ملف الإعدادات

The `config.php` file will automatically read from environment variables. Example values are provided but should **NOT** contain real keys in the repository.

ملف `config.php` سيقرأ تلقائياً من متغيرات البيئة. الأمثلة موجودة لكن يجب عدم وضع مفاتيح حقيقية في المستودع.

---

## Database Migration / ترحيل قاعدة البيانات

The `migrations/payments.sql` creates the `payments` table with the following structure:

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| order_id | INT | Foreign key to orders table |
| gateway | ENUM | Payment gateway (stripe, paypal, cod, cliq, other) |
| transaction_id | VARCHAR | Gateway transaction ID |
| amount | DECIMAL | Payment amount |
| currency | VARCHAR | Currency code |
| status | ENUM | Payment status (pending, completed, failed, refunded, cancelled) |
| raw_response | TEXT | Raw JSON response from gateway |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

---

## Stripe Setup / إعداد Stripe

### 1. Create Stripe Account / إنشاء حساب Stripe

1. Go to [https://stripe.com](https://stripe.com)
2. Sign up for an account
3. Navigate to Developers > API Keys
4. Copy your **Publishable key** and **Secret key**

### 2. Configure Webhook / إعداد Webhook

1. Go to Developers > Webhooks
2. Add endpoint: `https://your-domain.com/public/stripe_webhook.php`
3. Select events:
   - `checkout.session.completed`
   - `checkout.session.expired`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
4. Copy the **Webhook signing secret**

### 3. Test with Stripe CLI / الاختبار باستخدام Stripe CLI

```bash
# Install Stripe CLI
# Download from https://stripe.com/docs/stripe-cli

# Login
stripe login

# Forward webhooks to local server
stripe listen --forward-to localhost:8000/public/stripe_webhook.php

# This will give you a webhook signing secret for testing
# Use this secret in your local STRIPE_WEBHOOK_SECRET

# Trigger test events
stripe trigger checkout.session.completed
stripe trigger payment_intent.succeeded
```

### Test Cards / بطاقات الاختبار

| Card Number | Description |
|-------------|-------------|
| 4242 4242 4242 4242 | Successful payment |
| 4000 0000 0000 0002 | Declined |
| 4000 0000 0000 3220 | 3D Secure required |

Use any future expiry date and any 3-digit CVC.

---

## PayPal Setup / إعداد PayPal

### 1. Create PayPal Developer Account / إنشاء حساب مطور PayPal

1. Go to [https://developer.paypal.com](https://developer.paypal.com)
2. Sign in with your PayPal account
3. Go to Dashboard > My Apps & Credentials
4. Create a new app (Sandbox)
5. Copy your **Client ID** and **Secret**

### 2. Configure Sandbox Accounts / إعداد حسابات Sandbox

1. Go to Sandbox > Accounts
2. Create or use existing sandbox accounts:
   - Business account (merchant): receives payments
   - Personal account (buyer): for testing purchases

### 3. Configure Webhook / إعداد Webhook

1. Go to Dashboard > My Apps & Credentials
2. Select your app
3. Add webhook URL: `https://your-domain.com/public/paypal_webhook.php`
4. Subscribe to events:
   - `CHECKOUT.ORDER.APPROVED`
   - `CHECKOUT.ORDER.COMPLETED`
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`
   - `PAYMENT.CAPTURE.REFUNDED`

### PayPal Sandbox Testing / اختبار PayPal Sandbox

1. Use sandbox mode (`PAYPAL_MODE=sandbox`)
2. When redirected to PayPal, log in with sandbox buyer account
3. Approve the payment
4. You'll be redirected back to the store

**Merchant Email**: The payments will be sent to `ajourisat@yahoo.com` (configured in config.php)

---

## Testing Webhooks / اختبار Webhooks

### Local Development / التطوير المحلي

For local testing, use tools like:
- **ngrok**: `ngrok http 8000`
- **Stripe CLI**: See above
- **localtunnel**: `lt --port 8000`

### Webhook URLs

- Stripe: `https://your-domain.com/public/stripe_webhook.php`
- PayPal: `https://your-domain.com/public/paypal_webhook.php`

---

## Security Notes / ملاحظات أمنية

### ✅ Implemented / تم تنفيذه

1. **No real keys in repository** - Use environment variables
   لا توجد مفاتيح حقيقية في المستودع - استخدم متغيرات البيئة

2. **Prepared statements** - All database queries use PDO prepared statements
   جميع استعلامات قاعدة البيانات تستخدم prepared statements

3. **Webhook signature verification** - Stripe webhook signatures are verified
   يتم التحقق من توقيعات Stripe webhooks

4. **HTTPS required** - For production, always use HTTPS
   للإنتاج، استخدم دائماً HTTPS

### ⚠️ Recommendations / توصيات

1. Always test in sandbox mode first
   دائماً اختبر في وضع sandbox أولاً

2. Review and test all payment flows before going live
   راجع واختبر جميع مسارات الدفع قبل الإنتاج

3. Monitor webhook logs for failed events
   راقب سجلات webhook للأحداث الفاشلة

4. Implement rate limiting for payment endpoints
   نفّذ rate limiting لنقاط الدفع

---

## File Structure / هيكل الملفات

```
appliancess/
├── config.php                 # Payment configuration
├── composer.json              # Stripe SDK dependency
├── migrations/
│   └── payments.sql          # Payments table migration
├── includes/
│   └── payments.php          # Payment functions
├── public/
│   ├── checkout.php          # Checkout with payment options
│   ├── pay.php               # Payment initiation
│   ├── paypal_return.php     # PayPal return handler
│   ├── stripe_webhook.php    # Stripe webhook handler
│   └── paypal_webhook.php    # PayPal webhook handler
├── admin/
│   └── order_view.php        # Order view with payment records
└── README_PAYMENT.md         # This documentation
```

---

## Support / الدعم

For issues or questions:
- Check the error logs in your server
- Verify environment variables are correctly set
- Ensure SSL certificate is valid
- Test with sandbox/test credentials first

للمشاكل أو الأسئلة:
- تحقق من سجلات الأخطاء في الخادم
- تأكد من صحة متغيرات البيئة
- تأكد من صلاحية شهادة SSL
- اختبر بمفاتيح sandbox/test أولاً
