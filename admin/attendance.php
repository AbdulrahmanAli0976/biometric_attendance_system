<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = db();
$date = $_GET['date'] ?? date('Y-m-d');
$staffId = (int) ($_GET['staff_id'] ?? 0);

$params = [$date];
$where = 'WHERE a.date = ?';
if ($staffId > 0) {
    $where .= ' AND a.staff_id = ?';
    $params[] = $staffId;
}

$stmt = $pdo->prepare(
    'SELECT a.attendance_id, a.staff_id, a.date, a.check_in, a.check_out, a.working_hours, a.status, '
    . 's.name, s.email, s.phone, d.dept_name '
    . 'FROM attendance a '
    . 'JOIN staff s ON a.staff_id = s.staff_id '
    . 'LEFT JOIN departments d ON s.department_id = d.dept_id '
    . $where . ' '
    . 'ORDER BY s.name ASC'
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$staffList = $pdo->query('SELECT staff_id, name FROM staff ORDER BY name ASC')->fetchAll();

$stmt = $pdo->prepare(
    'SELECT s.staff_id, s.name, d.dept_name '
    . 'FROM staff s '
    . 'LEFT JOIN departments d ON s.department_id = d.dept_id '
    . 'LEFT JOIN attendance a ON a.staff_id = s.staff_id AND a.date = ? '
    . 'WHERE a.attendance_id IS NULL '
    . 'ORDER BY s.name ASC'
);
$stmt->execute([$date]);
$absent = $stmt->fetchAll();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance Records - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Attendance Records</div>
          <div class="brand-sub">Review processed attendance</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/attendance_process.php')); ?>">Process Attendance</a>
        <a class="btn ghost" href="<?php echo e(url_for('admin/realtime.php')); ?>">Real-Time Monitor</a>
      </div>
    </header>

    <div class="stack">
      <div class="card">
        <h2>Filters</h2>
        <form method="get" action="">
          <div class="grid">
            <div class="field">
              <label for="date">Date</label>
              <input id="date" name="date" type="date" value="<?php echo e($date); ?>" required>
            </div>
            <div class="field">
              <label for="staff_id">Staff</label>
              <select id="staff_id" name="staff_id">
                <option value="0">-- All --</option>
                <?php foreach ($staffList as $staffItem): ?>
                  <option value="<?php echo (int) $staffItem['staff_id']; ?>" <?php echo ($staffId === (int) $staffItem['staff_id']) ? 'selected' : ''; ?>>
                    <?php echo e($staffItem['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit">Apply</button>
        </form>
      </div>

      <div class="card">
        <h2>Records</h2>
        <?php if (empty($records)): ?>
          <p>No attendance records found for the selected date.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Working Hours</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $record): ?>
                <tr>
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

      <div class="card">
        <h2>Absent Staff</h2>
        <?php if (empty($absent)): ?>
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
              <?php foreach ($absent as $person): ?>
                <tr>
                  <td><?php echo e($person['name']); ?></td>
                  <td><?php echo e($person['dept_name'] ?? ''); ?></td>
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
