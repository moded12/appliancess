# Payment Gateway Integration / تكامل بوابات الدفع

This document explains how to configure and test the Stripe and PayPal payment gateway integration.

هذا المستند يشرح كيفية تكوين واختبار تكامل بوابات الدفع Stripe و PayPal.

---

## Table of Contents / جدول المحتويات

1. [Prerequisites / المتطلبات الأساسية](#prerequisites--المتطلبات-الأساسية)
2. [Database Migration / ترحيل قاعدة البيانات](#database-migration--ترحيل-قاعدة-البيانات)
3. [Environment Configuration / تكوين البيئة](#environment-configuration--تكوين-البيئة)
4. [Stripe Setup / إعداد Stripe](#stripe-setup--إعداد-stripe)
5. [PayPal Setup / إعداد PayPal](#paypal-setup--إعداد-paypal)
6. [Testing / الاختبار](#testing--الاختبار)
7. [Webhook Configuration / تكوين Webhooks](#webhook-configuration--تكوين-webhooks)

---

## Prerequisites / المتطلبات الأساسية

### English
- PHP 7.4 or higher
- Composer (for Stripe PHP SDK)
- MySQL/MariaDB database
- SSL certificate (required for payment processing)
- Stripe account (sandbox mode)
- PayPal Developer account (sandbox mode)

### العربية
- PHP 7.4 أو أعلى
- Composer (لتثبيت Stripe PHP SDK)
- قاعدة بيانات MySQL/MariaDB
- شهادة SSL (مطلوبة لمعالجة الدفع)
- حساب Stripe (وضع sandbox)
- حساب PayPal Developer (وضع sandbox)

---

## Database Migration / ترحيل قاعدة البيانات

### English
Run the following command to create the payments table:

```bash
mysql -u your_user -p your_database < migrations/payments.sql
```

### العربية
نفّذ الأمر التالي لإنشاء جدول الدفعات:

```bash
mysql -u اسم_المستخدم -p اسم_قاعدة_البيانات < migrations/payments.sql
```

---

## Environment Configuration / تكوين البيئة

### English
Set the following environment variables on your server. **Never commit real API keys to the repository.**

```bash
# Stripe Configuration
export STRIPE_PUBLISHABLE_KEY="pk_test_..."
export STRIPE_SECRET_KEY="sk_test_..."
export STRIPE_WEBHOOK_SECRET="whsec_..."

# PayPal Configuration
export PAYPAL_CLIENT_ID="your_sandbox_client_id"
export PAYPAL_SECRET="your_sandbox_secret"
export PAYPAL_MODE="sandbox"  # Use "live" for production
export PAYPAL_WEBHOOK_ID="your_webhook_id"  # From PayPal Developer Dashboard

# Currency
export DEFAULT_CURRENCY="USD"
```

### العربية
اضبط متغيرات البيئة التالية على الخادم. **لا تقم أبداً بإضافة مفاتيح API الحقيقية إلى المستودع.**

```bash
# إعدادات Stripe
export STRIPE_PUBLISHABLE_KEY="pk_test_..."
export STRIPE_SECRET_KEY="sk_test_..."
export STRIPE_WEBHOOK_SECRET="whsec_..."

# إعدادات PayPal
export PAYPAL_CLIENT_ID="معرف_العميل_للاختبار"
export PAYPAL_SECRET="السر_للاختبار"
export PAYPAL_MODE="sandbox"  # استخدم "live" للإنتاج
export PAYPAL_WEBHOOK_ID="معرف_ويب_هوك"  # من لوحة PayPal Developer

# العملة
export DEFAULT_CURRENCY="USD"
```

---

## Composer Installation / تثبيت Composer

### English
Install PHP dependencies:

```bash
cd /path/to/your/project
composer install
```

### العربية
تثبيت تبعيات PHP:

```bash
cd /المسار/إلى/مشروعك
composer install
```

---

## Stripe Setup / إعداد Stripe

### English

1. **Create Stripe Account**
   - Go to [stripe.com](https://stripe.com) and create an account
   - Enable "Test mode" (toggle in dashboard)

2. **Get API Keys**
   - Go to Developers → API Keys
   - Copy "Publishable key" (starts with `pk_test_`)
   - Copy "Secret key" (starts with `sk_test_`)

3. **Configure Webhook**
   - Go to Developers → Webhooks
   - Click "Add endpoint"
   - Enter URL: `https://yourdomain.com/public/stripe_webhook.php`
   - Select events: `checkout.session.completed`, `checkout.session.expired`, `payment_intent.payment_failed`
   - Copy the "Signing secret" (starts with `whsec_`)

4. **Local Testing with Stripe CLI**
   ```bash
   # Install Stripe CLI
   # macOS: brew install stripe/stripe-cli/stripe
   # Windows: scoop install stripe
   
   # Login
   stripe login
   
   # Forward webhooks to local
   stripe listen --forward-to localhost/public/stripe_webhook.php
   ```

### العربية

1. **إنشاء حساب Stripe**
   - اذهب إلى [stripe.com](https://stripe.com) وأنشئ حساباً
   - فعّل "وضع الاختبار" (Test mode)

2. **الحصول على مفاتيح API**
   - اذهب إلى Developers → API Keys
   - انسخ "Publishable key" (يبدأ بـ `pk_test_`)
   - انسخ "Secret key" (يبدأ بـ `sk_test_`)

3. **تكوين Webhook**
   - اذهب إلى Developers → Webhooks
   - انقر "Add endpoint"
   - أدخل الرابط: `https://yourdomain.com/public/stripe_webhook.php`
   - اختر الأحداث: `checkout.session.completed`, `checkout.session.expired`, `payment_intent.payment_failed`
   - انسخ "Signing secret" (يبدأ بـ `whsec_`)

---

## PayPal Setup / إعداد PayPal

### English

1. **Create PayPal Developer Account**
   - Go to [developer.paypal.com](https://developer.paypal.com)
   - Create a developer account

2. **Create Sandbox App**
   - Go to Dashboard → Apps & Credentials
   - Select "Sandbox" tab
   - Click "Create App"
   - Copy "Client ID" and "Secret"

3. **Sandbox Test Accounts**
   - Go to Sandbox → Accounts
   - Create or use existing sandbox buyer/seller accounts
   - Merchant Email: `ajourisat@yahoo.com` (reference for your seller account)

4. **Configure Webhook (Optional)**
   - Go to Apps & Credentials → Your App → Add Webhook
   - Enter URL: `https://yourdomain.com/public/paypal_webhook.php`
   - Select events: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`

### العربية

1. **إنشاء حساب PayPal Developer**
   - اذهب إلى [developer.paypal.com](https://developer.paypal.com)
   - أنشئ حساب مطور

2. **إنشاء تطبيق Sandbox**
   - اذهب إلى Dashboard → Apps & Credentials
   - اختر تبويب "Sandbox"
   - انقر "Create App"
   - انسخ "Client ID" و "Secret"

3. **حسابات الاختبار**
   - اذهب إلى Sandbox → Accounts
   - أنشئ أو استخدم حسابات المشتري/البائع الموجودة
   - البريد التجاري: `ajourisat@yahoo.com` (مرجع لحساب البائع)

---

## Testing / الاختبار

### Stripe Test Cards / بطاقات اختبار Stripe

| Card Number | Description (EN) | الوصف (AR) |
|-------------|------------------|------------|
| `4242 4242 4242 4242` | Successful payment | دفع ناجح |
| `4000 0000 0000 3220` | 3D Secure required | يتطلب 3D Secure |
| `4000 0000 0000 0002` | Card declined | بطاقة مرفوضة |
| `4000 0000 0000 9995` | Insufficient funds | رصيد غير كافٍ |

**For all test cards:**
- Use any future expiry date (e.g., 12/34)
- Use any 3-digit CVC (e.g., 123)
- Use any 5-digit ZIP code (e.g., 12345)

### PayPal Sandbox Testing / اختبار PayPal Sandbox

1. Use sandbox buyer account credentials
2. Default sandbox buyer email: Check your PayPal Developer Dashboard
3. Login with sandbox credentials when redirected to PayPal

---

## Webhook Configuration / تكوين Webhooks

### Stripe Webhooks

| Event | Description (EN) | الوصف (AR) |
|-------|------------------|------------|
| `checkout.session.completed` | Payment successful | تم الدفع بنجاح |
| `checkout.session.expired` | Session expired | انتهت صلاحية الجلسة |
| `payment_intent.payment_failed` | Payment failed | فشل الدفع |

### PayPal Webhooks

| Event | Description (EN) | الوصف (AR) |
|-------|------------------|------------|
| `PAYMENT.CAPTURE.COMPLETED` | Payment captured | تم التقاط الدفع |
| `PAYMENT.CAPTURE.DENIED` | Payment denied | تم رفض الدفع |
| `PAYMENT.CAPTURE.REFUNDED` | Payment refunded | تم استرداد المبلغ |

---

## Files Added/Modified / الملفات المضافة/المعدّلة

| File | Description (EN) | الوصف (AR) |
|------|------------------|------------|
| `config.php` | Payment configuration keys | مفاتيح تكوين الدفع |
| `migrations/payments.sql` | Payments table schema | مخطط جدول الدفعات |
| `includes/payments.php` | Payment gateway functions | دوال بوابات الدفع |
| `public/pay.php` | Payment initiation endpoint | نقطة بدء الدفع |
| `public/paypal_return.php` | PayPal return handler | معالج عودة PayPal |
| `public/stripe_webhook.php` | Stripe webhook handler | معالج Stripe webhook |
| `public/paypal_webhook.php` | PayPal webhook handler | معالج PayPal webhook |
| `public/checkout.php` | Updated with Stripe/PayPal options | محدّث بخيارات Stripe/PayPal |
| `admin/order_view.php` | Display payment records | عرض سجلات الدفع |
| `composer.json` | Stripe PHP SDK dependency | تبعية Stripe PHP SDK |

---

## Security Considerations / اعتبارات الأمان

### English
- ⚠️ Never commit real API keys to the repository
- ⚠️ Always use HTTPS in production
- ⚠️ Verify webhook signatures before processing
- ⚠️ Use PDO prepared statements for database queries
- ⚠️ Validate order existence before creating payment sessions

### العربية
- ⚠️ لا تقم أبداً بإضافة مفاتيح API الحقيقية للمستودع
- ⚠️ استخدم دائماً HTTPS في الإنتاج
- ⚠️ تحقق من توقيعات webhook قبل المعالجة
- ⚠️ استخدم PDO prepared statements لاستعلامات قاعدة البيانات
- ⚠️ تحقق من وجود الطلب قبل إنشاء جلسات الدفع

---

## Troubleshooting / استكشاف الأخطاء

### Common Issues / مشاكل شائعة

1. **"Stripe secret key not configured"**
   - Ensure `STRIPE_SECRET_KEY` environment variable is set
   - تأكد من ضبط متغير البيئة `STRIPE_SECRET_KEY`

2. **"PayPal credentials not configured"**
   - Ensure `PAYPAL_CLIENT_ID` and `PAYPAL_SECRET` are set
   - تأكد من ضبط `PAYPAL_CLIENT_ID` و `PAYPAL_SECRET`

3. **Webhook signature verification failed**
   - Check that `STRIPE_WEBHOOK_SECRET` matches the one in Stripe Dashboard
   - تحقق من تطابق `STRIPE_WEBHOOK_SECRET` مع لوحة Stripe

4. **"Class 'Stripe\Stripe' not found"**
   - Run `composer install` to install dependencies
   - نفّذ `composer install` لتثبيت التبعيات

---

## Support / الدعم

For issues or questions, please contact the development team.

للمشاكل أو الأسئلة، يرجى التواصل مع فريق التطوير.
