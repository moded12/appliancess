-- Migration: إنشاء جدول المدفوعات (payments)
-- يتتبع جميع عمليات الدفع عبر بوابات الدفع المختلفة (Stripe, PayPal, إلخ)

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway ENUM('stripe', 'paypal', 'cod', 'cliq', 'other') NOT NULL DEFAULT 'other',
  transaction_id VARCHAR(255) NULL COMMENT 'معرف المعاملة من بوابة الدفع',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  raw_response TEXT NULL COMMENT 'الاستجابة الكاملة من بوابة الدفع (JSON)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign key to orders table
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  
  -- Index for faster lookups
  INDEX idx_order_id (order_id),
  INDEX idx_transaction_id (transaction_id),
  INDEX idx_gateway (gateway),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تحديث جدول orders لإضافة أنواع الدفع الجديدة إذا لم تكن موجودة
-- هذا اختياري ويمكن تشغيله يدوياً إذا لزم الأمر
-- ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod','stripe','paypal','cliq','card') NOT NULL DEFAULT 'cod';
