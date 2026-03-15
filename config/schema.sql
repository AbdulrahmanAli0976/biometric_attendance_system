-- Biometric Attendance System Schema
-- Phase 2: Database creation

CREATE DATABASE IF NOT EXISTS biometric_attendance_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE biometric_attendance_system;

CREATE TABLE IF NOT EXISTS admins (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  dept_id INT AUTO_INCREMENT PRIMARY KEY,
  dept_name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff (
  staff_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  department_id INT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  password VARCHAR(255) NULL,
  device_user_id VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_email (email),
  CONSTRAINT fk_staff_department
    FOREIGN KEY (department_id) REFERENCES departments(dept_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_settings (
  device_id INT AUTO_INCREMENT PRIMARY KEY,
  device_name VARCHAR(120) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  port INT NOT NULL,
  UNIQUE KEY uq_device_ip (ip_address)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_logs (
  log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  device_user_id VARCHAR(50) NOT NULL,
  scan_date DATE NOT NULL,
  scan_time TIME NOT NULL,
  device_id INT NULL,
  UNIQUE KEY uq_logs_unique (device_user_id, scan_date, scan_time, device_id),
  KEY idx_logs_user_date_time (device_user_id, scan_date, scan_time),
  CONSTRAINT fk_logs_device
    FOREIGN KEY (device_id) REFERENCES device_settings(device_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  attendance_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  date DATE NOT NULL,
  check_in TIME NULL,
  check_out TIME NULL,
  working_hours DECIMAL(5,2) NULL,
  status ENUM('Present','Late','Absent') NOT NULL DEFAULT 'Present',
  UNIQUE KEY uq_attendance_staff_date (staff_id, date),
  KEY idx_attendance_date (date),
  CONSTRAINT fk_attendance_staff
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;
