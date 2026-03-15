<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../modules/report_generator/report.php';

require_admin();

$pdo = db();
$filters = build_report_filters($_GET);

$records = fetch_attendance_report($pdo, $filters);
$summary = fetch_report_summary($pdo, $filters);

$absences = [];
if ($filters['start_date'] === $filters['end_date']) {
    $absences = fetch_absences_for_date($pdo, $filters['start_date'], $filters['department_id'], $filters['staff_id']);
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    output_report_csv($records);
    exit;
}

$departments = $pdo->query('SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC')->fetchAll();
$staffList = $pdo->query('SELECT staff_id, name FROM staff ORDER BY name ASC')->fetchAll();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function selected($current, $value): string
{
    return ((string) $current === (string) $value) ? 'selected' : '';
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Attendance Reports</div>
          <div class="brand-sub">Generate summaries and exports</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/attendance.php')); ?>">Attendance Records</a>
        <a class="btn ghost" href="<?php echo e(url_for('admin/attendance_process.php')); ?>">Process Attendance</a>
      </div>
    </header>

    <div class="stack">
      <div class="card">
        <h2>Filters</h2>
        <form method="get" action="">
          <div class="grid">
            <div class="field">
              <label for="start_date">Start Date</label>
              <input id="start_date" name="start_date" type="date" value="<?php echo e($filters['start_date']); ?>" required>
            </div>
            <div class="field">
              <label for="end_date">End Date</label>
              <input id="end_date" name="end_date" type="date" value="<?php echo e($filters['end_date']); ?>" required>
            </div>
            <div class="field">
              <label for="department_id">Department</label>
              <select id="department_id" name="department_id">
                <option value="0">-- All --</option>
                <?php foreach ($departments as $dept): ?>
                  <option value="<?php echo (int) $dept['dept_id']; ?>" <?php echo selected($filters['department_id'], $dept['dept_id']); ?>>
                    <?php echo e($dept['dept_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="staff_id">Staff</label>
              <select id="staff_id" name="staff_id">
                <option value="0">-- All --</option>
                <?php foreach ($staffList as $staffItem): ?>
                  <option value="<?php echo (int) $staffItem['staff_id']; ?>" <?php echo selected($filters['staff_id'], $staffItem['staff_id']); ?>>
                    <?php echo e($staffItem['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status">
                <option value="" <?php echo selected($filters['status'], ''); ?>>-- All --</option>
                <option value="Present" <?php echo selected($filters['status'], 'Present'); ?>>Present</option>
                <option value="Late" <?php echo selected($filters['status'], 'Late'); ?>>Late</option>
              </select>
            </div>
          </div>
          <div class="actions" style="margin-top: 1rem;">
            <button type="submit">Apply</button>
            <a class="btn ghost" href="?<?php echo http_build_query(array_merge($filters, ['export' => 'csv'])); ?>">Export CSV</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>Summary</h2>
        <div class="grid">
          <div class="stat">
            <div class="label">Total Records</div>
            <div class="value"><?php echo (int) $summary['total_records']; ?></div>
          </div>
          <div class="stat alt">
            <div class="label">Total Hours</div>
            <div class="value"><?php echo number_format($summary['total_hours'], 2); ?></div>
          </div>
          <div class="stat">
            <div class="label">Late Count</div>
            <div class="value"><?php echo (int) $summary['late_count']; ?></div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>Attendance Records</h2>
        <?php if (empty($records)): ?>
          <p>No records found for the selected filters.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Department</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Hours</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $record): ?>
                <tr>
                  <td><?php echo e($record['date']); ?></td>
                  <td><?php echo e($record['name']); ?></td>
                  <td><?php echo e($record['dept_name'] ?? ''); ?></td>
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

      <?php if ($filters['start_date'] === $filters['end_date']): ?>
        <div class="card">
          <h2>Absent Staff (<?php echo e($filters['start_date']); ?>)</h2>
          <?php if (empty($absences)): ?>
            <p>No absences detected for the selected date.</p>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Department</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($absences as $person): ?>
                  <tr>
                    <td><?php echo e($person['name']); ?></td>
                    <td><?php echo e($person['dept_name'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
