<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../modules/device_sync/sync.php';

require_admin();

$pdo = db();
$error = '';
$success = '';
$info = '';
$editDevice = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT device_id, device_name, ip_address, port FROM device_settings WHERE device_id = ?');
        $stmt->execute([$editId]);
        $editDevice = $stmt->fetch();
        if (!$editDevice) {
            $error = 'Device not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_device') {
            $deviceId = (int) ($_POST['device_id'] ?? 0);
            $name = trim($_POST['device_name'] ?? '');
            $ip = trim($_POST['ip_address'] ?? '');
            $port = (int) ($_POST['port'] ?? 0);

            if ($name === '' || $ip === '' || $port <= 0) {
                $error = 'Device name, IP address, and port are required.';
            } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $error = 'Invalid IP address.';
            } elseif ($port < 1 || $port > 65535) {
                $error = 'Port must be between 1 and 65535.';
            } else {
                try {
                    if ($deviceId > 0) {
                        $stmt = $pdo->prepare('UPDATE device_settings SET device_name = ?, ip_address = ?, port = ? WHERE device_id = ?');
                        $stmt->execute([$name, $ip, $port, $deviceId]);
                        $success = 'Device updated.';
                        $editDevice = null;
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO device_settings (device_name, ip_address, port) VALUES (?, ?, ?)');
                        $stmt->execute([$name, $ip, $port]);
                        $success = 'Device added.';
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $error = 'Device IP address must be unique.';
                    } else {
                        $error = 'Unable to save device.';
                    }
                }
            }
        } elseif ($action === 'import_logs') {
            $defaultDeviceId = (int) ($_POST['default_device_id'] ?? 0);
            $defaultDeviceId = $defaultDeviceId > 0 ? $defaultDeviceId : null;

            $csvText = '';
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $csvText = (string) file_get_contents($_FILES['csv_file']['tmp_name']);
            } else {
                $csvText = trim($_POST['log_data'] ?? '');
            }

            if ($csvText === '') {
                $error = 'Provide a CSV file or paste log data.';
            } else {
                $rows = parse_device_csv($csvText);
                $errors = [];
                $inserted = 0;
                $skipped = 0;
                import_device_logs($pdo, $rows, $defaultDeviceId, $errors, $inserted, $skipped);

                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                }

                if ($inserted > 0 || $skipped > 0) {
                    $info = 'Imported: ' . $inserted . ' | Skipped: ' . $skipped;
                }

                if ($error === '' && $inserted > 0) {
                    $success = 'Logs imported successfully.';
                }
            }
        }
    }
}

$devices = $pdo->query('SELECT device_id, device_name, ip_address, port FROM device_settings ORDER BY device_name ASC')->fetchAll();
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
  <title>Device Sync - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo e(url_for('assets/css/app.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Device Synchronization</div>
          <div class="brand-sub">Configure devices and import logs</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn ghost" href="<?php echo e(url_for('admin/index.php')); ?>">Back to Dashboard</a>
        <a class="btn" href="<?php echo e(url_for('admin/staff.php')); ?>">Manage Staff</a>
      </div>
    </header>

    <?php if ($error !== ''): ?>
      <div class="notice error"><?php echo e($error); ?></div>
    <?php elseif ($success !== ''): ?>
      <div class="notice success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($info !== ''): ?>
      <div class="notice info"><?php echo e($info); ?></div>
    <?php endif; ?>

    <div class="stack">
      <div class="card">
        <h2><?php echo $editDevice ? 'Edit Device' : 'Add Device'; ?></h2>
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
          <input type="hidden" name="action" value="save_device">
          <input type="hidden" name="device_id" value="<?php echo (int) ($editDevice['device_id'] ?? 0); ?>">
          <div class="grid">
            <div class="field">
              <label for="device_name">Device Name</label>
              <input id="device_name" name="device_name" type="text" value="<?php echo e($editDevice['device_name'] ?? ''); ?>" required>
            </div>
            <div class="field">
              <label for="ip_address">IP Address</label>
              <input id="ip_address" name="ip_address" type="text" value="<?php echo e($editDevice['ip_address'] ?? ''); ?>" required>
            </div>
            <div class="field">
              <label for="port">Port</label>
              <input id="port" name="port" type="number" value="<?php echo e((string) ($editDevice['port'] ?? '')); ?>" required>
            </div>
          </div>
          <button type="submit"><?php echo $editDevice ? 'Update Device' : 'Add Device'; ?></button>
        </form>
      </div>

      <div class="card">
        <h2>Registered Devices</h2>
        <?php if (empty($devices)): ?>
          <p>No devices registered.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>IP Address</th>
                <th>Port</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($devices as $device): ?>
                <tr>
                  <td><?php echo e($device['device_name']); ?></td>
                  <td><?php echo e($device['ip_address']); ?></td>
                  <td><?php echo e((string) $device['port']); ?></td>
                  <td><a href="<?php echo e(url_for('admin/device_sync.php')); ?>?edit=<?php echo (int) $device['device_id']; ?>">Edit</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Import Attendance Logs</h2>
        <form method="post" action="" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
          <input type="hidden" name="action" value="import_logs">
          <div class="grid">
            <div class="field">
              <label for="default_device_id">Default Device (optional)</label>
              <select id="default_device_id" name="default_device_id">
                <option value="0">-- None --</option>
                <?php foreach ($devices as $device): ?>
                  <option value="<?php echo (int) $device['device_id']; ?>"><?php echo e($device['device_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="csv_file">Upload CSV (optional)</label>
              <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv">
            </div>
          </div>
          <div class="field">
            <label for="log_data">Or paste CSV content</label>
            <textarea id="log_data" name="log_data" placeholder="device_user_id,scan_date,scan_time,device_id&#10;1001,2026-03-14,08:05,1"></textarea>
          </div>
          <p style="color: var(--muted); margin-top: 0;">Expected columns: device_user_id, scan_date (YYYY-MM-DD or DD/MM/YYYY), scan_time (HH:MM or HH:MM:SS), device_id (optional). Header row is optional.</p>
          <button type="submit">Import Logs</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
