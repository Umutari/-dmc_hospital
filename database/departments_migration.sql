-- ============================================================
-- Departments migration
-- Run this on any server after deploying the initial schema.
-- Safe to run multiple times (uses UPDATE with WHERE and
-- INSERT ... ON DUPLICATE KEY UPDATE).
-- ============================================================

-- Step 1: Rename 3 existing departments to standard names
UPDATE departments SET name = 'Ophthalmology'  WHERE name = 'Eyes';
UPDATE departments SET name = 'General Surgery' WHERE name = 'Surgery';
UPDATE departments SET name = 'Gynecology'      WHERE name = 'Obstetrics & Gynecology';

-- Step 2: Insert 15 new departments (skips if name already exists)
INSERT INTO departments (name) VALUES ('Internal Medicine')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('ENT')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Psychiatry')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Radiology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Anesthesiology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Urology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Nephrology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Gastroenterology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Pulmonology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Oncology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Endocrinology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Rheumatology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Infectious Disease')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Emergency Medicine')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO departments (name) VALUES ('Pathology')
  ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Verify: should return 26 rows
-- SELECT COUNT(*), GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') FROM departments;
