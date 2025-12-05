-- Migration: Create payments table for PayPal and Stripe integration
-- Run this SQL after setting up the database with db.sql

-- Payments table to track all payment transactions
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

-- Add index for faster lookups
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_payments_transaction_id ON payments(transaction_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_gateway ON payments(gateway);

-- Update orders table to support new payment methods if needed
-- This is optional and depends on your existing orders table structure
-- ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod','stripe','paypal','cliq','card') NOT NULL DEFAULT 'cod';
