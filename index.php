<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

if (is_admin_logged_in()) {
    redirect_to('admin/index.php');
}

if (is_staff_logged_in()) {
    redirect_to('staff/index.php');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(url_for('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">BA</div>
        <div>
          <div class="brand-title"><?php echo APP_NAME; ?></div>
          <div class="brand-sub">Biometric attendance management</div>
        </div>
      </div>
    </header>

    <div class="card">
      <div class="hero">Choose your portal</div>
      <p>Access the admin console or staff portal to continue.</p>
      <div class="actions" style="margin-top: 1rem;">
        <a class="btn" href="<?php echo htmlspecialchars(url_for('auth/admin_login.php'), ENT_QUOTES, 'UTF-8'); ?>">Admin Login</a>
        <a class="btn secondary" href="<?php echo htmlspecialchars(url_for('auth/staff_login.php'), ENT_QUOTES, 'UTF-8'); ?>">Staff Login</a>
      </div>
    </div>
  </div>
</body>
</html>
