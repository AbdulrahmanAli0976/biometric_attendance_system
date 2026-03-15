<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = db();
$error = '';
$success = '';
$editStaff = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT staff_id, name, department_id, phone, email, device_user_id FROM staff WHERE staff_id = ?');
        $stmt->execute([$editId]);
        $editStaff = $stmt->fetch();
        if (!$editStaff) {
            $error = 'Staff member not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $deviceUserId = trim($_POST['device_user_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $deviceUserId === '') {
            $error = 'Name and Device User ID are required.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif ($password !== '' && strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } else {
            $departmentId = $departmentId > 0 ? $departmentId : null;
            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

            try {
                if ($staffId > 0) {
                    if ($passwordHash !== null) {
                        $stmt = $pdo->prepare(
                            'UPDATE staff SET name = ?, department_id = ?, phone = ?, email = ?, device_user_id = ?, password = ? WHERE staff_id = ?'
                        );
                        $stmt->execute([$name, $departmentId, $phone ?: null, $email ?: null, $deviceUserId, $passwordHash, $staffId]);
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE staff SET name = ?, department_id = ?, phone = ?, email = ?, device_user_id = ? WHERE staff_id = ?'
                        );
                        $stmt->execute([$name, $departmentId, $phone ?: null, $email ?: null, $deviceUserId, $staffId]);
                    }
                    $success = 'Staff record updated.';
                    $editStaff = null;
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO staff (name, department_id, phone, email, device_user_id, password) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$name, $departmentId, $phone ?: null, $email ?: null, $deviceUserId, $passwordHash]);
                    $success = 'Staff member added.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Email or Device User ID already exists.';
                } else {
                    $error = 'Unable to save staff record.';
                }
            }
        }
    }
}

$departments = $pdo->query('SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC')->fetchAll();
$staffList = $pdo->query(
    'SELECT s.staff_id, s.name, s.phone, s.email, s.device_user_id, d.dept_name '
    . 'FROM staff s LEFT JOIN departments d ON s.department_id = d.dept_id '
    . 'ORDER BY s.name ASC'
)->fetchAll();

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
  <title>Staff Management - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Staff Management</div>
          <div class="brand-sub">Register and update staff records</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/departments.php')); ?>">Manage Departments</a>
      </div>
    </header>

    <div class="stack">
      <div class="card">
        <h2><?php echo $editStaff ? 'Edit Staff' : 'Add Staff'; ?></h2>
        <?php if ($error !== ''): ?>
          <div class="notice error"><?php echo e($error); ?></div>
        <?php elseif ($success !== ''): ?>
          <div class="notice success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
          <input type="hidden" name="staff_id" value="<?php echo (int) ($editStaff['staff_id'] ?? 0); ?>">
          <div class="grid">
            <div class="field">
              <label for="name">Full Name</label>
              <input id="name" name="name" type="text" value="<?php echo e($editStaff['name'] ?? ''); ?>" required>
            </div>
            <div class="field">
              <label for="department_id">Department</label>
              <select id="department_id" name="department_id">
                <option value="0">-- None --</option>
                <?php foreach ($departments as $dept): ?>
                  <option value="<?php echo (int) $dept['dept_id']; ?>" <?php echo ((int) ($editStaff['department_id'] ?? 0) === (int) $dept['dept_id']) ? 'selected' : ''; ?>>
                    <?php echo e($dept['dept_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="phone">Phone</label>
              <input id="phone" name="phone" type="text" value="<?php echo e($editStaff['phone'] ?? ''); ?>">
            </div>
            <div class="field">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?php echo e($editStaff['email'] ?? ''); ?>">
            </div>
            <div class="field">
              <label for="device_user_id">Device User ID</label>
              <input id="device_user_id" name="device_user_id" type="text" value="<?php echo e($editStaff['device_user_id'] ?? ''); ?>" required>
            </div>
            <div class="field">
              <label for="password">Password</label>
              <input id="password" name="password" type="password">
              <div style="margin-top:0.4rem; color: var(--muted); font-size: 0.85rem;">Leave blank to keep current password.</div>
            </div>
          </div>
          <button type="submit"><?php echo $editStaff ? 'Update Staff' : 'Add Staff'; ?></button>
        </form>
      </div>

      <div class="card">
        <h2>Staff List</h2>
        <?php if (empty($staffList)): ?>
          <p>No staff found.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Device User ID</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($staffList as $staffItem): ?>
                <tr>
                  <td><?php echo e($staffItem['name']); ?></td>
                  <td><?php echo e($staffItem['dept_name'] ?? ''); ?></td>
                  <td><?php echo e($staffItem['email'] ?? ''); ?></td>
                  <td><?php echo e($staffItem['phone'] ?? ''); ?></td>
                  <td><?php echo e($staffItem['device_user_id']); ?></td>
                  <td><a href="<?php echo e(url_for('admin/staff.php')); ?>?edit=<?php echo (int) $staffItem['staff_id']; ?>">Edit</a></td>
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
