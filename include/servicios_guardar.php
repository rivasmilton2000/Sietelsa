<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/servicios_iconos.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulo_servicios(string $estado, int $servicioId = 0): void
{
    $vista = 'crear';
    if ($estado === 'actualizado' || ($servicioId > 0 && !in_array($estado, ['creado', 'eliminado'], true))) {
        $vista = 'editar';
    }

    $query = in_array($estado, ['creado', 'actualizado', 'eliminado'], true)
        ? 'ok=' . urlencode($estado)
        : 'error=' . urlencode($estado);

    $query .= '&vista=' . urlencode($vista);

    if ($servicioId > 0 && $vista === 'editar') {
        $query .= '&id=' . $servicioId;
    }

    redirigir('/admin/servicios?' . $query);
}

function obtener_siguiente_orden_servicio(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COALESCE(MAX(orden), 0) FROM servicios');
    $maxOrden = (int) ($stmt ? $stmt->fetchColumn() : 0);
    return $maxOrden + 1;
}

function reordenar_servicios_continuo(PDO $pdo): void
{
    $idsStmt = $pdo->query('SELECT id FROM servicios ORDER BY orden ASC, id ASC');
    $ids = $idsStmt ? $idsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($ids)) {
        return;
    }

    $stmtUpdate = $pdo->prepare('UPDATE servicios SET orden = :orden WHERE id = :id LIMIT 1');
    $orden = 1;
    foreach ($ids as $idRaw) {
        $stmtUpdate->execute([
            ':orden' => $orden,
            ':id' => (int) $idRaw,
        ]);
        $orden++;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

require_modulo('gestionar_servicios');
global $pdo;
asegurar_tabla_servicios($pdo);

$accion = trim((string) ($_POST['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    redirigir('/admin/dashboard');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_servicios')) {
    redirigir_modulo_servicios('csrf');
}

$servicioId = (int) ($_POST['servicio_id'] ?? 0);

if ($accion === 'eliminar') {
    if ($servicioId <= 0) {
        redirigir_modulo_servicios('id');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM servicios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $servicioId]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            redirigir_modulo_servicios('id');
        }
        reordenar_servicios_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_servicios('db', $servicioId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_servicios('general', $servicioId);
    }

    redirigir_modulo_servicios('eliminado');
}

$titulo = trim((string) ($_POST['titulo'] ?? ''));
$icono = normalizar_icono_servicio_lista((string) ($_POST['icono'] ?? ''));
$activo = isset($_POST['activo']) ? 1 : 0;

if ($titulo === '') {
    redirigir_modulo_servicios('titulo', $servicioId);
}

if (strlen($titulo) > 120) {
    redirigir_modulo_servicios('titulo', $servicioId);
}

if (!icono_servicio_es_valido($icono)) {
    redirigir_modulo_servicios('icono', $servicioId);
}

if ($accion === 'editar' && $servicioId <= 0) {
    redirigir_modulo_servicios('id');
}

try {
    if ($accion === 'crear') {
        $pdo->beginTransaction();
        $orden = obtener_siguiente_orden_servicio($pdo);
        $stmtInsert = $pdo->prepare(
            'INSERT INTO servicios (titulo, descripcion, icono, orden, activo)
             VALUES (:titulo, NULL, :icono, :orden, :activo)'
        );
        $stmtInsert->execute([
            ':titulo' => $titulo,
            ':icono' => $icono,
            ':orden' => (int) $orden,
            ':activo' => $activo,
        ]);
        $servicioId = (int) $pdo->lastInsertId();
        reordenar_servicios_continuo($pdo);
        $pdo->commit();
        redirigir_modulo_servicios('creado', $servicioId);
    }

    $stmtExiste = $pdo->prepare('SELECT id FROM servicios WHERE id = :id LIMIT 1');
    $stmtExiste->execute([':id' => $servicioId]);
    if (!$stmtExiste->fetchColumn()) {
        redirigir_modulo_servicios('id');
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE servicios
         SET titulo = :titulo,
             icono = :icono,
             activo = :activo
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':id' => $servicioId,
        ':titulo' => $titulo,
        ':icono' => $icono,
        ':activo' => $activo,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirigir_modulo_servicios('db', $servicioId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirigir_modulo_servicios('general', $servicioId);
}

redirigir_modulo_servicios('actualizado', $servicioId);
