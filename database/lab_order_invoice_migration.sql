-- ============================================================
-- Lab order → invoice link migration
-- Run this on any server after deploying the initial schema.
-- Adds invoices.lab_order_id so the lab dashboard can tell
-- whether a patient has paid for their lab tests.
-- Safe to run multiple times (checks column existence first).
-- ============================================================

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'lab_order_id'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE invoices ADD COLUMN lab_order_id INT NULL AFTER patient_id, ADD FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
