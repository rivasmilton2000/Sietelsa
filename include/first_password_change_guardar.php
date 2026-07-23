<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/primer-acceso/cambiar-clave?error=metodo');
}

require_login();

if (!usuario_actual_debe_cambiar_password()) {
    redirigir('/admin/dashboard');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'first_password_change')) {
    redirigir('/admin/primer-acceso/cambiar-clave?error=csrf');
}

$passwordActual = (string) ($_POST['password_actual'] ?? '');
$passwordNueva = (string) ($_POST['password_nueva'] ?? '');
$passwordConfirmar = (string) ($_POST['password_confirmar'] ?? '');

if ($passwordActual === '' || $passwordNueva === '' || $passwordConfirmar === '') {
    redirigir('/admin/primer-acceso/cambiar-clave?error=campos');
}

if (!password_fuerte_valido($passwordNueva)) {
    redirigir('/admin/primer-acceso/cambiar-clave?error=policy');
}

if (!hash_equals($passwordNueva, $passwordConfirmar)) {
    redirigir('/admin/primer-acceso/cambiar-clave?error=confirm');
}

$usuario = obtener_usuario_actual() ?? [];
$usuarioId = (int) ($usuario['id'] ?? 0);
if ($usuarioId <= 0) {
    redirigir('/admin/login?error=auth');
}

global $pdo;

try {
    $stmt = $pdo->prepare('SELECT password_hash, must_change_password FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $fila = $stmt->fetch();
    if (!$fila) {
        cerrar_sesion();
        redirigir('/admin/login?error=auth');
    }

    $hashActual = (string) ($fila['password_hash'] ?? '');
    if ($hashActual === '' || !password_verify($passwordActual, $hashActual)) {
        redirigir('/admin/primer-acceso/cambiar-clave?error=actual');
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE usuarios
         SET password_hash = :password_hash,
             must_change_password = 0
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':password_hash' => password_hash($passwordNueva, PASSWORD_DEFAULT),
        ':id' => $usuarioId,
    ]);
} catch (Throwable $e) {
    sietelsa_log('First password change failed', [
        'user_id' => $usuarioId,
        'message' => $e->getMessage(),
    ]);
    redirigir('/admin/primer-acceso/cambiar-clave?error=db');
}

iniciar_sesion_segura();
$_SESSION['usuario']['must_change_password'] = 0;
invalidar_cache_permisos_usuario();

redirigir('/admin/dashboard');
