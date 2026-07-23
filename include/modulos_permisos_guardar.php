<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulos_permisos_admin(string $tipo, string $estado, int $userId = 0, int $profileId = 0): void
{
    $query = [];
    if ($userId > 0) {
        $query['user_id'] = $userId;
    }
    if ($profileId > 0) {
        $query['profile_id'] = $profileId;
    }

    if ($tipo === 'ok') {
        $query['ok'] = $estado;
    } else {
        $query['error'] = $estado;
    }

    redirigir('/admin/modulos/permisos?' . http_build_query($query));
}

function normalizar_ids_modulos(array $modulos): array
{
    $ids = [];
    foreach ($modulos as $moduloId) {
        $id = (int) $moduloId;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_modulos_permisos_admin('error', 'metodo');
}

require_admin();
ensure_rbac_runtime();

global $pdo;
sync_modules($pdo);
sync_module_access_matrix($pdo);

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_modulos_permisos')) {
    redirigir_modulos_permisos_admin('error', 'csrf', (int) ($_POST['user_id'] ?? 0), (int) ($_POST['profile_id'] ?? 0));
}

$accion = strtolower(trim((string) ($_POST['accion'] ?? '')));
$accionesValidas = [
    'save_profile',
    'save_user_overrides',
    'restore_user_overrides',
    'allow_all_user',
    'deny_all_user',
];
if (!in_array($accion, $accionesValidas, true)) {
    redirigir_modulos_permisos_admin('error', 'accion');
}

$userId = (int) ($_POST['user_id'] ?? 0);
$profileId = (int) ($_POST['profile_id'] ?? 0);

try {
    $stmtModulos = $pdo->query('SELECT id FROM system_modules ORDER BY id ASC');
    $modulosIds = $stmtModulos ? array_map('intval', $stmtModulos->fetchAll(PDO::FETCH_COLUMN)) : [];
    if (empty($modulosIds)) {
        redirigir_modulos_permisos_admin('error', 'modulos', $userId, $profileId);
    }

    if ($accion === 'save_profile') {
        if ($profileId <= 0) {
            redirigir_modulos_permisos_admin('error', 'profile', $userId, $profileId);
        }

        $stmtPerfil = $pdo->prepare('SELECT id, nombre FROM perfiles WHERE id = :id LIMIT 1');
        $stmtPerfil->execute([':id' => $profileId]);
        $perfil = $stmtPerfil->fetch();
        if (!is_array($perfil)) {
            redirigir_modulos_permisos_admin('error', 'profile', $userId, $profileId);
        }

        $modulosSeleccionados = normalizar_ids_modulos(is_array($_POST['allowed_modules'] ?? null) ? $_POST['allowed_modules'] : []);
        $setSeleccionados = array_fill_keys($modulosSeleccionados, true);

        $perfilEsAdmin = modulos_rbac_es_perfil_admin((string) ($perfil['nombre'] ?? ''));
        $stmtUpsert = $pdo->prepare(
            'INSERT INTO profile_module_access (profile_id, module_id, allowed)
             VALUES (:profile_id, :module_id, :allowed)
             ON DUPLICATE KEY UPDATE allowed = VALUES(allowed), updated_at = CURRENT_TIMESTAMP'
        );

        $pdo->beginTransaction();
        foreach ($modulosIds as $moduleId) {
            $allow = $perfilEsAdmin ? 1 : (isset($setSeleccionados[(int) $moduleId]) ? 1 : 0);
            $stmtUpsert->execute([
                ':profile_id' => $profileId,
                ':module_id' => (int) $moduleId,
                ':allowed' => $allow,
            ]);
        }
        $pdo->commit();

        sync_module_access_matrix($pdo);
        redirigir_modulos_permisos_admin('ok', 'perfil_guardado', $userId, $profileId);
    }

    if ($userId <= 0) {
        redirigir_modulos_permisos_admin('error', 'user', $userId, $profileId);
    }

    $stmtUsuario = $pdo->prepare('SELECT id, perfil_id FROM usuarios WHERE id = :id LIMIT 1');
    $stmtUsuario->execute([':id' => $userId]);
    $usuario = $stmtUsuario->fetch();
    if (!is_array($usuario)) {
        redirigir_modulos_permisos_admin('error', 'user', $userId, $profileId);
    }

    if ($profileId <= 0) {
        $profileId = (int) ($usuario['perfil_id'] ?? 0);
    }

    if ($accion === 'restore_user_overrides') {
        $stmtDelete = $pdo->prepare('DELETE FROM user_module_override WHERE user_id = :user_id');
        $stmtDelete->execute([':user_id' => $userId]);

        redirigir_modulos_permisos_admin('ok', 'usuario_restaurado', $userId, $profileId);
    }

    if ($accion === 'allow_all_user' || $accion === 'deny_all_user') {
        $effect = $accion === 'allow_all_user' ? 'allow' : 'deny';

        $stmtUpsertAll = $pdo->prepare(
            'INSERT INTO user_module_override (user_id, module_id, effect)
             VALUES (:user_id, :module_id, :effect)
             ON DUPLICATE KEY UPDATE effect = VALUES(effect), updated_at = CURRENT_TIMESTAMP'
        );

        $pdo->beginTransaction();
        foreach ($modulosIds as $moduleId) {
            $stmtUpsertAll->execute([
                ':user_id' => $userId,
                ':module_id' => (int) $moduleId,
                ':effect' => $effect,
            ]);
        }
        $pdo->commit();

        redirigir_modulos_permisos_admin('ok', $accion === 'allow_all_user' ? 'usuario_permitido_todo' : 'usuario_denegado_todo', $userId, $profileId);
    }

    $effectsRaw = is_array($_POST['override_effect'] ?? null) ? $_POST['override_effect'] : [];
    $effectsValidos = ['inherit', 'allow', 'deny'];

    $stmtUpsertOverride = $pdo->prepare(
        'INSERT INTO user_module_override (user_id, module_id, effect)
         VALUES (:user_id, :module_id, :effect)
         ON DUPLICATE KEY UPDATE effect = VALUES(effect), updated_at = CURRENT_TIMESTAMP'
    );
    $stmtDeleteOverride = $pdo->prepare(
        'DELETE FROM user_module_override
         WHERE user_id = :user_id
           AND module_id = :module_id'
    );

    $pdo->beginTransaction();
    foreach ($modulosIds as $moduleId) {
        $moduleId = (int) $moduleId;
        $effect = strtolower(trim((string) ($effectsRaw[(string) $moduleId] ?? 'inherit')));
        if (!in_array($effect, $effectsValidos, true)) {
            $effect = 'inherit';
        }

        if ($effect === 'inherit') {
            $stmtDeleteOverride->execute([
                ':user_id' => $userId,
                ':module_id' => $moduleId,
            ]);
            continue;
        }

        $stmtUpsertOverride->execute([
            ':user_id' => $userId,
            ':module_id' => $moduleId,
            ':effect' => $effect,
        ]);
    }
    $pdo->commit();

    redirigir_modulos_permisos_admin('ok', 'usuario_guardado', $userId, $profileId);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirigir_modulos_permisos_admin('error', 'db', $userId, $profileId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirigir_modulos_permisos_admin('error', 'general', $userId, $profileId);
}
