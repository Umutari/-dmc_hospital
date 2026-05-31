-- ============================================================
--  DMC - Dream Medical Center Hospital
--  Database Schema v2
--  KK 541 Street, Kigali | Phone: 0782 749 660
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS dmc_hospital;
CREATE DATABASE dmc_hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dmc_hospital;

-- ── Departments ──────────────────────────────────────────────
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    head_doctor_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Users (all roles) ────────────────────────────────────────
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','doctor','nurse','receptionist','pharmacist','accountant','lab_technician','patient') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Patients ─────────────────────────────────────────────────
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_no VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-'),
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    insurance_provider VARCHAR(100),
    insurance_number VARCHAR(50),
    balance DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    registered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Doctors (extra profile) ──────────────────────────────────
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    department_id INT,
    specialization VARCHAR(100),
    qualification VARCHAR(200),
    license_number VARCHAR(50),
    consultation_fee DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- ── Appointments ─────────────────────────────────────────────
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_no VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    type ENUM('consultation','follow_up','emergency','routine_checkup','lab_visit','surgery') DEFAULT 'consultation',
    reason TEXT,
    status ENUM('scheduled','confirmed','completed','cancelled','no_show') DEFAULT 'scheduled',
    notes TEXT,
    booked_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (booked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Medical Records ──────────────────────────────────────────
CREATE TABLE medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    visit_date DATE NOT NULL,
    symptoms TEXT,
    diagnosis TEXT,
    treatment_plan TEXT,
    notes TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- ── Vital Signs ──────────────────────────────────────────────
CREATE TABLE vital_signs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    appointment_id INT,
    temperature DECIMAL(4,1),
    blood_pressure_sys INT,
    blood_pressure_dia INT,
    pulse_rate INT,
    respiratory_rate INT,
    oxygen_saturation DECIMAL(4,1),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,2),
    notes TEXT,
    recorded_by INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Medicine Categories ──────────────────────────────────────
CREATE TABLE medicine_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- ── Medicines ────────────────────────────────────────────────
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    generic_name VARCHAR(150),
    category_id INT,
    unit VARCHAR(30),
    current_stock INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    purchase_price DECIMAL(10,2) DEFAULT 0,
    selling_price DECIMAL(10,2) DEFAULT 0,
    expiry_date DATE,
    manufacturer VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL
);

-- ── Prescriptions ────────────────────────────────────────────
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_no VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    medical_record_id INT,
    status ENUM('pending','dispensed','partial','cancelled') DEFAULT 'pending',
    notes TEXT,
    dispensed_by INT,
    dispensed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    FOREIGN KEY (dispensed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Prescription Items ───────────────────────────────────────
CREATE TABLE prescription_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    quantity INT DEFAULT 1,
    notes TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- ── Lab Tests ────────────────────────────────────────────────
CREATE TABLE lab_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    reference_range VARCHAR(100),
    unit VARCHAR(30),
    turnaround_hours INT DEFAULT 24,
    is_active TINYINT(1) DEFAULT 1
);

-- ── Lab Orders ───────────────────────────────────────────────
CREATE TABLE lab_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_no VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    medical_record_id INT,
    status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
    priority ENUM('routine','urgent','stat') DEFAULT 'routine',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL
);

-- ── Lab Order Items ──────────────────────────────────────────
CREATE TABLE lab_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lab_order_id INT NOT NULL,
    lab_test_id INT NOT NULL,
    result TEXT,
    result_notes TEXT,
    status ENUM('pending','completed') DEFAULT 'pending',
    performed_by INT,
    completed_at DATETIME,
    FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Room Types ───────────────────────────────────────────────
CREATE TABLE room_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price_per_day DECIMAL(10,2) DEFAULT 0,
    description TEXT
);

-- ── Rooms ────────────────────────────────────────────────────
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_no VARCHAR(20) UNIQUE NOT NULL,
    room_type_id INT,
    floor VARCHAR(10),
    is_occupied TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE SET NULL
);

-- ── Admissions ───────────────────────────────────────────────
CREATE TABLE admissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    room_id INT,
    doctor_id INT,
    admitted_by INT,
    reason TEXT,
    admitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    discharged_at DATETIME,
    status ENUM('active','discharged') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admitted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Invoices ─────────────────────────────────────────────────
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_no VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    total DECIMAL(12,2) DEFAULT 0,
    paid DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft','issued','partial','paid','cancelled') DEFAULT 'draft',
    due_date DATE,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Invoice Items ────────────────────────────────────────────
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- ── Payments ─────────────────────────────────────────────────
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_no VARCHAR(30) UNIQUE NOT NULL,
    invoice_id INT NOT NULL,
    patient_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('cash','momo_mtn','momo_airtel','card','insurance','bank_transfer') NOT NULL,
    status ENUM('pending','success','failed','refunded') DEFAULT 'success',
    flw_transaction_id VARCHAR(100),
    flw_ref VARCHAR(100),
    otp_verified TINYINT(1) DEFAULT 0,
    notes TEXT,
    collected_by INT,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── SMS Logs ─────────────────────────────────────────────────
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    channel ENUM('sms','whatsapp','both') DEFAULT 'sms',
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Notifications ────────────────────────────────────────────
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Audit Logs ───────────────────────────────────────────────
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── System Settings ──────────────────────────────────────────
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Insurance Providers ───────────────────────────────────────
CREATE TABLE insurance_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    coverage_percentage INT DEFAULT 80,
    patient_percentage INT DEFAULT 20,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Insurance Coverage History ───────────────────────────────
CREATE TABLE insurance_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    patient_id INT NOT NULL,
    insurance_provider VARCHAR(100),
    total_amount DECIMAL(10,2),
    insurance_amount DECIMAL(10,2),
    patient_amount DECIMAL(10,2),
    insurance_status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_date DATETIME,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════
--  SEED DATA
-- ════════════════════════════════════════════════════════════

-- Insurance Providers (Rwanda)
INSERT INTO insurance_providers (name, coverage_percentage, patient_percentage, description, is_active) VALUES
('RSSB', 85, 15, 'Rwanda Social Security Board', 1),
('MEDIPLAN', 80, 20, 'Mediplan Insurance Rwanda', 1),
('SONATUBANK', 70, 30, 'SonaTuBanque Insurance', 1),
('UMURENGE', 75, 25, 'Umurenge Cooperative Insurance', 1),
('PRIVATE', 0, 100, 'No Insurance - Patient Pays 100%', 1);

-- Departments
INSERT INTO departments (name, description) VALUES
('General Medicine', 'General outpatient consultations'),
('Surgery', 'Surgical procedures and post-op care'),
('Pediatrics', 'Children healthcare'),
('Obstetrics & Gynecology', 'Women health and maternity'),
('Cardiology', 'Heart and cardiovascular care'),
('Orthopedics', 'Bones, joints and musculoskeletal'),
('Dermatology', 'Skin conditions'),
('Neurology', 'Brain and nervous system'),
('Pharmacy', 'Drug dispensing and management'),
('Laboratory', 'Diagnostic tests and results');

-- Medicine Categories
INSERT INTO medicine_categories (name) VALUES
('Antibiotics'),('Analgesics'),('Antihypertensives'),('Antivirals'),
('Vitamins & Supplements'),('Antidiabetics'),('Antihistamines'),('Antiseptics');

-- Lab Tests (with categories)
INSERT INTO lab_tests (name, category, price, reference_range, unit, turnaround_hours) VALUES
('Complete Blood Count (CBC)', 'Hematology', 5000, '4.5-11.0', '×10³/μL', 4),
('Blood Glucose (Fasting)', 'Biochemistry', 3000, '3.9-5.6', 'mmol/L', 2),
('Liver Function Test (LFT)', 'Biochemistry', 8000, 'Varies', '', 6),
('Kidney Function Test (KFT)', 'Biochemistry', 8000, 'Varies', '', 6),
('HIV Test', 'Serology', 3000, 'Negative', '', 2),
('Malaria RDT', 'Parasitology', 2000, 'Negative', '', 1),
('Urinalysis', 'Urinalysis', 2500, 'Normal', '', 2),
('Chest X-Ray', 'Radiology', 10000, 'Normal', '', 3),
('ECG (12-Lead)', 'Cardiology', 8000, 'Normal sinus rhythm', '', 1),
('Stool Analysis', 'Parasitology', 2500, 'No ova/cysts', '', 4),
('Thyroid Function (TSH)', 'Endocrinology', 10000, '0.4-4.0', 'mIU/L', 6),
('Blood Group & Rhesus', 'Hematology', 2000, '', '', 1);

-- Room Types
INSERT INTO room_types (name, price_per_day, description) VALUES
('General Ward', 5000, 'Shared ward with 4-6 beds'),
('Semi-Private', 10000, 'Shared room with 2 beds'),
('Private Room', 20000, 'Single occupancy room'),
('ICU', 80000, 'Intensive Care Unit'),
('Maternity Ward', 15000, 'Maternity and postnatal care');

-- Rooms
INSERT INTO rooms (room_no, room_type_id, floor) VALUES
('G01', 1, 'Ground'), ('G02', 1, 'Ground'), ('G03', 1, 'Ground'),
('S01', 2, '1st'), ('S02', 2, '1st'),
('P01', 3, '1st'), ('P02', 3, '1st'), ('P03', 3, '2nd'),
('ICU1', 4, '2nd'), ('ICU2', 4, '2nd'),
('M01', 5, 'Ground'), ('M02', 5, 'Ground');

-- Admin user (password: password)
INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES
('System', 'Admin', 'admin@dmc.rw', '0782749660',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample staff
INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES
('Jean Pierre', 'Habimana', 'doctor@dmc.rw', '0788112233',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('Marie', 'Mukamana', 'receptionist@dmc.rw', '0788334455',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist'),
('Claude', 'Bizimana', 'pharmacist@dmc.rw', '0788556677',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist'),
('Alice', 'Uwimana', 'accountant@dmc.rw', '0788778899',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'accountant'),
('Patrick', 'Nzeyimana', 'nurse@dmc.rw', '0788990011',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nurse'),
('Eric', 'Tuyishime', 'lab@dmc.rw', '0788001122',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lab_technician');

-- Doctor profile for Dr. Jean Pierre
INSERT INTO doctors (user_id, department_id, specialization, consultation_fee) VALUES
(2, 1, 'General Medicine', 5000);

-- Sample Medicines
INSERT INTO medicines (name, generic_name, category_id, unit, current_stock, reorder_level, purchase_price, selling_price) VALUES
('Amoxicillin 500mg', 'Amoxicillin', 1, 'capsules', 500, 50, 200, 350),
('Paracetamol 500mg', 'Paracetamol', 2, 'tablets', 1000, 100, 50, 100),
('Ibuprofen 400mg', 'Ibuprofen', 2, 'tablets', 300, 50, 80, 150),
('Metformin 500mg', 'Metformin', 6, 'tablets', 200, 30, 150, 250),
('Amlodipine 5mg', 'Amlodipine', 3, 'tablets', 150, 30, 200, 350),
('ORS Sachets', 'Oral Rehydration Salts', 5, 'sachets', 200, 50, 100, 200),
('Vitamin C 500mg', 'Ascorbic Acid', 5, 'tablets', 400, 50, 80, 150),
('Doxycycline 100mg', 'Doxycycline', 1, 'capsules', 100, 20, 300, 500),
('Artemether/Lumefantrine', 'Coartem', 4, 'tablets', 80, 20, 1500, 2500),
('Normal Saline 500ml', 'Sodium Chloride 0.9%', 8, 'vials', 60, 15, 800, 1500);

-- Patient user account (password: password)
INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES
('Yvette', 'Umutari', 'patient@dmc.rw', '0780777770',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient');

-- Sample Patient (Yvette Umutari) — linked to the patient user above
INSERT INTO patients (patient_no, first_name, last_name, date_of_birth, gender, blood_group, phone, email, address, insurance_provider, status, registered_by) VALUES
('DMC-P-2024-0001', 'Yvette', 'Umutari', '1995-03-15', 'female', 'B+', '0780777770', 'patient@dmc.rw', 'KG 9 Ave, Kigali', 'RSSB', 'active', 1);

-- Additional sample patients
INSERT INTO patients (patient_no, first_name, last_name, date_of_birth, gender, blood_group, phone, email, address, status, registered_by) VALUES
('DMC-P-2024-0002', 'Jean Paul', 'Nkurunziza', '1988-07-22', 'male', 'O+', '0788222111', 'jpaul@example.com', 'KK 15 Ave, Kigali', 'active', 1),
('DMC-P-2024-0003', 'Amina', 'Cyusa', '2001-11-05', 'female', 'A+', '0786333222', '', 'Remera, Kigali', 'active', 1),
('DMC-P-2024-0004', 'Emmanuel', 'Rugema', '1975-02-14', 'male', 'B-', '0784444333', '', 'Kimironko, Kigali', 'active', 1);

-- System Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('hospital_name', 'DMC - Dream Medical Center'),
('hospital_tagline', 'Your Health, Our Priority'),
('hospital_address', 'KK 541 Street, Kigali, Rwanda'),
('hospital_phone', '0782 749 660'),
('hospital_email', 'info@dmc.rw'),
('flw_public_key', 'FLWPUBK_TEST-SANDBOXDEMOKEY-X'),
('flw_secret_key', ''),
('mista_api_key', '573|q3fyofhnmy7ux39FFrfbseOqufq0nGbQSg2pBxo2'),
('wa_token', 'EAAYhC3gxzUgBRvZA4FGBw9pZCVaBqqy96JlyDk905WFc9MQZAd32MQJNlXRz45oZBzKW7KYMirlJ2J85KsarU6NjioNfdroV24ZBSb5YZB5xRsGmkpu4VRWBJZCZA7mwtuyZCUAkJO38N0LRzeFkfbmktEtxTtTOXgCU815yEmsuh9ZBDNpe9UkhWx8a78HinG9q3b37Cn4I2NCIZCvsD4B5kmdzQQC5p1TwqxB2tIOIJbbWfzhTXPuqiibQkFktZALuXpE7CFBBLqr1cNZB72UhSPlPqTQZDZD'),
('wa_phone_id', '1133350083195956'),
('default_otp', '000000'),
('sms_sender_id', 'DMC Hospital'),
('currency', 'RWF');

-- Sample appointments (adjust dates relative to today)
INSERT INTO appointments (appointment_no, patient_id, doctor_id, department_id, appointment_date, appointment_time, type, reason, status, booked_by) VALUES
('DMC-A-2024-0001', 1, 2, 1, CURDATE(), '09:00:00', 'consultation', 'General check-up and follow-up', 'scheduled', 3),
('DMC-A-2024-0002', 2, 2, 1, CURDATE(), '10:00:00', 'consultation', 'Fever and headache', 'confirmed', 3),
('DMC-A-2024-0003', 3, 2, 1, CURDATE(), '11:00:00', 'follow_up', 'Post-treatment review', 'scheduled', 3),
('DMC-A-2024-0004', 4, 2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'consultation', 'Hypertension management', 'scheduled', 3);

-- NOTE: Default password for all accounts is "password"
-- Change passwords after first login!
-- ─────────────────────────────────────────────────────────────
-- Account credentials summary:
--   admin@dmc.rw       / password   (Admin)
--   doctor@dmc.rw      / password   (Doctor)
--   nurse@dmc.rw       / password   (Nurse)
--   receptionist@dmc.rw/ password   (Receptionist)
--   pharmacist@dmc.rw  / password   (Pharmacist)
--   accountant@dmc.rw  / password   (Accountant)
--   lab@dmc.rw         / password   (Lab Technician)
--   patient@dmc.rw     / password   (Patient — Yvette Umutari)
-- ─────────────────────────────────────────────────────────────
