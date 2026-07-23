#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Acceso prohibido.\n");
}

require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/visitas_analytics.php';

global $pdo;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[geoip-backfill] No hay conexion PDO disponible.\n");
    exit(1);
}

$options = getopt('', ['pagina::', 'batch::', 'max::']);

$pagina = trim((string) ($options['pagina'] ?? 'inicio'));
if ($pagina === '') {
    $pagina = 'inicio';
}

$batch = (int) ($options['batch'] ?? 100);
$batch = max(1, min(500, $batch));

$max = (int) ($options['max'] ?? 5000);
$max = max(1, min(50000, $max));

$inicio = microtime(true);
$totalActualizados = 0;
$iteracion = 0;

fwrite(STDOUT, '[geoip-backfill] Inicio ' . date(DATE_ATOM) . PHP_EOL);
fwrite(STDOUT, "[geoip-backfill] pagina={$pagina} batch={$batch} max={$max}" . PHP_EOL);

while ($totalActualizados < $max) {
    $iteracion++;
    $lote = min($batch, $max - $totalActualizados);

    try {
        $actualizados = enriquecer_visitas_sin_pais($pdo, $pagina, $lote);
    } catch (Throwable $e) {
        sietelsa_log('CLI geoip backfill visitas failed', [
            'pagina' => $pagina,
            'iteracion' => $iteracion,
            'message' => $e->getMessage(),
        ]);
        fwrite(STDERR, "[geoip-backfill] ERROR iteracion {$iteracion}: {$e->getMessage()}" . PHP_EOL);
        exit(1);
    }

    if ($actualizados <= 0) {
        fwrite(STDOUT, '[geoip-backfill] Sin filas pendientes para enriquecer.' . PHP_EOL);
        break;
    }

    $totalActualizados += $actualizados;
    fwrite(STDOUT, "[geoip-backfill] Iteracion {$iteracion}: +{$actualizados} (total {$totalActualizados})" . PHP_EOL);

    if ($actualizados < $lote) {
        break;
    }
}

$duracionMs = (int) round((microtime(true) - $inicio) * 1000);
fwrite(STDOUT, "[geoip-backfill] Completado. Filas actualizadas: {$totalActualizados}. Duracion: {$duracionMs} ms." . PHP_EOL);

