-- migrations/payments.sql
-- إنشاء جدول payments لتسجيل معاملات الدفع

-- تحديث جدول orders لإضافة طرق دفع جديدة (إذا لزم الأمر)
ALTER TABLE orders 
  MODIFY COLUMN payment_method ENUM('cod','stripe','paypal','cliq','card') NOT NULL DEFAULT 'cod';

-- إنشاء جدول payments
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway ENUM('stripe','paypal','cod','cliq','card_stub') NOT NULL,
  transaction_id VARCHAR(255) NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending','processing','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  raw_response TEXT NULL COMMENT 'JSON response from payment gateway',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_order_id (order_id),
  INDEX idx_transaction_id (transaction_id),
  INDEX idx_status (status),
  INDEX idx_gateway (gateway)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة فهرس على created_at للاستعلامات المرتبة بالتاريخ
CREATE INDEX idx_payments_created_at ON payments(created_at);
