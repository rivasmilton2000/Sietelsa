<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/maintenance.php';

function mantenimiento_reporte_redirigir(string $estado): void
{
    redirigir('/admin/mantenimiento?mantenimiento_error=' . urlencode($estado));
}

function mantenimiento_reporte_acceso_desbloqueado(PDO $pdo): bool
{
    if (!mantenimiento_password_configurada($pdo)) {
        return true;
    }

    $desbloqueoHasta = (int) ($_SESSION['mantenimiento_unlock_until'] ?? 0);
    return $desbloqueoHasta > time();
}

function mantenimiento_reporte_usuario(array $evento): string
{
    $nombre = trim((string) ($evento['usuario_nombre_actual'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string) ($evento['usuario_nombre'] ?? ''));
    }

    $username = trim((string) ($evento['usuario_username_actual'] ?? ''));
    if ($username === '') {
        $username = trim((string) ($evento['usuario_username'] ?? ''));
    }

    if ($nombre !== '' && $username !== '') {
        return $nombre . ' (' . $username . ')';
    }

    if ($nombre !== '') {
        return $nombre;
    }

    if ($username !== '') {
        return $username;
    }

    return 'Sistema';
}

function mantenimiento_reporte_pdf_escape(string $texto): string
{
    $texto = str_replace(["\r", "\n", "\t"], ' ', $texto);

    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $texto);
        if (is_string($convertido) && $convertido !== '') {
            $texto = $convertido;
        }
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
}

function mantenimiento_reporte_pdf_lineas_normalizadas(array $lineas, int $ancho = 95): array
{
    $salida = [];

    foreach ($lineas as $linea) {
        $linea = trim((string) $linea);
        if ($linea === '') {
            $salida[] = ' ';
            continue;
        }

        $segmentos = explode("\n", wordwrap($linea, $ancho, "\n", true));
        foreach ($segmentos as $segmento) {
            $salida[] = $segmento;
        }
    }

    if (empty($salida)) {
        $salida[] = 'Sin registros.';
    }

    return $salida;
}

function mantenimiento_reporte_pdf_generar(array $lineas): string
{
    $lineas = mantenimiento_reporte_pdf_lineas_normalizadas($lineas);
    $lineasPorPagina = 48;
    $paginas = array_chunk($lineas, $lineasPorPagina);
    if (empty($paginas)) {
        $paginas = [['Sin registros.']];
    }

    $objetos = [];
    $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';

    $kids = [];
    $indicePagina = 0;
    foreach ($paginas as $lineasPagina) {
        $paginaId = 3 + ($indicePagina * 2);
        $contenidoId = $paginaId + 1;
        $kids[] = $paginaId . ' 0 R';

        $stream = "BT\n/F1 10 Tf\n50 760 Td\n";
        $primera = true;
        foreach ($lineasPagina as $linea) {
            if (!$primera) {
                $stream .= "0 -14 Td\n";
            }
            $stream .= '(' . mantenimiento_reporte_pdf_escape($linea) . ") Tj\n";
            $primera = false;
        }
        $stream .= "ET\n";

        $objetos[$paginaId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /ProcSet [/PDF /Text] /Font << /F1 ' . (3 + (count($paginas) * 2)) . ' 0 R >> >> /Contents ' . $contenidoId . ' 0 R >>';
        $objetos[$contenidoId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';

        $indicePagina++;
    }

    $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($paginas) . ' >>';
    $fuenteId = 3 + (count($paginas) * 2);
    $objetos[$fuenteId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    ksort($objetos);
    $maxId = (int) max(array_keys($objetos));

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];

    for ($id = 1; $id <= $maxId; $id++) {
        if (!isset($objetos[$id])) {
            continue;
        }

        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $objetos[$id] . "\nendobj\n";
    }

    $startXref = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($id = 1; $id <= $maxId; $id++) {
        $offset = $offsets[$id] ?? 0;
        $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $startXref . "\n%%EOF";

    return $pdf;
}

require_admin();

$usuario = obtener_usuario_actual();
if (!usuario_puede_configurar_password_mantenimiento($usuario)) {
    mostrar_pagina_error(403);
}

global $pdo;

if (!mantenimiento_reporte_acceso_desbloqueado($pdo)) {
    mantenimiento_reporte_redirigir('desbloqueo_requerido');
}

$formato = strtolower(trim((string) ($_GET['formato'] ?? '')));
if (!in_array($formato, ['excel', 'pdf'], true)) {
    mantenimiento_reporte_redirigir('reporte_formato');
}

try {
    mantenimiento_registrar_evento(
        $pdo,
        $usuario,
        $formato === 'excel' ? 'exportar_reporte_mantenimiento_excel' : 'exportar_reporte_mantenimiento_pdf',
        'Reporte de mantenimiento generado en formato ' . strtoupper($formato) . '.'
    );
} catch (Throwable $e) {
}

$eventos = mantenimiento_obtener_eventos($pdo, 5000);
$filas = [];

foreach ($eventos as $evento) {
    $filas[] = [
        'fecha' => trim((string) ($evento['creado_en'] ?? '')),
        'accion' => mantenimiento_label_accion((string) ($evento['accion'] ?? 'evento')),
        'usuario' => mantenimiento_reporte_usuario($evento),
        'ip' => trim((string) ($evento['ip_origen'] ?? '')),
        'detalle' => trim((string) ($evento['detalle'] ?? '')),
    ];
}

$fechaGeneracion = date('Y-m-d_His');

if (ob_get_level() > 0) {
    ob_end_clean();
}

if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_mantenimiento_' . $fechaGeneracion . '.xls"');
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    header('Pragma: public');

    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reporte mantenimiento</title></head><body>';
    echo '<h3>Reporte de mantenimiento</h3>';
    echo '<p>Generado: ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<thead><tr><th>#</th><th>Fecha y hora</th><th>Accion</th><th>Usuario</th><th>IP</th><th>Detalle</th></tr></thead><tbody>';

    if (empty($filas)) {
        echo '<tr><td colspan="6">Sin registros.</td></tr>';
    } else {
        $i = 1;
        foreach ($filas as $fila) {
            echo '<tr>';
            echo '<td>' . $i . '</td>';
            echo '<td>' . htmlspecialchars((string) $fila['fecha'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $fila['accion'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $fila['usuario'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $fila['ip'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $fila['detalle'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
            $i++;
        }
    }

    echo '</tbody></table></body></html>';
    exit;
}

$lineasPdf = [];
$lineasPdf[] = 'Reporte de mantenimiento - Sietelsa';
$lineasPdf[] = 'Generado: ' . date('Y-m-d H:i:s');
$lineasPdf[] = 'Total de registros: ' . count($filas);
$lineasPdf[] = '';
$lineasPdf[] = 'Fecha y hora | Accion | Usuario | IP | Detalle';
$lineasPdf[] = str_repeat('-', 130);

if (empty($filas)) {
    $lineasPdf[] = 'Sin registros.';
} else {
    foreach ($filas as $fila) {
        $detalle = (string) $fila['detalle'];
        if (strlen($detalle) > 110) {
            $detalle = substr($detalle, 0, 107) . '...';
        }

        $lineasPdf[] = (string) $fila['fecha']
            . ' | ' . (string) $fila['accion']
            . ' | ' . (string) $fila['usuario']
            . ' | ' . (string) $fila['ip']
            . ' | ' . $detalle;
    }
}

$pdf = mantenimiento_reporte_pdf_generar($lineasPdf);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="reporte_mantenimiento_' . $fechaGeneracion . '.pdf"');
header('Content-Length: ' . (string) strlen($pdf));
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: public');

echo $pdf;
exit;
