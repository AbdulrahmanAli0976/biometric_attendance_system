<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

ensure_session();

$adminCount = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($adminCount === 0) {
    redirect_to('auth/init_admin.php');
}

if (is_admin_logged_in()) {
    redirect_to('admin/index.php');
}

$error = '';
$username = '';
$rateKey = rate_limit_key('admin_login');
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

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $stmt = db()->prepare('SELECT admin_id, username, password FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password'])) {
                $error = 'Invalid username or password.';
            } else {
                clear_rate_limit($rateKey);
                login_admin((int) $admin['admin_id'], $admin['username']);
                redirect_to('admin/index.php');
            }
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
  <title>Admin Login - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(url_for('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <div class="auth-shell">
    <div class="card">
      <div class="brand" style="margin-bottom: 1rem;">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Admin Login</div>
          <div class="brand-sub"><?php echo APP_NAME; ?></div>
        </div>
      </div>

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
        <button type="submit">Sign In</button>
      </form>
    </div>
  </div>
</body>
</html>
