<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = db();
$today = date('Y-m-d');

$totalStaff = (int) $pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT s.staff_id) '
    . 'FROM attendance_logs l '
    . 'JOIN staff s ON s.device_user_id = l.device_user_id '
    . 'WHERE l.scan_date = ?'
);
$stmt->execute([$today]);
$presentToday = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM ('
    . 'SELECT l.device_user_id, MIN(l.scan_time) AS first_scan '
    . 'FROM attendance_logs l '
    . 'WHERE l.scan_date = ? '
    . 'GROUP BY l.device_user_id'
    . ') t WHERE t.first_scan > "08:30:00"'
);
$stmt->execute([$today]);
$lateToday = (int) $stmt->fetchColumn();

$absentToday = max(0, $totalStaff - $presentToday);

$stmt = $pdo->prepare(
    'SELECT l.scan_date, l.scan_time, l.device_user_id, s.name '
    . 'FROM attendance_logs l '
    . 'LEFT JOIN staff s ON s.device_user_id = l.device_user_id '
    . 'WHERE l.scan_date = ? '
    . 'ORDER BY l.scan_time DESC '
    . 'LIMIT 15'
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
  <meta http-equiv="refresh" content="60">
  <title>Real-Time Monitor - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Real-Time Monitor</div>
          <div class="brand-sub">Live updates every 60 seconds</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/attendance.php')); ?>">Attendance Records</a>
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

    <div class="card" style="margin-top: 1.5rem;">
      <h2>Live Attendance Feed (Today)</h2>
      <?php if (empty($recentLogs)): ?>
        <p>No recent scans yet.</p>
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
  </div>
</body>
</html>
