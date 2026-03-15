# Testing Guide

## Manual Smoke Checks
1. Admin initialization flow: `/auth/init_admin.php`
2. Admin login: `/auth/admin_login.php`
3. Staff login (after setting staff password): `/auth/staff_login.php`
4. Device sync: `/admin/device_sync.php` (import sample CSV)
5. Process attendance: `/admin/attendance_process.php`
6. Attendance records: `/admin/attendance.php`
7. Real-time monitor: `/admin/realtime.php`
8. Reports with CSV export: `/admin/reports.php`
9. Staff portal: `/staff/index.php`, `/staff/attendance.php`, `/staff/profile.php`

## Optional CLI Checks
- Syntax check (PowerShell):
  - `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`

## Notes
- Database must be created from `config/schema.sql` and updated with any ALTERs from `PROJECT_MEMORY.md`.
- Logs must be imported before processing attendance.
