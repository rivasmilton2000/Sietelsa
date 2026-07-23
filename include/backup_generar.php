<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/csrf.php';

function backup_redirigir(string $estado): void
{
    redirigir('/admin/backup?error=' . urlencode($estado));
}

function backup_escape_identificador(string $valor): string
{
    return '`' . str_replace('`', '``', $valor) . '`';
}

function backup_sql_valor(PDO $pdo, $valor): string
{
    if ($valor === null) {
        return 'NULL';
    }

    if (is_bool($valor)) {
        return $valor ? '1' : '0';
    }

    if (is_int($valor) || is_float($valor)) {
        return (string) $valor;
    }

    return $pdo->quote((string) $valor);
}

function backup_escribir($handle, string $contenido): void
{
    if (fwrite($handle, $contenido) === false) {
        throw new RuntimeException('No fue posible escribir el respaldo SQL.');
    }
}

function backup_obtener_tablas(PDO $pdo): array
{
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tablas = [];

    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $fila) {
            if (isset($fila[0]) && trim((string) $fila[0]) !== '') {
                $tablas[] = (string) $fila[0];
            }
        }
    }

    return $tablas;
}

function backup_exportar_tabla(PDO $pdo, $handle, string $tabla): void
{
    $tablaSql = backup_escape_identificador($tabla);
    $stmtCreate = $pdo->query('SHOW CREATE TABLE ' . $tablaSql);
    $filaCreate = $stmtCreate ? $stmtCreate->fetch(PDO::FETCH_NUM) : false;
    if (!is_array($filaCreate) || !isset($filaCreate[1])) {
        throw new RuntimeException('No fue posible obtener la estructura de la tabla: ' . $tabla);
    }

    backup_escribir($handle, "-- --------------------------------------------------\n");
    backup_escribir($handle, '-- Tabla: ' . $tabla . "\n");
    backup_escribir($handle, "-- --------------------------------------------------\n");
    backup_escribir($handle, 'DROP TABLE IF EXISTS ' . $tablaSql . ";\n");
    backup_escribir($handle, (string) $filaCreate[1] . ";\n\n");

    $stmtColumnas = $pdo->query('SHOW COLUMNS FROM ' . $tablaSql);
    $columnas = [];
    if ($stmtColumnas) {
        foreach ($stmtColumnas->fetchAll(PDO::FETCH_ASSOC) as $columna) {
            $campo = trim((string) ($columna['Field'] ?? ''));
            if ($campo !== '') {
                $columnas[] = $campo;
            }
        }
    }

    if (empty($columnas)) {
        return;
    }

    $listaColumnas = implode(', ', array_map('backup_escape_identificador', $columnas));
    $stmtDatos = $pdo->query('SELECT * FROM ' . $tablaSql);
    if (!$stmtDatos) {
        return;
    }

    while ($fila = $stmtDatos->fetch(PDO::FETCH_ASSOC)) {
        $valores = [];
        foreach ($columnas as $columna) {
            $valores[] = backup_sql_valor($pdo, $fila[$columna] ?? null);
        }

        backup_escribir(
            $handle,
            'INSERT INTO ' . $tablaSql . ' (' . $listaColumnas . ') VALUES (' . implode(', ', $valores) . ");\n"
        );
    }

    backup_escribir($handle, "\n");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/backup');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_backup')) {
    backup_redirigir('csrf');
}

require_modulo('gestionar_backup');
global $pdo;

$backupDir = __DIR__ . '/../database';
if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    backup_redirigir('dir');
}

$nombreDb = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: 'database');
$nombreDbLimpio = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombreDb) ?: 'database';
$archivoNombre = 'backup_' . $nombreDbLimpio . '_' . date('Ymd_His') . '.sql';
$archivoRuta = $backupDir . '/' . $archivoNombre;

try {
    $handle = fopen($archivoRuta, 'wb');
    if ($handle === false) {
        throw new RuntimeException('No fue posible crear el archivo de respaldo.');
    }

    backup_escribir($handle, '-- Respaldo SQL generado por Sietelsa' . "\n");
    backup_escribir($handle, '-- Fecha: ' . date('Y-m-d H:i:s') . "\n");
    backup_escribir($handle, '-- Base de datos: ' . $nombreDb . "\n\n");
    backup_escribir($handle, "SET NAMES utf8mb4;\n");
    backup_escribir($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $tablas = backup_obtener_tablas($pdo);
    if (empty($tablas)) {
        throw new RuntimeException('No se encontraron tablas para respaldar.');
    }

    foreach ($tablas as $tabla) {
        backup_exportar_tabla($pdo, $handle, $tabla);
    }

    backup_escribir($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
} catch (Throwable $e) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }
    if (is_file($archivoRuta)) {
        @unlink($archivoRuta);
    }
    backup_redirigir('generar');
}

$tamano = is_file($archivoRuta) ? filesize($archivoRuta) : 0;
if (!is_file($archivoRuta) || $tamano === false || $tamano <= 0) {
    backup_redirigir('generar');
}

if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . basename($archivoRuta) . '"');
header('Content-Length: ' . (string) $tamano);
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: public');

readfile($archivoRuta);
exit;
