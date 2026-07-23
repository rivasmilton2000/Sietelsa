<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/login');
}

$identificador = trim((string) ($_POST['identificador'] ?? $_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$recaptchaToken = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
$remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
$rateKeyIp = $remoteIp !== '' ? $remoteIp : 'desconocido';
$rateIdentBase = substr($identificador, 0, 120);
$rateIdentInput = function_exists('mb_strtolower')
    ? mb_strtolower($rateIdentBase, 'UTF-8')
    : strtolower($rateIdentBase);
$rateKeyIdent = $rateIdentInput !== '' ? $rateIdentInput : '';
$rateStatus = sietelsa_rate_limit_guard('admin_login', $rateKeyIp, 5, 900);

if (!$rateStatus['allowed']) {
    header('Retry-After: ' . (string) $rateStatus['retry_after']);
    redirigir('/admin/login?error=rate_limit');
}

if ($rateKeyIdent !== '') {
    $rateStatusIdent = sietelsa_rate_limit_guard('admin_login_ident', $rateKeyIdent, 8, 900);
    if (!$rateStatusIdent['allowed']) {
        header('Retry-After: ' . (string) $rateStatusIdent['retry_after']);
        redirigir('/admin/login?error=rate_limit');
    }
}

$registrarFallo = static function () use ($rateKeyIp, $rateKeyIdent): void {
    sietelsa_rate_limit_register_fail('admin_login', $rateKeyIp, 900);
    if ($rateKeyIdent !== '') {
        sietelsa_rate_limit_register_fail('admin_login_ident', $rateKeyIdent, 900);
    }
};

if (!csrf_token_valido($csrfToken, 'admin_login')) {
    $registrarFallo();
    redirigir('/admin/login?error=csrf');
}

if ($identificador === '' || $password === '') {
    $registrarFallo();
    redirigir('/admin/login?error=campos');
}

if (
    strlen($identificador) > 120
    || preg_match('/[\x00-\x1F\x7F]/', $identificador) === 1
    || preg_match('/\s{2,}/', $identificador) === 1
) {
    $registrarFallo();
    redirigir('/admin/login?error=formato');
}

global $pdo;

if (!hay_admin_configurado()) {
    if (sietelsa_setup_admin_habilitado_en_request()) {
        redirigir('/admin/setup-admin');
    }

    redirigir('/admin/login?error=setup_required');
}

if (!recaptcha_configurada()) {
    redirigir('/admin/login?error=captcha_config');
}

if ($recaptchaToken === '') {
    $registrarFallo();
    redirigir('/admin/login?error=captcha');
}

if (!validar_recaptcha($recaptchaToken, $remoteIp)) {
    $registrarFallo();
    redirigir('/admin/login?error=captcha');
}

$sql = 'SELECT u.id, u.nombre, u.username, u.email, u.password_hash, u.must_change_password, u.activo, u.perfil_id, p.nombre AS perfil
        FROM usuarios u
        LEFT JOIN perfiles p ON p.id = u.perfil_id
        WHERE (u.email = :identificador_email OR u.username = :identificador_username)
        LIMIT 1';

$ejecutarConsultaUsuario = static function () use ($pdo, $sql, $identificador): array|false {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':identificador_email' => $identificador,
        ':identificador_username' => $identificador,
    ]);
    return $stmt->fetch();
};

try {
    $usuario = $ejecutarConsultaUsuario();
} catch (PDOException $e) {
    $sqlState = $e->errorInfo[0] ?? '';
    if ($sqlState === '42S02' || $sqlState === '42S22') {
        sietelsa_log('Login DB schema error', ['message' => $e->getMessage(), 'sql_state' => $sqlState]);
        redirigir('/admin/login?error=estructura');
    }

    sietelsa_log('Login DB error', ['message' => $e->getMessage(), 'sql_state' => $sqlState]);
    http_response_code(500);
    exit('Error interno del servidor.');
}

if (!$usuario || (int) $usuario['activo'] !== 1 || !password_verify($password, $usuario['password_hash'])) {
    $registrarFallo();
    redirigir('/admin/login?error=credenciales');
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
        sietelsa_log('Profile auto-recovery failed', ['message' => $e->getMessage()]);
    }
}

if (trim((string) ($usuario['perfil'] ?? '')) === '' || (int) ($usuario['perfil_id'] ?? 0) <= 0) {
    $registrarFallo();
    redirigir('/admin/login?error=perfil');
}

sietelsa_rate_limit_clear('admin_login', $rateKeyIp);
if ($rateKeyIdent !== '') {
    sietelsa_rate_limit_clear('admin_login_ident', $rateKeyIdent);
}

autenticar_usuario_en_sesion($usuario);
if ($pdo instanceof PDO) {
    bitacora_registrar_request_si_aplica($pdo, $usuario, '/admin/auth/login', 'POST');
}

if ((int) ($usuario['must_change_password'] ?? 0) === 1) {
    redirigir('/admin/primer-acceso/cambiar-clave');
}

redirigir(ruta_inicio_usuario_actual());
