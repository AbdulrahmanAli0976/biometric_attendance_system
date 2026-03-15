<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_staff();
$staff = current_staff();
$staffId = (int) ($staff['id'] ?? 0);

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT s.name, s.email, s.phone, s.device_user_id, d.dept_name '
    . 'FROM staff s '
    . 'LEFT JOIN departments d ON s.department_id = d.dept_id '
    . 'WHERE s.staff_id = ?'
);
$stmt->execute([$staffId]);
$profile = $stmt->fetch();

$today = date('Y-m-d');

$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE staff_id = ? AND date = ?');
$stmt->execute([$staffId, $today]);
$hasToday = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT SUM(working_hours) FROM attendance WHERE staff_id = ?');
$stmt->execute([$staffId]);
$totalHours = $stmt->fetchColumn();
$totalHours = $totalHours !== null ? (float) $totalHours : 0.0;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Portal - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Staff Portal</div>
          <div class="brand-sub">Welcome back, <?php echo e($profile['name'] ?? 'Staff'); ?></div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('staff/attendance.php')); ?>">Attendance History</a>
        <a class="btn" href="<?php echo e(url_for('staff/profile.php')); ?>">My Profile</a>
        <a class="btn ghost" href="<?php echo e(url_for('auth/logout.php')); ?>">Logout</a>
      </div>
    </header>

    <div class="grid">
      <div class="stat">
        <div class="label">Logged Today</div>
        <div class="value"><?php echo $hasToday > 0 ? 'Yes' : 'No'; ?></div>
      </div>
      <div class="stat alt">
        <div class="label">Total Hours</div>
        <div class="value"><?php echo number_format($totalHours, 2); ?></div>
      </div>
      <div class="stat">
        <div class="label">Device User ID</div>
        <div class="value"><?php echo e($profile['device_user_id'] ?? ''); ?></div>
      </div>
    </div>

    <div class="card" style="margin-top: 1.5rem;">
      <h2>Profile Snapshot</h2>
      <p><strong>Department:</strong> <?php echo e($profile['dept_name'] ?? ''); ?></p>
      <p><strong>Email:</strong> <?php echo e($profile['email'] ?? ''); ?></p>
      <p><strong>Phone:</strong> <?php echo e($profile['phone'] ?? ''); ?></p>
    </div>
  </div>
</body>
</html>
