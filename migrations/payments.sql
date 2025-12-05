-- Migration: Add payments table and update orders table for payment gateways
-- Run this migration to enable PayPal and Stripe payment integration

-- ============================================================
-- 1. Create payments table to store payment records
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway ENUM('paypal', 'stripe', 'cod', 'cliq') NOT NULL,
  transaction_id VARCHAR(255) NULL,
  amount DECIMAL(10, 2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  gateway_response TEXT NULL COMMENT 'JSON response from payment gateway',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_transaction (transaction_id),
  INDEX idx_order_gateway (order_id, gateway),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Update orders table payment_method ENUM to include paypal and stripe
-- ============================================================
ALTER TABLE orders 
  MODIFY COLUMN payment_method ENUM('cod', 'stripe', 'paypal', 'cliq', 'card') NOT NULL DEFAULT 'cod';

-- ============================================================
-- 3. Add gateway column to orders table if not exists
-- ============================================================
-- Check and add gateway column
SET @preparedStatement = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'gateway') = 0,
    'ALTER TABLE orders ADD COLUMN gateway VARCHAR(50) NULL AFTER payment_status',
    'SELECT 1'
  )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 4. Add transaction_id column to orders table if not exists
-- ============================================================
SET @preparedStatement = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'transaction_id') = 0,
    'ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(255) NULL AFTER gateway',
    'SELECT 1'
  )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 5. Add payment_meta column to orders table if not exists
-- ============================================================
SET @preparedStatement = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_meta') = 0,
    'ALTER TABLE orders ADD COLUMN payment_meta JSON NULL AFTER transaction_id',
    'SELECT 1'
  )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 6. Add index on transaction_id for orders table
-- ============================================================
SET @preparedStatement = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_transaction_id') = 0,
    'ALTER TABLE orders ADD INDEX idx_transaction_id (transaction_id)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
