# دليل إعداد واختبار بوابات الدفع

هذا الدليل يوضح كيفية إعداد واختبار بوابات الدفع (Stripe و PayPal) في وضع sandbox.

## المتطلبات الأساسية

- PHP 7.4 أو أعلى
- Composer
- cURL extension
- اتصال بالإنترنت

## تثبيت المتطلبات

```bash
cd /path/to/appliancess
composer install
```

## إعداد قاعدة البيانات

قم بتشغيل ملف الـ migration لإنشاء جدول payments:

```sql
SOURCE migrations/payments.sql;
```

أو يدوياً:

```bash
mysql -u appliance -p appliance < migrations/payments.sql
```

---

## إعداد Stripe (Sandbox)

### 1. إنشاء حساب Stripe

1. اذهب إلى [Stripe Dashboard](https://dashboard.stripe.com/register)
2. أنشئ حساباً جديداً أو سجل الدخول
3. تأكد من تفعيل **Test mode** (الوضع التجريبي)

### 2. الحصول على المفاتيح

1. اذهب إلى **Developers > API keys**
2. انسخ **Publishable key** (يبدأ بـ `pk_test_`)
3. انسخ **Secret key** (يبدأ بـ `sk_test_`)

### 3. إعداد Webhook

1. اذهب إلى **Developers > Webhooks**
2. اضغط **Add endpoint**
3. أدخل URL: `https://yourdomain.com/public/stripe_webhook.php`
4. اختر الأحداث التالية:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
5. اضغط **Add endpoint**
6. انسخ **Signing secret** (يبدأ بـ `whsec_`)

### 4. تعيين متغيرات البيئة

```bash
export STRIPE_PUBLISHABLE_KEY="pk_test_..."
export STRIPE_SECRET_KEY="sk_test_..."
export STRIPE_WEBHOOK_SECRET="whsec_..."
```

أو أضفها في `.env` أو في إعدادات الخادم.

### 5. أرقام بطاقات الاختبار

| رقم البطاقة         | الوصف                    |
|---------------------|--------------------------|
| 4242 4242 4242 4242 | دفع ناجح                 |
| 4000 0000 0000 9995 | رفض البطاقة              |
| 4000 0000 0000 3220 | يتطلب 3D Secure          |

- تاريخ الانتهاء: أي تاريخ مستقبلي (مثل 12/34)
- CVV: أي 3 أرقام (مثل 123)

---

## إعداد PayPal (Sandbox)

### 1. إنشاء حساب Developer

1. اذهب إلى [PayPal Developer](https://developer.paypal.com/)
2. سجل الدخول بحساب PayPal أو أنشئ حساباً جديداً

### 2. إنشاء تطبيق Sandbox

1. اذهب إلى **Dashboard > My Apps & Credentials**
2. تأكد من أنك في وضع **Sandbox**
3. اضغط **Create App**
4. أدخل اسم التطبيق واضغط **Create App**
5. انسخ **Client ID** و **Secret**

### 3. إنشاء حسابات اختبار

1. اذهب إلى **Sandbox > Accounts**
2. ستجد حسابين جاهزين:
   - **Business** (للتاجر)
   - **Personal** (للمشتري)
3. اضغط على الحساب الشخصي > **View/Edit Account**
4. انسخ البريد الإلكتروني وكلمة المرور للاختبار

### 4. إعداد Webhook (اختياري)

1. اذهب إلى **Dashboard > My Apps & Credentials**
2. اختر تطبيقك
3. اضغط **Add Webhook**
4. أدخل URL: `https://yourdomain.com/public/paypal_webhook.php`
5. اختر الأحداث:
   - `CHECKOUT.ORDER.APPROVED`
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`
   - `PAYMENT.CAPTURE.REFUNDED`

### 5. تعيين متغيرات البيئة

```bash
export PAYPAL_CLIENT_ID="AV..."
export PAYPAL_SECRET="EH..."
export PAYPAL_MODE="sandbox"
```

---

## اختبار الدفع

### اختبار Stripe

1. أضف منتجاً للسلة
2. اذهب للدفع (checkout)
3. اختر **Stripe (Visa/MasterCard)**
4. أكمل بيانات العميل واضغط **تأكيد الطلب**
5. ستُعاد توجيهك لصفحة Stripe Checkout
6. استخدم بطاقة اختبار: `4242 4242 4242 4242`
7. أكمل الدفع وتحقق من تحديث حالة الطلب

### اختبار PayPal

1. أضف منتجاً للسلة
2. اذهب للدفع (checkout)
3. اختر **PayPal**
4. أكمل بيانات العميل واضغط **تأكيد الطلب**
5. ستُعاد توجيهك لصفحة PayPal
6. سجل الدخول بحساب المشتري التجريبي
7. وافق على الدفع وتحقق من تحديث حالة الطلب

---

## التحقق من سجلات الدفع

### من لوحة الإدارة

1. اذهب إلى **الطلبات** في لوحة الإدارة
2. اختر طلباً لعرضه
3. ستجد قسم **سجلات الدفع** يعرض:
   - البوابة (Stripe/PayPal)
   - رقم المعاملة
   - المبلغ والعملة
   - الحالة (pending/paid/failed)
   - التاريخ

### من قاعدة البيانات

```sql
SELECT * FROM payments ORDER BY created_at DESC LIMIT 10;
```

---

## استكشاف الأخطاء

### Stripe

- تأكد من تثبيت `stripe/stripe-php` via Composer
- تأكد من صحة المفاتيح (test keys تبدأ بـ `pk_test_` و `sk_test_`)
- تحقق من سجلات Webhook في Stripe Dashboard
- راجع سجلات الخادم للأخطاء

### PayPal

- تأكد من أنك في وضع Sandbox وليس Live
- تأكد من صحة Client ID و Secret
- استخدم حساب المشتري التجريبي وليس حسابك الحقيقي
- راجع PayPal Developer Dashboard للأحداث والأخطاء

---

## الانتقال للإنتاج

عند الجاهزية للإنتاج:

1. **Stripe**: استبدل مفاتيح test بمفاتيح live من Stripe Dashboard
2. **PayPal**: غيّر `PAYPAL_MODE` من `sandbox` إلى `live` واستخدم بيانات الإنتاج
3. أعد إنشاء Webhooks بروابط الإنتاج
4. اختبر بمبالغ صغيرة قبل الإطلاق الكامل

---

## الأمان

- **لا تخزن** مفاتيح API في الكود أو Git
- استخدم متغيرات البيئة أو ملفات `.env` (مع إضافتها لـ `.gitignore`)
- تحقق دائماً من توقيع Webhooks
- استخدم HTTPS في الإنتاج

---

## المراجع

- [Stripe Documentation](https://stripe.com/docs)
- [Stripe Testing](https://stripe.com/docs/testing)
- [PayPal Developer](https://developer.paypal.com/docs/)
- [PayPal Sandbox](https://developer.paypal.com/docs/api-basics/sandbox/)
