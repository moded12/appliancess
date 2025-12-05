-- Payments Table Migration
-- Run: mysql -u your_user -p your_database < migrations/payments.sql

-- Create payments table for tracking all payment transactions
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway VARCHAR(50) NOT NULL COMMENT 'Payment gateway: stripe, paypal, cod, cliq, etc.',
  transaction_id VARCHAR(255) NULL COMMENT 'Transaction ID from the payment gateway',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  raw_response TEXT NULL COMMENT 'JSON response from payment gateway',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign key constraint (can be removed if not desired)
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  
  -- Index for faster lookups
  INDEX idx_payments_order_id (order_id),
  INDEX idx_payments_gateway (gateway),
  INDEX idx_payments_transaction_id (transaction_id),
  INDEX idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add paypal to payment_method enum if not exists
-- Note: This ALTER may fail if 'paypal' already exists, which is fine
ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod','stripe','paypal','cliq','card') NOT NULL DEFAULT 'cod';
