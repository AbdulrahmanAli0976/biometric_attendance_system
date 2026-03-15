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

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">My Profile</div>
          <div class="brand-sub">Personal information</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('staff/index.php')); ?>">Back to Portal</a>
        <a class="btn" href="<?php echo e(url_for('staff/attendance.php')); ?>">Attendance History</a>
        <a class="btn ghost" href="<?php echo e(url_for('auth/logout.php')); ?>">Logout</a>
      </div>
    </header>

    <div class="card">
      <table class="table">
        <tbody>
          <tr>
            <th>Name</th>
            <td><?php echo e($profile['name'] ?? ''); ?></td>
          </tr>
          <tr>
            <th>Department</th>
            <td><?php echo e($profile['dept_name'] ?? ''); ?></td>
          </tr>
          <tr>
            <th>Email</th>
            <td><?php echo e($profile['email'] ?? ''); ?></td>
          </tr>
          <tr>
            <th>Phone</th>
            <td><?php echo e($profile['phone'] ?? ''); ?></td>
          </tr>
          <tr>
            <th>Device User ID</th>
            <td><?php echo e($profile['device_user_id'] ?? ''); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
