<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/modules_approval.php';

global $pdo;

if (!sietelsa_install_enabled()) {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    sietelsa_log('Security installer access blocked', [
        'reason' => 'install_disabled',
        'ip' => sietelsa_remote_ip(),
        'env' => sietelsa_runtime_env(),
        'path' => $path,
    ]);
    http_response_code(403);
    exit('Acceso prohibido.');
}

if (!sietelsa_request_es_localhost()) {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    sietelsa_log('Security installer access blocked', [
        'reason' => 'ip_not_allowed',
        'ip' => sietelsa_remote_ip(),
        'env' => sietelsa_runtime_env(),
        'path' => $path,
    ]);
    http_response_code(403);
    exit('Acceso prohibido.');
}

try {
    asegurar_compatibilidad_auth($pdo);
    sync_modules($pdo);
    sync_module_access_matrix($pdo);
} catch (Throwable $e) {
    sietelsa_log('Error installing auth schema', ['message' => $e->getMessage()]);
    http_response_code(500);
    exit('Error interno del servidor.');
}

header('Location: /admin/login?ok=instalado');
exit;
