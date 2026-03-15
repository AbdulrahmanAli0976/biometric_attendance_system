<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

ensure_session();

if (is_staff_logged_in()) {
    redirect_to('staff/index.php');
}

$error = '';
$email = '';
$rateKey = rate_limit_key('staff_login');
$rateLimited = is_rate_limited($rateKey);
if ($rateLimited) {
    $minutes = (int) ceil(rate_limit_remaining($rateKey) / 60);
    $error = 'Too many attempts. Try again in ' . $minutes . ' minute(s).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimited) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } else {
            $stmt = db()->prepare('SELECT staff_id, name, password FROM staff WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $staff = $stmt->fetch();

            if (!$staff || empty($staff['password']) || !password_verify($password, $staff['password'])) {
                $error = 'Invalid email or password.';
            } else {
                clear_rate_limit($rateKey);
                login_staff((int) $staff['staff_id'], $staff['name']);
                redirect_to('staff/index.php');
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
  <title>Staff Login - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(url_for('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <div class="auth-shell">
    <div class="card">
      <div class="brand" style="margin-bottom: 1rem;">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title">Staff Login</div>
          <div class="brand-sub"><?php echo APP_NAME; ?></div>
        </div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>
        <button type="submit">Sign In</button>
      </form>
      <p style="margin-top: 1rem; color: var(--muted);">If you do not have a password yet, contact your administrator.</p>
    </div>
  </div>
</body>
</html>
