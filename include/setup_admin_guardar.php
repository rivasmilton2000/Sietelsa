<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/setup-admin?error=metodo');
}

if (!sietelsa_setup_admin_habilitado_en_request()) {
    sietelsa_log('Security setup-admin create blocked', [
        'reason' => 'setup_disabled_or_remote',
        'ip' => sietelsa_remote_ip(),
        'env' => sietelsa_runtime_env(),
        'path' => ruta_request_actual(),
    ]);
    http_response_code(403);
    exit('Acceso prohibido.');
}

global $pdo;

if (hay_admin_configurado()) {
    redirigir('/admin/setup-admin?error=exists');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'setup_admin_create')) {
    redirigir('/admin/setup-admin?error=csrf');
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if ($nombre === '' || $username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
    redirigir('/admin/setup-admin?error=campos');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigir('/admin/setup-admin?error=correo');
}

if (preg_match('/^[A-Za-z0-9_-]{3,60}$/', $username) !== 1) {
    redirigir('/admin/setup-admin?error=username');
}

if (!password_fuerte_valido($password)) {
    redirigir('/admin/setup-admin?error=password_policy');
}

if (!hash_equals($password, $passwordConfirm)) {
    redirigir('/admin/setup-admin?error=password_confirm');
}

try {
    $pdo->beginTransaction();

    if (existe_admin_configurado($pdo)) {
        $pdo->rollBack();
        redirigir('/admin/setup-admin?error=exists');
    }

    $stmtPerfil = $pdo->prepare(
        "SELECT id
         FROM perfiles
         WHERE LOWER(TRIM(nombre)) IN ('admin', 'administrador')
         ORDER BY CASE WHEN LOWER(TRIM(nombre)) = 'admin' THEN 0 ELSE 1 END
         LIMIT 1"
    );
    $stmtPerfil->execute();
    $perfilId = (int) ($stmtPerfil->fetchColumn() ?: 0);

    if ($perfilId <= 0) {
        $stmtCrearPerfil = $pdo->prepare(
            "INSERT INTO perfiles (nombre, descripcion, activo)
             VALUES ('admin', 'Control total del sistema', 1)"
        );
        $stmtCrearPerfil->execute();
        $perfilId = (int) $pdo->lastInsertId();
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO usuarios (nombre, username, email, password_hash, must_change_password, perfil_id, activo)
         VALUES (:nombre, :username, :email, :password_hash, 1, :perfil_id, 1)'
    );
    $stmtInsert->execute([
        ':nombre' => $nombre,
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':perfil_id' => $perfilId,
    ]);

    $adminId = (int) $pdo->lastInsertId();
    $pdo->commit();

    sietelsa_log('Security initial admin created', [
        'admin_user_id' => $adminId,
        'username' => $username,
        'ip' => sietelsa_remote_ip(),
        'env' => sietelsa_runtime_env(),
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((string) ($e->errorInfo[0] ?? '') === '23000') {
        redirigir('/admin/setup-admin?error=duplicate');
    }

    sietelsa_log('Setup admin DB error', [
        'message' => $e->getMessage(),
        'sql_state' => $e->errorInfo[0] ?? '',
    ]);
    redirigir('/admin/setup-admin?error=db');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sietelsa_log('Setup admin runtime error', ['message' => $e->getMessage()]);
    redirigir('/admin/setup-admin?error=db');
}

redirigir('/admin/login?ok=admin_created');
