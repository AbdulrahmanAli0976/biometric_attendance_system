# Biometric Attendance System

A web-based staff biometric attendance system built with PHP, MySQL, and XAMPP. It imports biometric device logs, processes attendance, and provides admin and staff portals.

## Requirements
- XAMPP (Apache + MySQL)
- PHP 8+

## Setup (Localhost)
1. Copy the project to:
   - `C:\xampp\htdocs\biometric_attendance_system`
2. Start **Apache** and **MySQL** in XAMPP.
3. Create the database:
   - Open `http://localhost/phpmyadmin`
   - Create `biometric_attendance_system`
   - Import `config/schema.sql`
4. If you already created the DB before schema updates, apply:

```sql
ALTER TABLE staff ADD COLUMN password VARCHAR(255) NULL AFTER email;
ALTER TABLE staff ADD UNIQUE KEY uq_staff_email (email);

ALTER TABLE attendance_logs
  ADD UNIQUE KEY uq_logs_unique (device_user_id, scan_date, scan_time, device_id);
```

## First Admin Setup
- Visit: `http://localhost/biometric_attendance_system/auth/init_admin.php`
- Create the first admin account.

## Core Flow
1. **Register staff** (match device ID):
   - `http://localhost/biometric_attendance_system/admin/staff.php`
2. **Import logs** (CSV from device):
   - `http://localhost/biometric_attendance_system/admin/device_sync.php`
3. **Process attendance**:
   - `http://localhost/biometric_attendance_system/admin/attendance_process.php`
4. **View records / reports**:
   - `http://localhost/biometric_attendance_system/admin/attendance.php`
   - `http://localhost/biometric_attendance_system/admin/reports.php`

## Staff Portal
- Login: `http://localhost/biometric_attendance_system/auth/staff_login.php`
- Portal: `http://localhost/biometric_attendance_system/staff/index.php`

## Notes
- Fingerprints are captured on the biometric device, not in the web app.
- The web app imports **logs** (user ID + date + time).

## Testing
See `docs/TESTING.md`.
