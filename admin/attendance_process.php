<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../modules/attendance_processor/processor.php';

require_admin();

$pdo = db();
$error = '';
$success = '';
$summary = [];

$startDate = $_POST['start_date'] ?? date('Y-m-d');
$endDate = $_POST['end_date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $errors = [];
        $summary = [];
        process_attendance($pdo, $startDate, $endDate, $summary, $errors);

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        } else {
            $success = 'Attendance processing completed.';
        }
    }
}

$token = csrf_token();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Process Attendance - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Process Attendance</div>
          <div class="brand-sub">Run the attendance engine</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/attendance.php')); ?>">Attendance Records</a>
      </div>
    </header>

    <div class="card">
      <h2>Run Processing</h2>
      <?php if ($error !== ''): ?>
        <div class="notice error"><?php echo e($error); ?></div>
      <?php elseif ($success !== ''): ?>
        <div class="notice success"><?php echo e($success); ?></div>
      <?php endif; ?>
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
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
        <button type="submit">Process</button>
      </form>
      <?php if (!empty($summary)): ?>
        <div class="notice info" style="margin-top: 1rem;">
          Groups processed: <?php echo (int) ($summary['groups'] ?? 0); ?> | Inserted/Updated: <?php echo (int) ($summary['inserted_or_updated'] ?? 0); ?> | Skipped: <?php echo (int) ($summary['skipped'] ?? 0); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
