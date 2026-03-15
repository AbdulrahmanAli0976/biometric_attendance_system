<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function url_for(string $path = ''): string
{
    $base = rtrim(BASE_PATH, '/');
    $path = ltrim($path, '/');
    if ($base === '') {
        return '/' . $path;
    }
    return $path === '' ? $base : $base . '/' . $path;
}

function redirect_to(string $path = ''): void
{
    header('Location: ' . url_for($path));
    exit;
}

function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $csp = "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
    header('Content-Security-Policy: ' . $csp);
}

function ensure_session(): void
{
    apply_security_headers();

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        $secure = SESSION_SECURE;
        if (!$secure && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $secure = true;
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => SESSION_SAMESITE,
        ]);
        session_start();
    }

    enforce_session_timeouts();
}

function enforce_session_timeouts(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $now = time();

    if (!empty($_SESSION['created_at']) && $now - $_SESSION['created_at'] > SESSION_ABSOLUTE_TIMEOUT) {
        clear_session();
        return;
    }

    if (!empty($_SESSION['last_activity']) && $now - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        clear_session();
        return;
    }

    if (empty($_SESSION['created_at'])) {
        $_SESSION['created_at'] = $now;
    }
    $_SESSION['last_activity'] = $now;
}

function clear_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    ensure_session();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function is_admin_logged_in(): bool
{
    ensure_session();
    return !empty($_SESSION['admin']);
}

function is_staff_logged_in(): bool
{
    ensure_session();
    return !empty($_SESSION['staff']);
}

function current_admin(): ?array
{
    ensure_session();
    return $_SESSION['admin'] ?? null;
}

function current_staff(): ?array
{
    ensure_session();
    return $_SESSION['staff'] ?? null;
}

function login_admin(int $adminId, string $username): void
{
    ensure_session();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['admin'] = [
        'id' => $adminId,
        'username' => $username,
        'login_time' => time(),
    ];
}

function login_staff(int $staffId, string $name): void
{
    ensure_session();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['staff'] = [
        'id' => $staffId,
        'name' => $name,
        'login_time' => time(),
    ];
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect_to('auth/admin_login.php');
    }
}

function require_staff(): void
{
    if (!is_staff_logged_in()) {
        redirect_to('auth/staff_login.php');
    }
}

function logout_user(): void
{
    ensure_session();
    clear_session();
}

function current_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function rate_limit_key(string $scope, ?string $identifier = null): string
{
    $id = $identifier ?? current_ip();
    return $scope . ':' . $id;
}

function rate_limit_state(string $key): array
{
    ensure_session();
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = [
            'count' => 0,
            'first' => time(),
        ];
    }

    $state = $_SESSION['rate_limits'][$key];
    if ((time() - $state['first']) > LOGIN_WINDOW_SECONDS) {
        $_SESSION['rate_limits'][$key] = [
            'count' => 0,
            'first' => time(),
        ];
    }

    return $_SESSION['rate_limits'][$key];
}

function rate_limit_remaining(string $key): int
{
    $state = rate_limit_state($key);
    $elapsed = time() - $state['first'];
    $remaining = LOGIN_WINDOW_SECONDS - $elapsed;
    return $remaining > 0 ? $remaining : 0;
}

function is_rate_limited(string $key): bool
{
    $state = rate_limit_state($key);
    return $state['count'] >= LOGIN_MAX_ATTEMPTS;
}

function record_rate_limit_hit(string $key): void
{
    $state = rate_limit_state($key);
    $_SESSION['rate_limits'][$key]['count'] = $state['count'] + 1;
}

function clear_rate_limit(string $key): void
{
    ensure_session();
    unset($_SESSION['rate_limits'][$key]);
}
