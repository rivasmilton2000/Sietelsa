<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$usuarioActual = obtener_usuario_actual();
global $pdo;
if ($pdo instanceof PDO && is_array($usuarioActual)) {
    bitacora_registrar_request_si_aplica($pdo, $usuarioActual, '/admin/auth/logout', 'GET');
}

cerrar_sesion();
redirigir('/admin/login?ok=logout');
