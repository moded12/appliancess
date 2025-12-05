# Payment Integration Guide / دليل تكامل الدفع

This document explains how to set up and test PayPal and Stripe payment integrations in sandbox/test mode.

يشرح هذا المستند كيفية إعداد واختبار تكاملات الدفع عبر PayPal و Stripe في وضع sandbox/test.

---

## Table of Contents / الفهرس

1. [Prerequisites / المتطلبات](#prerequisites--المتطلبات)
2. [Environment Variables / متغيرات البيئة](#environment-variables--متغيرات-البيئة)
3. [Stripe Setup / إعداد Stripe](#stripe-setup--إعداد-stripe)
4. [PayPal Setup / إعداد PayPal](#paypal-setup--إعداد-paypal)
5. [Database Migration / ترحيل قاعدة البيانات](#database-migration--ترحيل-قاعدة-البيانات)
6. [Testing / الاختبار](#testing--الاختبار)
7. [Webhooks Setup / إعداد Webhooks](#webhooks-setup--إعداد-webhooks)

---

## Prerequisites / المتطلبات

- PHP 7.4 or higher
- Composer installed
- MySQL/MariaDB database
- cURL extension enabled
- SSL certificate (required for webhooks)

---

## Environment Variables / متغيرات البيئة

Set the following environment variables on your server. **Never commit real keys to the repository.**

قم بتعيين متغيرات البيئة التالية على الخادم. **لا تضع المفاتيح الحقيقية في المستودع أبداً.**

```bash
# Stripe Configuration
export STRIPE_PUBLISHABLE_KEY="pk_test_..."
export STRIPE_SECRET_KEY="sk_test_..."
export STRIPE_WEBHOOK_SECRET="whsec_..."

# PayPal Configuration
export PAYPAL_CLIENT_ID="..."
export PAYPAL_SECRET="..."
export PAYPAL_MODE="sandbox"  # or "live" for production
export PAYPAL_MERCHANT_EMAIL="ajourisat@yahoo.com"

# Payment Currency
export DEFAULT_CURRENCY="USD"
```

### Apache (.htaccess or VirtualHost)

```apache
SetEnv STRIPE_PUBLISHABLE_KEY "pk_test_..."
SetEnv STRIPE_SECRET_KEY "sk_test_..."
SetEnv STRIPE_WEBHOOK_SECRET "whsec_..."
SetEnv PAYPAL_CLIENT_ID "..."
SetEnv PAYPAL_SECRET "..."
SetEnv PAYPAL_MODE "sandbox"
SetEnv DEFAULT_CURRENCY "USD"
```

### PHP-FPM (pool.d/www.conf)

```ini
env[STRIPE_PUBLISHABLE_KEY] = "pk_test_..."
env[STRIPE_SECRET_KEY] = "sk_test_..."
env[STRIPE_WEBHOOK_SECRET] = "whsec_..."
env[PAYPAL_CLIENT_ID] = "..."
env[PAYPAL_SECRET] = "..."
env[PAYPAL_MODE] = "sandbox"
env[DEFAULT_CURRENCY] = "USD"
```

---

## Stripe Setup / إعداد Stripe

### 1. Create Stripe Account / إنشاء حساب Stripe

1. Go to [https://dashboard.stripe.com/register](https://dashboard.stripe.com/register)
2. Complete registration
3. Access the Dashboard

### 2. Get API Keys / الحصول على مفاتيح API

1. Go to **Developers** → **API Keys**
2. Copy the **Publishable key** (pk_test_...)
3. Copy the **Secret key** (sk_test_...)

### 3. Install Stripe SDK / تثبيت Stripe SDK

```bash
cd /path/to/your/project
composer install
```

### 4. Test Cards / بطاقات الاختبار

| Card Number | Description |
|-------------|-------------|
| 4242 4242 4242 4242 | Successful payment |
| 4000 0000 0000 0002 | Card declined |
| 4000 0000 0000 9995 | Insufficient funds |

Use any future expiry date (e.g., 12/34) and any 3-digit CVC.

---

## PayPal Setup / إعداد PayPal

### 1. Create PayPal Developer Account / إنشاء حساب مطور PayPal

1. Go to [https://developer.paypal.com](https://developer.paypal.com)
2. Log in or sign up
3. Access the Developer Dashboard

### 2. Create Sandbox App / إنشاء تطبيق Sandbox

1. Go to **My Apps & Credentials**
2. Select **Sandbox** tab
3. Click **Create App**
4. Enter app name and create
5. Copy **Client ID** and **Secret**

### 3. Sandbox Accounts / حسابات Sandbox

1. Go to **Sandbox** → **Accounts**
2. Create or use existing:
   - **Business** account (merchant) - link with `ajourisat@yahoo.com`
   - **Personal** account (buyer) for testing

### 4. Test Credentials / بيانات الاختبار

Use the sandbox personal account email/password to test payments.

---

## Database Migration / ترحيل قاعدة البيانات

Run the migration to create the `payments` table:

قم بتشغيل الترحيل لإنشاء جدول `payments`:

```bash
mysql -u your_user -p your_database < migrations/payments.sql
```

Or run in phpMyAdmin or any MySQL client:

```sql
-- From migrations/payments.sql
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway ENUM('stripe','paypal','cod','cliq') NOT NULL,
  transaction_id VARCHAR(255) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending','initiated','processing','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  raw_response MEDIUMTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Webhooks Setup / إعداد Webhooks

### Stripe Webhooks

#### Using Stripe CLI (Local Development)

```bash
# Install Stripe CLI
# macOS
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to your local server
stripe listen --forward-to https://yourdomain.com/public/stripe_webhook.php
```

The CLI will provide a webhook signing secret (`whsec_...`). Use this as `STRIPE_WEBHOOK_SECRET`.

#### Production Webhook

1. Go to **Developers** → **Webhooks** in Stripe Dashboard
2. Click **Add endpoint**
3. Enter URL: `https://yourdomain.com/public/stripe_webhook.php`
4. Select events:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
5. Copy the webhook signing secret

### PayPal Webhooks

1. Go to **My Apps & Credentials** in PayPal Developer
2. Select your app
3. Scroll to **Webhooks**
4. Click **Add Webhook**
5. Enter URL: `https://yourdomain.com/public/paypal_webhook.php`
6. Select events:
   - `CHECKOUT.ORDER.APPROVED`
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`

---

## Testing / الاختبار

### Successful Payment / دفع ناجح

1. Add items to cart
2. Go to checkout
3. Fill customer details
4. Select **Stripe** or **PayPal**
5. Complete payment using test credentials
6. Verify order status is `paid` and `completed`

### Failed Payment / دفع فاشل

#### Stripe
- Use card: `4000 0000 0000 0002` (declined)

#### PayPal
- Cancel the payment on PayPal page

### Verify in Admin Panel / التحقق في لوحة الإدارة

1. Login to admin panel
2. Go to Orders
3. View order details
4. Check **سجلات المدفوعات** (Payment Records) section

---

## Troubleshooting / استكشاف الأخطاء

### Common Issues

1. **"Stripe secret key not configured"**
   - Ensure `STRIPE_SECRET_KEY` environment variable is set
   - Check if PHP can read environment variables (`getenv()`)

2. **"PayPal credentials not configured"**
   - Ensure `PAYPAL_CLIENT_ID` and `PAYPAL_SECRET` are set
   - Verify `PAYPAL_MODE` is set to `sandbox` for testing

3. **Webhook verification fails**
   - Ensure `STRIPE_WEBHOOK_SECRET` matches the webhook endpoint
   - Check server time is synchronized (NTP)

4. **cURL errors**
   - Ensure PHP cURL extension is enabled
   - Check SSL certificate is valid

### Logs

Check PHP error logs for detailed error messages:
```bash
tail -f /var/log/php/error.log
```

---

## Security Notes / ملاحظات أمنية

1. **Never commit API keys** to version control
2. Use **environment variables** for all sensitive data
3. Always verify webhook signatures
4. Use **HTTPS** for all payment endpoints
5. Keep Stripe SDK updated

---

## Support / الدعم

For issues with:
- **Stripe**: [Stripe Support](https://support.stripe.com)
- **PayPal**: [PayPal Developer Support](https://developer.paypal.com/support/)
