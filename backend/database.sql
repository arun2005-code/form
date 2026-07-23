-- ============================================================
-- Smart Customer Registration System
-- Database Schema (MySQL)
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_customer_registration
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE smart_customer_registration;

-- ------------------------------------------------------------
-- Table: customers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    customer_id       VARCHAR(20)  NOT NULL UNIQUE,        -- e.g. CUS0001
    full_name         VARCHAR(150) NOT NULL,
    father_name       VARCHAR(150) NOT NULL,
    mobile_number     VARCHAR(15)  NOT NULL,
    whatsapp_number   VARCHAR(15)  NOT NULL,
    email             VARCHAR(150) NOT NULL,
    gender            ENUM('Male','Female','Other') NOT NULL,
    dob               DATE NOT NULL,
    age               INT NOT NULL,
    address            TEXT NOT NULL,
    city              VARCHAR(100) NOT NULL,
    district          VARCHAR(100) NOT NULL,
    state             VARCHAR(100) NOT NULL,
    pincode           VARCHAR(10)  NOT NULL,
    occupation        VARCHAR(100) NOT NULL,
    company_name      VARCHAR(150),
    annual_income     VARCHAR(50),
    preferred_language VARCHAR(50),
    customer_category VARCHAR(50),
    aadhar_number     VARCHAR(20) NOT NULL,
    pan_number        VARCHAR(20) NOT NULL,
    gst_number        VARCHAR(20),
    photo_path        VARCHAR(255),
    id_proof_path     VARCHAR(255),
    signature_path    VARCHAR(255),
    reference_name    VARCHAR(150),
    reference_mobile  VARCHAR(15),
    source            ENUM('Instagram','Facebook','WhatsApp','Google','Friend','Other') DEFAULT 'Other',
    remarks           TEXT,
    terms_accepted    TINYINT(1) NOT NULL DEFAULT 0,
    registration_no   VARCHAR(30),
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_customer_id (customer_id),
    INDEX idx_district (district),
    INDEX idx_city (city),
    INDEX idx_gender (gender),
    INDEX idx_occupation (occupation),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: customer_id_sequence  (keeps CUS0001, CUS0002... safe under concurrency)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_id_sequence (
    id  INT PRIMARY KEY AUTO_INCREMENT,
    tag CHAR(1) NOT NULL DEFAULT 'x'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: admins (for the dashboard login)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account
-- IMPORTANT: Run this once in PHP to generate a real password hash, then
-- paste the result below before importing this file:
--
--   <?php echo password_hash('Admin@123', PASSWORD_DEFAULT); ?>
--
-- Replace REPLACE_WITH_GENERATED_HASH with that output.
INSERT INTO admins (username, password_hash) VALUES
('admin', 'REPLACE_WITH_GENERATED_HASH')
ON DUPLICATE KEY UPDATE username = username;
