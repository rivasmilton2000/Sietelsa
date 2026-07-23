<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulo_perfiles(string $estado, int $perfilId = 0): void
{
    if ($estado === 'creado' || $estado === 'actualizado' || $estado === 'eliminado') {
        $query = 'ok=' . urlencode($estado);
    } else {
        $query = 'error=' . urlencode($estado);
    }

    if ($perfilId > 0) {
        $query .= '&id=' . $perfilId;
    }

    redirigir('/admin/perfiles?' . $query);
}

function es_perfil_base_protegido(string $nombrePerfil): bool
{
    $nombre = strtolower(trim($nombrePerfil));
    return in_array($nombre, ['admin', 'developer'], true);
}

function normalizar_modulos_perfil(array $modulos): array
{
    $resultado = [];
    foreach ($modulos as $idModulo) {
        $id = (int) $idModulo;
        if ($id > 0) {
            $resultado[$id] = true;
        }
    }
    return array_keys($resultado);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

require_modulo('gestionar_perfiles', true);

global $pdo;

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_perfiles')) {
    redirigir_modulo_perfiles('csrf', (int) ($_POST['perfil_id'] ?? 0));
}

$accion = trim((string) ($_POST['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar', 'actualizar_modulos'], true)) {
    redirigir_modulo_perfiles('accion');
}

$perfilId = (int) ($_POST['perfil_id'] ?? 0);
$nombre = strtolower(trim((string) ($_POST['nombre'] ?? '')));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));

if ($accion === 'crear' || $accion === 'editar') {
    if ($nombre === '') {
        redirigir_modulo_perfiles('campos', $perfilId);
    }
    if (preg_match('/^[a-z0-9_]{3,50}$/', $nombre) !== 1) {
        redirigir_modulo_perfiles('nombre', $perfilId);
    }
    if (strlen($descripcion) > 150) {
        redirigir_modulo_perfiles('descripcion', $perfilId);
    }
}

if (($accion === 'editar' || $accion === 'eliminar' || $accion === 'actualizar_modulos') && $perfilId <= 0) {
    redirigir_modulo_perfiles('id');
}

$perfilExistente = null;
if ($accion === 'editar' || $accion === 'eliminar' || $accion === 'actualizar_modulos') {
    $stmtPerfil = $pdo->prepare('SELECT id, nombre FROM perfiles WHERE id = :id LIMIT 1');
    $stmtPerfil->execute([':id' => $perfilId]);
    $perfilExistente = $stmtPerfil->fetch();
    if (!$perfilExistente) {
        redirigir_modulo_perfiles('id');
    }
}

if ($accion === 'editar' && $perfilExistente !== null) {
    $nombreActual = strtolower(trim((string) ($perfilExistente['nombre'] ?? '')));
    $esBase = es_perfil_base_protegido($nombreActual);
    if ($esBase && $nombre !== $nombreActual) {
        redirigir_modulo_perfiles('protegido', $perfilId);
    }
}

if ($accion === 'eliminar' && $perfilExistente !== null) {
    $nombreActual = strtolower(trim((string) ($perfilExistente['nombre'] ?? '')));
    if (es_perfil_base_protegido($nombreActual)) {
        redirigir_modulo_perfiles('protegido', $perfilId);
    }
}

try {
    if ($accion === 'actualizar_modulos') {
        if (!usuario_actual_es_admin()) {
            mostrar_pagina_error(403);
        }

        $modulosSeleccionados = normalizar_modulos_perfil(is_array($_POST['modulos'] ?? null) ? $_POST['modulos'] : []);
        $nombrePerfilObjetivo = strtolower(trim((string) ($perfilExistente['nombre'] ?? '')));

        $stmtTodosModulos = $pdo->query("SELECT id FROM modulos WHERE estado = 'activo'");
        $todosModulos = $stmtTodosModulos ? array_map('intval', $stmtTodosModulos->fetchAll(PDO::FETCH_COLUMN)) : [];
        $setModulos = ($nombrePerfilObjetivo === 'admin')
            ? array_fill_keys($todosModulos, true)
            : array_fill_keys($modulosSeleccionados, true);

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare('DELETE FROM perfil_modulo WHERE perfil_id = :perfil_id');
        $stmtDelete->execute([':perfil_id' => $perfilId]);

        if (!empty($todosModulos)) {
            $stmtInsert = $pdo->prepare(
                'INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
                 VALUES (:perfil_id, :modulo_id, 1)'
            );
            foreach ($todosModulos as $moduloId) {
                if (!isset($setModulos[(int) $moduloId])) {
                    continue;
                }
                $stmtInsert->execute([
                    ':perfil_id' => $perfilId,
                    ':modulo_id' => (int) $moduloId,
                ]);
            }
        }

        $pdo->commit();
        invalidar_cache_permisos_usuario();
        redirigir_modulo_perfiles('actualizado', $perfilId);
    }

    if ($accion === 'crear') {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO perfiles (nombre, descripcion)
             VALUES (:nombre, :descripcion)'
        );
        $stmtInsert->execute([
            ':nombre' => $nombre,
            ':descripcion' => ($descripcion !== '' ? $descripcion : null),
        ]);
        redirigir_modulo_perfiles('creado', (int) $pdo->lastInsertId());
    }

    if ($accion === 'editar') {
        $stmtUpdate = $pdo->prepare(
            'UPDATE perfiles
             SET nombre = :nombre, descripcion = :descripcion
             WHERE id = :id
             LIMIT 1'
        );
        $stmtUpdate->execute([
            ':id' => $perfilId,
            ':nombre' => $nombre,
            ':descripcion' => ($descripcion !== '' ? $descripcion : null),
        ]);
        redirigir_modulo_perfiles('actualizado', $perfilId);
    }

    $pdo->beginTransaction();

    $stmtUsuarios = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE perfil_id = :perfil_id');
    $stmtUsuarios->execute([':perfil_id' => $perfilId]);
    $usuariosAsociados = (int) $stmtUsuarios->fetchColumn();
    if ($usuariosAsociados > 0) {
        $pdo->rollBack();
        redirigir_modulo_perfiles('en_uso', $perfilId);
    }

    $stmtDeletePermisosPerfil = $pdo->prepare('DELETE FROM perfil_permiso WHERE perfil_id = :perfil_id');
    $stmtDeletePermisosPerfil->execute([':perfil_id' => $perfilId]);

    $stmtDeletePerfil = $pdo->prepare('DELETE FROM perfiles WHERE id = :id LIMIT 1');
    $stmtDeletePerfil->execute([':id' => $perfilId]);
    if ($stmtDeletePerfil->rowCount() <= 0) {
        $pdo->rollBack();
        redirigir_modulo_perfiles('id');
    }

    $pdo->commit();

    redirigir_modulo_perfiles('eliminado');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $errorCode = (string) ($e->errorInfo[1] ?? '');
    if ($errorCode === '1062') {
        redirigir_modulo_perfiles('duplicado', $perfilId);
    }
    if ($errorCode === '1451') {
        redirigir_modulo_perfiles('en_uso', $perfilId);
    }
    redirigir_modulo_perfiles('db', $perfilId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirigir_modulo_perfiles('general', $perfilId);
}
