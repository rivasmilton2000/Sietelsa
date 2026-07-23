<?php

declare(strict_types=1);

const SIETELSA_AUTH_SESSION_KEY = 'sietelsa_user';
const SIETELSA_CSRF_SESSION_KEY = 'sietelsa_csrf_token';

function app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $srcPosition = strpos($scriptName, '/src/');

    if ($srcPosition !== false) {
        return rtrim(substr($scriptName, 0, $srcPosition), '/');
    }

    $directory = str_replace('\\', '/', dirname($scriptName));

    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');

    return $base . ($path === '' ? '/' : '/' . $path);
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookiePath = app_base_path() . '/';

    session_name('SIETELSA_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auth_csrf_token(): string
{
    if (
        !isset($_SESSION[SIETELSA_CSRF_SESSION_KEY])
        || !is_string($_SESSION[SIETELSA_CSRF_SESSION_KEY])
    ) {
        $_SESSION[SIETELSA_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[SIETELSA_CSRF_SESSION_KEY];
}

function auth_rotate_csrf_token(): string
{
    $_SESSION[SIETELSA_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));

    return $_SESSION[SIETELSA_CSRF_SESSION_KEY];
}

function auth_validate_csrf(?string $token): bool
{
    $storedToken = $_SESSION[SIETELSA_CSRF_SESSION_KEY] ?? null;

    return is_string($token)
        && is_string($storedToken)
        && hash_equals($storedToken, $token);
}

function auth_current_user(): ?array
{
    $user = $_SESSION[SIETELSA_AUTH_SESSION_KEY] ?? null;

    return is_array($user) ? $user : null;
}

function auth_is_authenticated(): bool
{
    $user = auth_current_user();

    return isset($user['id'], $user['role']);
}

function auth_is_admin(): bool
{
    $user = auth_current_user();

    return isset($user['role']) && $user['role'] === 'admin';
}

function auth_user_name(): string
{
    $user = auth_current_user();

    return (string) ($user['name'] ?? $user['username'] ?? '');
}

function auth_user_identifier(): string
{
    $user = auth_current_user();

    return (string) ($user['email'] ?? $user['username'] ?? '');
}

function auth_store_user(array $databaseUser): void
{
    session_regenerate_id(true);
    $_SESSION[SIETELSA_AUTH_SESSION_KEY] = [
        'id' => (int) $databaseUser['id'],
        'name' => (string) $databaseUser['nombre'],
        'username' => (string) $databaseUser['username'],
        'email' => (string) $databaseUser['email'],
        'role' => (string) $databaseUser['rol'],
    ];
    $_SESSION['sietelsa_authenticated_at'] = time();
    auth_rotate_csrf_token();
}

function auth_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

function auth_redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function auth_require_admin(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $sessionUser = auth_current_user();

    if ($sessionUser === null || !isset($sessionUser['id'])) {
        auth_redirect('src/login/login.php');
    }

    if (($sessionUser['role'] ?? '') !== 'admin') {
        auth_logout();
        auth_redirect('index.php');
    }

    require_once __DIR__ . '/conexion.php';

    try {
        $statement = db_connection()->prepare(
            'SELECT id, nombre, username, email, rol, activo
             FROM usuarios
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => (int) $sessionUser['id']]);
        $databaseUser = $statement->fetch();
    } catch (Throwable $exception) {
        error_log('[SIETELSA] No fue posible validar la sesión administrativa: ' . $exception->getMessage());
        auth_logout();
        auth_redirect('src/login/login.php?status=internal');
    }

    if (
        !$databaseUser
        || (int) $databaseUser['activo'] !== 1
        || $databaseUser['rol'] !== 'admin'
    ) {
        auth_logout();
        auth_redirect('src/login/login.php?status=denied');
    }

    $_SESSION[SIETELSA_AUTH_SESSION_KEY] = [
        'id' => (int) $databaseUser['id'],
        'name' => (string) $databaseUser['nombre'],
        'username' => (string) $databaseUser['username'],
        'email' => (string) $databaseUser['email'],
        'role' => (string) $databaseUser['rol'],
    ];
}

auth_start_session();
require_once __DIR__ . '/branding.php';
