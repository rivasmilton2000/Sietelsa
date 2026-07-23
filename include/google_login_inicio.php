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

try {
    $state = bin2hex(random_bytes(24));
} catch (Throwable $e) {
    redirigir('/admin/login?error=google_auth');
}

$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_state_expires'] = time() + 600;

$authUrl = google_oauth_build_auth_url($state);
redirigir($authUrl);
