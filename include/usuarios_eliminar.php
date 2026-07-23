<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/csrf.php';

function es_perfil_admin(string $perfil): bool
{
    $perfil = strtolower(trim($perfil));
    return $perfil === 'admin' || $perfil === 'administrador';
}

function es_perfil_developer(string $perfil): bool
{
    return strtolower(trim($perfil)) === 'developer';
}

function redirigir_eliminar_usuario(string $estado): void
{
    if ($estado === 'eliminado') {
        redirigir('/admin/usuarios/eliminar?ok=eliminado');
    }

    redirigir('/admin/usuarios/eliminar?error=' . urlencode($estado));
}

function eliminar_foto_usuario_por_ruta(?string $rutaRelativa): void
{
    if ($rutaRelativa === null || trim($rutaRelativa) === '') {
        return;
    }

    $filename = basename($rutaRelativa);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $path = __DIR__ . '/../assets/uploads/usuarios/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_eliminar_usuario('metodo');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_usuarios_eliminar')) {
    redirigir_eliminar_usuario('csrf');
}

require_modulo('gestionar_usuarios');

global $pdo;

$usuarioId = (int) ($_POST['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    redirigir_eliminar_usuario('id');
}

$stmtUsuario = $pdo->prepare(
    'SELECT u.id, u.foto_path, LOWER(TRIM(p.nombre)) AS perfil
     FROM usuarios u
     INNER JOIN perfiles p ON p.id = u.perfil_id
     WHERE u.id = :id
     LIMIT 1'
);
$stmtUsuario->execute([':id' => $usuarioId]);
$usuarioObjetivo = $stmtUsuario->fetch();
if (!$usuarioObjetivo) {
    redirigir_eliminar_usuario('id');
}

$sesion = obtener_usuario_actual() ?? [];
$usuarioSesionId = (int) ($sesion['id'] ?? 0);
$perfilSesion = strtolower(trim((string) ($sesion['perfil'] ?? '')));
$perfilObjetivo = strtolower(trim((string) ($usuarioObjetivo['perfil'] ?? '')));

if ($usuarioSesionId > 0 && $usuarioSesionId === $usuarioId) {
    redirigir_eliminar_usuario('self');
}

if (es_perfil_developer($perfilObjetivo) && !es_perfil_developer($perfilSesion)) {
    redirigir_eliminar_usuario('developer');
}

if (es_perfil_admin($perfilObjetivo) && !es_perfil_admin($perfilSesion) && !es_perfil_developer($perfilSesion)) {
    redirigir_eliminar_usuario('admin');
}

$fotoAEliminar = trim((string) ($usuarioObjetivo['foto_path'] ?? ''));

try {
    $pdo->beginTransaction();

    $stmtDeletePermisos = $pdo->prepare('DELETE FROM usuario_permiso WHERE usuario_id = :usuario_id');
    $stmtDeletePermisos->execute([':usuario_id' => $usuarioId]);

    $stmtDeleteUsuario = $pdo->prepare('DELETE FROM usuarios WHERE id = :id LIMIT 1');
    $stmtDeleteUsuario->execute([':id' => $usuarioId]);
    if ($stmtDeleteUsuario->rowCount() <= 0) {
        throw new RuntimeException('No se elimino el usuario.');
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirigir_eliminar_usuario('db');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirigir_eliminar_usuario('general');
}

if ($fotoAEliminar !== '') {
    eliminar_foto_usuario_por_ruta($fotoAEliminar);
}

redirigir_eliminar_usuario('eliminado');
