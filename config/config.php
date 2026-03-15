<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Lagos');

define('APP_NAME', 'Biometric Attendance System');

define('DB_HOST', 'localhost');
define('DB_NAME', 'biometric_attendance_system');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SESSION_NAME', 'bas_session');
define('SESSION_SECURE', false);
define('SESSION_SAMESITE', 'Lax');
define('SESSION_IDLE_TIMEOUT', 1800);
define('SESSION_ABSOLUTE_TIMEOUT', 28800);

define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_SECONDS', 900);

$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$projectRoot = realpath(__DIR__ . '/..');
$basePath = '';
if ($docRoot && $projectRoot && strncmp($projectRoot, $docRoot, strlen($docRoot)) === 0) {
    $relative = substr($projectRoot, strlen($docRoot));
    $relative = str_replace('\\', '/', $relative);
    $basePath = $relative !== '' ? $relative : '';
}
define('BASE_PATH', $basePath);
