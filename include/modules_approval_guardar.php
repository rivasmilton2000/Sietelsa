<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

function redirigir_aprobacion_modulos_admin(string $tipo, string $estado, string $filtro = 'todos', string $slug = ''): void
{
    $filtrosValidos = ['todos', 'borrador', 'pendiente', 'aprobado', 'rechazado'];
    if (!in_array($filtro, $filtrosValidos, true)) {
        $filtro = 'todos';
    }

    $query = [
        'estado' => $filtro,
    ];
    if ($tipo === 'ok') {
        $query['ok'] = $estado;
    } else {
        $query['error'] = $estado;
    }

    $slug = modulos_publicacion_normalizar_slug($slug);
    if ($slug !== '') {
        $query['slug'] = $slug;
    }

    redirigir('/admin/modulos/aprobacion?' . http_build_query($query));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_aprobacion_modulos_admin('error', 'metodo');
}

require_admin();
ensure_rbac_runtime();

global $pdo;

$filtro = strtolower(trim((string) ($_POST['estado_filtro'] ?? 'todos')));
$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_modules_approval')) {
    redirigir_aprobacion_modulos_admin('error', 'csrf', $filtro);
}

$accion = strtolower(trim((string) ($_POST['accion'] ?? '')));
$mapaAccionEstado = [
    'aprobar' => 'APROBADO',
    'rechazar' => 'RECHAZADO',
    'borrador' => 'BORRADOR',
    'pendiente' => 'PENDIENTE',
];
if (!isset($mapaAccionEstado[$accion])) {
    redirigir_aprobacion_modulos_admin('error', 'accion', $filtro);
}

$slug = modulos_publicacion_normalizar_slug((string) ($_POST['slug'] ?? ''));
if ($slug === '') {
    redirigir_aprobacion_modulos_admin('error', 'slug', $filtro);
}

$nuevoEstado = $mapaAccionEstado[$accion];
if ($nuevoEstado === 'APROBADO' && !usuario_actual_es_admin()) {
    mostrar_pagina_error(403);
}

$motivo = trim((string) ($_POST['motivo'] ?? ''));
$motivo = preg_replace('/[\x00-\x1F\x7F]/u', '', $motivo) ?? '';
if ($nuevoEstado === 'RECHAZADO' && $motivo === '') {
    redirigir_aprobacion_modulos_admin('error', 'motivo', $filtro, $slug);
}
if (strlen($motivo) > 500) {
    redirigir_aprobacion_modulos_admin('error', 'motivo_largo', $filtro, $slug);
}

try {
    $stmtModulo = $pdo->prepare(
        'SELECT id, approval_status
         FROM system_modules
         WHERE slug = :slug
         LIMIT 1'
    );
    $stmtModulo->execute([':slug' => $slug]);
    $modulo = $stmtModulo->fetch();

    if (!$modulo) {
        redirigir_aprobacion_modulos_admin('error', 'slug', $filtro, $slug);
    }

    $estadoAnterior = modulos_publicacion_normalizar_estado((string) ($modulo['approval_status'] ?? 'BORRADOR'));
    $motivoGuardado = $nuevoEstado === 'RECHAZADO' ? $motivo : null;

    if ($estadoAnterior === $nuevoEstado && ($nuevoEstado !== 'RECHAZADO' || $motivoGuardado === '')) {
        redirigir_aprobacion_modulos_admin('ok', 'sin_cambios', $filtro, $slug);
    }

    $usuario = obtener_usuario_actual() ?? [];
    $usuarioId = (int) ($usuario['id'] ?? 0);
    $nombreAuditoria = trim((string) ($usuario['nombre'] ?? ''));
    if ($nombreAuditoria === '') {
        $nombreAuditoria = trim((string) ($usuario['username'] ?? ''));
    }
    if ($nombreAuditoria === '') {
        $nombreAuditoria = trim((string) ($usuario['email'] ?? ''));
    }
    if ($nombreAuditoria === '') {
        $nombreAuditoria = 'admin';
    }

    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare(
        'UPDATE system_modules
         SET approval_status = :approval_status,
             rejection_reason = :rejection_reason,
             reviewed_by_user_id = :reviewed_by_user_id,
             reviewed_at = NOW()
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':approval_status' => $nuevoEstado,
        ':rejection_reason' => $motivoGuardado,
        ':reviewed_by_user_id' => $usuarioId > 0 ? $usuarioId : null,
        ':id' => (int) ($modulo['id'] ?? 0),
    ]);

    $stmtAudit = $pdo->prepare(
        'INSERT INTO system_module_approval_audit (
            module_id,
            from_status,
            to_status,
            reason,
            changed_by_user_id,
            changed_by_name
         ) VALUES (
            :module_id,
            :from_status,
            :to_status,
            :reason,
            :changed_by_user_id,
            :changed_by_name
         )'
    );
    $stmtAudit->execute([
        ':module_id' => (int) ($modulo['id'] ?? 0),
        ':from_status' => $estadoAnterior,
        ':to_status' => $nuevoEstado,
        ':reason' => $motivoGuardado,
        ':changed_by_user_id' => $usuarioId > 0 ? $usuarioId : null,
        ':changed_by_name' => substr($nombreAuditoria, 0, 120),
    ]);

    $pdo->commit();

    modulos_publicacion_mapa($pdo, true);

    $okEstado = strtolower($nuevoEstado);
    redirigir_aprobacion_modulos_admin('ok', $okEstado, $filtro, $slug);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirigir_aprobacion_modulos_admin('error', 'db', $filtro, $slug);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirigir_aprobacion_modulos_admin('error', 'general', $filtro, $slug);
}
