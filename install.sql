-- Fresh install for XAMPP / phpMyAdmin.
CREATE DATABASE IF NOT EXISTS kyc_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kyc_system;

-- Parent table. Every personal KYC table below uses users.id as its foreign key.
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('APPLICANT','REVIEWER','ADMIN') NOT NULL DEFAULT 'APPLICANT',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- One address record per user. Both addresses are stored independently.
CREATE TABLE addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  permanent_address TEXT NOT NULL,
  temporary_address TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One set of educational certificates per user. Values are uploaded file paths.
CREATE TABLE education (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  see_document VARCHAR(255) NULL,
  slc_document VARCHAR(255) NULL,
  graduate_document VARCHAR(255) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_education_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Additional government identity documents for one user.
CREATE TABLE additional_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  citizenship_document VARCHAR(255) NULL,
  passport_document VARCHAR(255) NULL,
  license_document VARCHAR(255) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_additional_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Application workflow is separate, so a user can submit more than one KYC request.
CREATE TABLE applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NULL,
  status ENUM('DRAFT','SUBMITTED','UNDER_REVIEW','APPROVED','REJECTED','RESUBMISSION_REQUESTED') NOT NULL DEFAULT 'DRAFT',
  full_name VARCHAR(160) NULL, date_of_birth DATE NULL, nationality VARCHAR(100) NULL,
  id_type VARCHAR(60) NULL, id_number VARCHAR(100) NULL, id_expiry DATE NULL, issuing_country VARCHAR(100) NULL,
  review_notes TEXT NULL, reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_applicant FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL, actor_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL, detail TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- After registering an account, make it a staff account if needed:
-- UPDATE users SET role='ADMIN' WHERE email='admin@example.com';
