<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = db();
$error = '';
$success = '';
$editDept = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT dept_id, dept_name FROM departments WHERE dept_id = ?');
        $stmt->execute([$editId]);
        $editDept = $stmt->fetch();
        if (!$editDept) {
            $error = 'Department not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $name = trim($_POST['dept_name'] ?? '');
        $deptId = (int) ($_POST['dept_id'] ?? 0);

        if ($name === '') {
            $error = 'Department name is required.';
        } else {
            try {
                if ($deptId > 0) {
                    $stmt = $pdo->prepare('UPDATE departments SET dept_name = ? WHERE dept_id = ?');
                    $stmt->execute([$name, $deptId]);
                    $success = 'Department updated.';
                    $editDept = null;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO departments (dept_name) VALUES (?)');
                    $stmt->execute([$name]);
                    $success = 'Department added.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Department name must be unique.';
                } else {
                    $error = 'Unable to save department.';
                }
            }
        }
    }
}

$departments = $pdo->query('SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC')->fetchAll();
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
  <title>Departments - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Departments</div>
          <div class="brand-sub">Manage organizational units</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/staff.php')); ?>">Manage Staff</a>
      </div>
    </header>

    <div class="stack">
      <div class="card">
        <h2><?php echo $editDept ? 'Edit Department' : 'Add Department'; ?></h2>
        <?php if ($error !== ''): ?>
          <div class="notice error"><?php echo e($error); ?></div>
        <?php elseif ($success !== ''): ?>
          <div class="notice success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
          <input type="hidden" name="dept_id" value="<?php echo (int) ($editDept['dept_id'] ?? 0); ?>">
          <div class="field">
            <label for="dept_name">Department Name</label>
            <input id="dept_name" name="dept_name" type="text" value="<?php echo e($editDept['dept_name'] ?? ''); ?>" required>
          </div>
          <button type="submit"><?php echo $editDept ? 'Update' : 'Add'; ?></button>
        </form>
      </div>

      <div class="card">
        <h2>Department List</h2>
        <?php if (empty($departments)): ?>
          <p>No departments found.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($departments as $dept): ?>
                <tr>
                  <td><?php echo (int) $dept['dept_id']; ?></td>
                  <td><?php echo e($dept['dept_name']); ?></td>
                  <td><a href="<?php echo e(url_for('admin/departments.php')); ?>?edit=<?php echo (int) $dept['dept_id']; ?>">Edit</a></td>
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
