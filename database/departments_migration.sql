-- ============================================================
-- Departments migration
-- Run this on any server after deploying the initial schema.
-- Safe to run multiple times (renames check old name exists,
-- inserts use WHERE NOT EXISTS to avoid duplicates).
-- ============================================================

-- Step 1: Rename 3 existing departments to standard names
UPDATE departments SET name = 'Ophthalmology'  WHERE name = 'Eyes';
UPDATE departments SET name = 'General Surgery' WHERE name = 'Surgery';
UPDATE departments SET name = 'Gynecology'      WHERE name = 'Obstetrics & Gynecology';

-- Step 2: Insert 15 new departments (skips each if name already exists)
INSERT INTO departments (name) SELECT 'Internal Medicine'  WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Internal Medicine');
INSERT INTO departments (name) SELECT 'ENT'                WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'ENT');
INSERT INTO departments (name) SELECT 'Psychiatry'         WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Psychiatry');
INSERT INTO departments (name) SELECT 'Radiology'          WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Radiology');
INSERT INTO departments (name) SELECT 'Anesthesiology'     WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Anesthesiology');
INSERT INTO departments (name) SELECT 'Urology'            WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Urology');
INSERT INTO departments (name) SELECT 'Nephrology'         WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Nephrology');
INSERT INTO departments (name) SELECT 'Gastroenterology'   WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Gastroenterology');
INSERT INTO departments (name) SELECT 'Pulmonology'        WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Pulmonology');
INSERT INTO departments (name) SELECT 'Oncology'           WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Oncology');
INSERT INTO departments (name) SELECT 'Endocrinology'      WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Endocrinology');
INSERT INTO departments (name) SELECT 'Rheumatology'       WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Rheumatology');
INSERT INTO departments (name) SELECT 'Infectious Disease' WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Infectious Disease');
INSERT INTO departments (name) SELECT 'Emergency Medicine' WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Emergency Medicine');
INSERT INTO departments (name) SELECT 'Pathology'          WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Pathology');

-- Verify: should return 26 rows
-- SELECT COUNT(*), GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') FROM departments;
