-- ============================================================
-- DHAKA MODEL AGENCY - GROOMING REGISTRATION CAMPAIGN
-- Database Schema
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+06:00";

-- ------------------------------------------------------------
-- Table: admin_users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`           INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(50) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,            -- bcrypt hash
  `full_name`    VARCHAR(100) NOT NULL,
  `email`        VARCHAR(100) NOT NULL,
  `role`         ENUM('superadmin','admin','viewer') DEFAULT 'admin',
  `last_login`   DATETIME DEFAULT NULL,
  `is_active`    TINYINT(1) DEFAULT 1,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default superadmin  (password: Admin@1234 — CHANGE THIS IMMEDIATELY)
INSERT INTO `admin_users` (`username`, `password`, `full_name`, `email`, `role`)
VALUES (
  'superadmin',
  '$2y$10$KTJ9/OgWcxHeclHqltNtkOxfptGJVNwOF2XKzrWa90Cu.2s.5gpha', -- Admin@1234
  'Super Admin',
  'admin@dhakamodelAgency.com',
  'superadmin'
);

-- ------------------------------------------------------------
-- Table: registrations
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registrations` (
  `id`              INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reg_code`        VARCHAR(20) NOT NULL UNIQUE,   -- e.g. DMA-2025-00001
  `full_name`       VARCHAR(100) NOT NULL,
  `phone`           VARCHAR(20) NOT NULL,
  `email`           VARCHAR(100) DEFAULT NULL,
  `dob`             DATE NOT NULL,
  `age`             TINYINT UNSIGNED NOT NULL,
  `gender`          ENUM('male','female','other') NOT NULL,
  `height_cm`       SMALLINT UNSIGNED NOT NULL,    -- stored in cm
  `weight_kg`       TINYINT UNSIGNED DEFAULT NULL,
  `skin_tone`       ENUM('fair','wheatish','dusky','dark') DEFAULT NULL,
  `district`        VARCHAR(60) NOT NULL,
  `address`         TEXT DEFAULT NULL,
  `experience`      ENUM('none','some','professional') DEFAULT 'none',
  `exp_details`     TEXT DEFAULT NULL,
  `photo_path`      VARCHAR(255) DEFAULT NULL,     -- relative path
  `fb_profile`      VARCHAR(255) DEFAULT NULL,
  `instagram`       VARCHAR(100) DEFAULT NULL,
  `how_heard`       ENUM('facebook','instagram','friend','poster','other') DEFAULT 'facebook',
  `status`          ENUM('pending','reviewed','approved','rejected','waitlist') DEFAULT 'pending',
  `admin_note`      TEXT DEFAULT NULL,
  `reviewed_by`     INT(11) UNSIGNED DEFAULT NULL,
  `reviewed_at`     DATETIME DEFAULT NULL,
  `ip_address`      VARCHAR(45) DEFAULT NULL,
  `user_agent`      TEXT DEFAULT NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status`     (`status`),
  INDEX `idx_phone`      (`phone`),
  INDEX `idx_gender`     (`gender`),
  INDEX `idx_created`    (`created_at`),
  INDEX `idx_district`   (`district`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: status_logs  (audit trail)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `status_logs` (
  `id`            INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reg_id`        INT(11) UNSIGNED NOT NULL,
  `changed_by`    INT(11) UNSIGNED NOT NULL,
  `old_status`    VARCHAR(20) DEFAULT NULL,
  `new_status`    VARCHAR(20) NOT NULL,
  `note`          TEXT DEFAULT NULL,
  `changed_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reg_id`)     REFERENCES `registrations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: campaign_settings  (key-value config store)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_settings` (
  `setting_key`   VARCHAR(60) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `campaign_settings` (`setting_key`, `setting_value`) VALUES
('campaign_name',       'DMA Grooming Campaign 2025'),
('campaign_active',     '1'),
('max_registrations',   '500'),
('fb_pixel_id',         'YOUR_PIXEL_ID_HERE'),
('contact_email',       'info@dhakamodelAgency.com'),
('contact_phone',       '+880-XXXXXXXXXX'),
('registration_note',   'We will contact you within 3 working days.');
