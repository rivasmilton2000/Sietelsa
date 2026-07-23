<?php
declare(strict_types=1);

if (defined('SIETELSA_BOOTSTRAPPED')) {
    return;
}
define('SIETELSA_BOOTSTRAPPED', true);

define('SIETELSA_ROOT', dirname(__DIR__));

function sietelsa_env_load(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            continue;
        }

        $value = trim(substr($line, $pos + 1));
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $existing = getenv($key);
        if (is_string($existing) && $existing !== '') {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

sietelsa_env_load(SIETELSA_ROOT . '/.env');

function sietelsa_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (!is_string($value) || $value === '') {
        return $default;
    }

    return $value;
}

function sietelsa_env_bool(string $key, bool $default = false): bool
{
    $value = trim(strtolower(sietelsa_env($key, '')));
    if ($value === '') {
        return $default;
    }

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sietelsa_runtime_env(): string
{
    $env = strtolower(trim(sietelsa_env('ENV', sietelsa_env('APP_ENV', 'production'))));
    return $env !== '' ? $env : 'production';
}

function sietelsa_install_enabled(): bool
{
    return sietelsa_env_bool('INSTALL_ENABLED', false);
}

function sietelsa_remote_ip(): string
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function sietelsa_ip_es_localhost(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '') {
        return false;
    }

    return in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

function sietelsa_request_es_localhost(): bool
{
    return sietelsa_ip_es_localhost(sietelsa_remote_ip());
}

$appConfigPath = SIETELSA_ROOT . '/config/app.php';
$appConfig = is_file($appConfigPath) ? (array) require $appConfigPath : [];

$timezone = (string) ($appConfig['app_timezone'] ?? 'America/El_Salvador');
date_default_timezone_set($timezone);

$logPath = (string) ($appConfig['log_path'] ?? (SIETELSA_ROOT . '/logs/app.log'));
$logDir = dirname($logPath);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
if (!is_file($logPath)) {
    @touch($logPath);
}

$appEnv = strtolower(trim((string) ($appConfig['app_env'] ?? sietelsa_runtime_env())));
$displayErrors = $appEnv !== 'production';

ini_set('display_errors', $displayErrors ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $logPath);
error_reporting(E_ALL);

function sietelsa_log(string $message, array $context = []): void
{
    $payload = [
        'ts' => date('c'),
        'message' => $message,
        'context' => $context,
    ];
    error_log('[sietelsa] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

set_exception_handler(static function (Throwable $exception): void {
    sietelsa_log('Unhandled exception', [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo 'Error interno del servidor.';
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    sietelsa_log('Runtime warning', [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ]);

    return false;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    sietelsa_log('Fatal shutdown', $error);
});

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com https://use.fontawesome.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net; frame-src https://www.google.com https://www.gstatic.com; upgrade-insecure-requests");

    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
