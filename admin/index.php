<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();
$admin = current_admin();

$today = date('Y-m-d');
$pdo = db();

$totalStaff = (int) $pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE date = ? AND check_in IS NOT NULL');
$stmt->execute([$today]);
$presentToday = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE date = ?');
$stmt->execute([$today]);
$attendanceToday = (int) $stmt->fetchColumn();

$absentToday = max(0, $totalStaff - $attendanceToday);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE date = ? AND status = "Late"');
$stmt->execute([$today]);
$lateToday = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT l.scan_date, l.scan_time, l.device_user_id, s.name '
    . 'FROM attendance_logs l '
    . 'LEFT JOIN staff s ON s.device_user_id = l.device_user_id '
    . 'WHERE l.scan_date = ? '
    . 'ORDER BY l.scan_time DESC '
    . 'LIMIT 12'
);
$stmt->execute([$today]);
$recentLogs = $stmt->fetchAll();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Admin Dashboard</div>
          <div class="brand-sub"><?php echo e($today); ?></div>
        </div>
      </div>
      <div class="actions">
        <span class="brand-sub">Signed in as <?php echo e($admin['username'] ?? 'Admin'); ?></span>
        <a class="btn ghost" href="<?php echo e(url_for('auth/logout.php')); ?>">Logout</a>
      </div>
    </header>

    <section class="grid">
      <div class="stat">
        <div class="label">Total Staff</div>
        <div class="value"><?php echo $totalStaff; ?></div>
      </div>
      <div class="stat alt">
        <div class="label">Present Today</div>
        <div class="value"><?php echo $presentToday; ?></div>
      </div>
      <div class="stat">
        <div class="label">Late Today</div>
        <div class="value"><?php echo $lateToday; ?></div>
      </div>
      <div class="stat alt">
        <div class="label">Absent Today</div>
        <div class="value"><?php echo $absentToday; ?></div>
      </div>
    </section>

    <section class="grid grid-wide" style="margin-top: 1.5rem;">
      <div class="card">
        <h2>Live Attendance Feed</h2>
        <?php if (empty($recentLogs)): ?>
          <p>No check-ins recorded today.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Staff</th>
                <th>Device User ID</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentLogs as $log): ?>
                <tr>
                  <td><?php echo e($log['name'] ?? 'Unknown Staff'); ?></td>
                  <td><?php echo e($log['device_user_id']); ?></td>
                  <td><?php echo e(substr($log['scan_time'], 0, 5)); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Quick Actions</h2>
        <div class="stack">
          <a class="btn" href="<?php echo e(url_for('admin/device_sync.php')); ?>">Sync Device Logs</a>
          <a class="btn secondary" href="<?php echo e(url_for('admin/attendance_process.php')); ?>">Process Attendance</a>
          <a class="btn ghost" href="<?php echo e(url_for('admin/attendance.php')); ?>">Attendance Records</a>
          <a class="btn ghost" href="<?php echo e(url_for('admin/realtime.php')); ?>">Real-Time Monitor</a>
          <a class="btn ghost" href="<?php echo e(url_for('admin/staff.php')); ?>">Manage Staff</a>
          <a class="btn ghost" href="<?php echo e(url_for('admin/departments.php')); ?>">Departments</a>
          <a class="btn ghost" href="<?php echo e(url_for('admin/reports.php')); ?>">View Reports</a>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
