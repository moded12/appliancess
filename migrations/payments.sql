-- Migration: Create payments table for storing payment gateway transactions
-- Run this migration after db.sql to add payment tracking functionality

-- Payments table to track all payment gateway transactions
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  gateway ENUM('stripe','paypal','cod','cliq') NOT NULL,
  transaction_id VARCHAR(255) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('pending','processing','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  raw_response TEXT NULL COMMENT 'JSON response from payment gateway',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign key to orders table
  CONSTRAINT fk_payments_order_id 
    FOREIGN KEY (order_id) REFERENCES orders(id) 
    ON DELETE CASCADE,
  
  -- Index for faster lookups
  INDEX idx_payments_order_id (order_id),
  INDEX idx_payments_gateway (gateway),
  INDEX idx_payments_status (status),
  INDEX idx_payments_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update orders table to add 'paypal' to payment_method enum if not already present
-- Note: MySQL doesn't support IF NOT EXISTS for ALTER, so this may need manual adjustment
-- ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod','stripe','paypal','cliq','card') NOT NULL DEFAULT 'cod';
