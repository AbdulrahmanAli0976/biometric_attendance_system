<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

ensure_session();

$adminCount = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($adminCount > 0) {
    redirect_to('auth/admin_login.php');
}

$error = '';
$username = '';
$rateKey = rate_limit_key('init_admin');
$rateLimited = is_rate_limited($rateKey);
if ($rateLimited) {
    $minutes = (int) ceil(rate_limit_remaining($rateKey) / 60);
    $error = 'Too many attempts. Try again in ' . $minutes . ' minute(s).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimited) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($username === '' || $password === '' || $confirm === '') {
            $error = 'All fields are required.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);
            $adminId = (int) db()->lastInsertId();
            clear_rate_limit($rateKey);
            login_admin($adminId, $username);
            redirect_to('admin/index.php');
        }
    }

    if ($error !== '') {
        record_rate_limit_hit($rateKey);
    }
}

$token = csrf_token();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Initialize Admin - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(url_for('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <div class="auth-shell">
    <div class="card">
      <div class="brand" style="margin-bottom: 1rem;">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Initialize Admin</div>
          <div class="brand-sub"><?php echo APP_NAME; ?></div>
        </div>
      </div>

      <p>Create the first administrator account.</p>
      <?php if ($error !== ''): ?>
        <div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <input id="confirm_password" name="confirm_password" type="password" required>
        </div>
        <button type="submit">Create Admin</button>
      </form>
    </div>
  </div>
</body>
</html>
