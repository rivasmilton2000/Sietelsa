#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Acceso prohibido.\n");
}

require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/db_setup.php';
require_once __DIR__ . '/../include/modules_approval.php';

global $pdo;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[migrate] No hay conexion PDO disponible.\n");
    exit(1);
}

$inicio = microtime(true);
$pasos = [
    'asegurar_compatibilidad_auth' => static function (PDO $pdo): void {
        asegurar_compatibilidad_auth($pdo);
    },
    'sync_modules' => static function (PDO $pdo): void {
        sync_modules($pdo);
    },
    'sync_module_access_matrix' => static function (PDO $pdo): void {
        sync_module_access_matrix($pdo);
    },
];

fwrite(STDOUT, "[migrate] Inicio " . date(DATE_ATOM) . PHP_EOL);

foreach ($pasos as $nombre => $ejecutor) {
    fwrite(STDOUT, "[migrate] Ejecutando {$nombre}..." . PHP_EOL);
    try {
        $ejecutor($pdo);
        fwrite(STDOUT, "[migrate] OK {$nombre}" . PHP_EOL);
    } catch (Throwable $e) {
        sietelsa_log('CLI migrate failed', [
            'step' => $nombre,
            'message' => $e->getMessage(),
        ]);
        fwrite(STDERR, "[migrate] ERROR {$nombre}: {$e->getMessage()}" . PHP_EOL);
        exit(1);
    }
}

$duracionMs = (int) round((microtime(true) - $inicio) * 1000);
fwrite(STDOUT, "[migrate] Completado en {$duracionMs} ms" . PHP_EOL);
