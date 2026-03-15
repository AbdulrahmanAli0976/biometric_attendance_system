<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_staff();
$staff = current_staff();
$staffId = (int) ($staff['id'] ?? 0);

$pdo = db();

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT date, check_in, check_out, working_hours, status '
    . 'FROM attendance '
    . 'WHERE staff_id = ? AND date BETWEEN ? AND ? '
    . 'ORDER BY date DESC'
);
$stmt->execute([$staffId, $startDate, $endDate]);
$records = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT SUM(working_hours) FROM attendance WHERE staff_id = ? AND date BETWEEN ? AND ?'
);
$stmt->execute([$staffId, $startDate, $endDate]);
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
  <title>Attendance History - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Attendance History</div>
          <div class="brand-sub">Review your work hours</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('staff/index.php')); ?>">Back to Portal</a>
        <a class="btn" href="<?php echo e(url_for('staff/profile.php')); ?>">My Profile</a>
        <a class="btn ghost" href="<?php echo e(url_for('auth/logout.php')); ?>">Logout</a>
      </div>
    </header>

    <div class="stack">
      <div class="card">
        <h2>Filters</h2>
        <form method="get" action="">
          <div class="grid">
            <div class="field">
              <label for="start_date">Start Date</label>
              <input id="start_date" name="start_date" type="date" value="<?php echo e($startDate); ?>" required>
            </div>
            <div class="field">
              <label for="end_date">End Date</label>
              <input id="end_date" name="end_date" type="date" value="<?php echo e($endDate); ?>" required>
            </div>
          </div>
          <div class="actions" style="margin-top: 1rem;">
            <button type="submit">Apply</button>
            <span style="color: var(--muted);">Total hours in range: <?php echo number_format($totalHours, 2); ?></span>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>Records</h2>
        <?php if (empty($records)): ?>
          <p>No attendance records found for the selected range.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Working Hours</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $record): ?>
                <tr>
                  <td><?php echo e($record['date']); ?></td>
                  <td><?php echo e($record['check_in'] ?? ''); ?></td>
                  <td><?php echo e($record['check_out'] ?? ''); ?></td>
                  <td><?php echo e($record['working_hours'] !== null ? (string) $record['working_hours'] : ''); ?></td>
                  <td>
                    <?php if ($record['status'] === 'Late'): ?>
                      <span class="chip late">Late</span>
                    <?php else: ?>
                      <span class="chip present">Present</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
