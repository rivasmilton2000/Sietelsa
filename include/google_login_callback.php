<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google_oauth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    redirigir('/admin/login');
}

iniciar_sesion_segura();

if (usuario_autenticado()) {
    redirigir(ruta_inicio_usuario_actual());
}

if (!google_oauth_enabled()) {
    redirigir('/admin/login?error=google_config');
}

$stateSession = trim((string) ($_SESSION['google_oauth_state'] ?? ''));
$stateExpire = (int) ($_SESSION['google_oauth_state_expires'] ?? 0);
$stateIncoming = trim((string) ($_GET['state'] ?? ''));

unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_expires']);

if (
    $stateSession === ''
    || $stateIncoming === ''
    || !hash_equals($stateSession, $stateIncoming)
    || $stateExpire <= time()
) {
    redirigir('/admin/login?error=google_state');
}

if (isset($_GET['error'])) {
    redirigir('/admin/login?error=google_auth');
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    redirigir('/admin/login?error=google_auth');
}

$tokenData = google_oauth_exchange_code($code);
if (!is_array($tokenData)) {
    redirigir('/admin/login?error=google_auth');
}

$accessToken = trim((string) ($tokenData['access_token'] ?? ''));
$userInfo = google_oauth_fetch_userinfo($accessToken);
if (!is_array($userInfo)) {
    redirigir('/admin/login?error=google_auth');
}

$email = strtolower(trim((string) ($userInfo['email'] ?? '')));
$emailVerified = (bool) ($userInfo['email_verified'] ?? false);
if ($email === '' || !$emailVerified) {
    redirigir('/admin/login?error=google_email');
}

global $pdo;

$stmt = $pdo->prepare(
    'SELECT u.id,
            u.nombre,
            u.username,
            u.email,
            u.must_change_password,
            u.activo,
            u.perfil_id,
            p.nombre AS perfil
     FROM usuarios u
     LEFT JOIN perfiles p ON p.id = u.perfil_id
     WHERE LOWER(u.email) = :email
     LIMIT 1'
);
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch();

if (!is_array($usuario) || (int) ($usuario['activo'] ?? 0) !== 1) {
    redirigir('/admin/login?error=google_no_user');
}

$perfilNombre = trim((string) ($usuario['perfil'] ?? ''));
$perfilId = (int) ($usuario['perfil_id'] ?? 0);

if ($perfilNombre === '' || $perfilId <= 0) {
    try {
        dte_asegurar_rbac_basico($pdo);
        $perfilDte = dte_buscar_perfil($pdo, 'dte', true);
        if ($perfilDte === null) {
            $perfilDte = dte_buscar_perfil($pdo, 'dte', false);
        }

        if (is_array($perfilDte) && (int) ($perfilDte['id'] ?? 0) > 0) {
            $nuevoPerfilId = (int) ($perfilDte['id'] ?? 0);
            $stmtFixPerfil = $pdo->prepare(
                'UPDATE usuarios
                 SET perfil_id = :perfil_id
                 WHERE id = :usuario_id
                 LIMIT 1'
            );
            $stmtFixPerfil->execute([
                ':perfil_id' => $nuevoPerfilId,
                ':usuario_id' => (int) ($usuario['id'] ?? 0),
            ]);

            $usuario['perfil_id'] = $nuevoPerfilId;
            $usuario['perfil'] = (string) ($perfilDte['nombre'] ?? 'dte');
        }
    } catch (Throwable $e) {
        sietelsa_log('Google profile auto-recovery failed', ['message' => $e->getMessage()]);
    }
}

if (trim((string) ($usuario['perfil'] ?? '')) === '' || (int) ($usuario['perfil_id'] ?? 0) <= 0) {
    redirigir('/admin/login?error=google_profile');
}

autenticar_usuario_en_sesion($usuario);
if ($pdo instanceof PDO) {
    bitacora_registrar_request_si_aplica($pdo, $usuario, '/admin/auth/google/callback', 'GET');
}

if ((int) ($usuario['must_change_password'] ?? 0) === 1) {
    redirigir('/admin/primer-acceso/cambiar-clave');
}

redirigir(ruta_inicio_usuario_actual());
