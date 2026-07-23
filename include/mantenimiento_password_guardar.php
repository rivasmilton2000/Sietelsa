<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/maintenance.php';

function redirigir_modulo_mantenimiento(array $query = []): void
{
    $queryLimpia = [];
    foreach ($query as $clave => $valor) {
        $clave = trim((string) $clave);
        if ($clave === '') {
            continue;
        }

        $queryLimpia[$clave] = (string) $valor;
    }

    $ruta = '/admin/mantenimiento';
    if (!empty($queryLimpia)) {
        $ruta .= '?' . http_build_query($queryLimpia);
    }

    redirigir($ruta);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'metodo']);
}

require_login(['admin', 'gerente', 'tecnico', 'developer']);

$usuario = obtener_usuario_actual();
if (!usuario_puede_configurar_password_mantenimiento($usuario)) {
    mostrar_pagina_error(403);
}

$accion = strtolower(trim((string) ($_POST['accion'] ?? '')));
if (!in_array($accion, ['desbloquear', 'actualizar_clave'], true)) {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'accion']);
}

global $pdo;

if ($accion === 'desbloquear') {
    $tokenAcceso = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!csrf_token_valido($tokenAcceso, 'mantenimiento_unlock')) {
        redirigir_modulo_mantenimiento(['mantenimiento_error' => 'csrf']);
    }

    if (!mantenimiento_password_configurada($pdo)) {
        $_SESSION['mantenimiento_unlock_until'] = time() + 900;
        try {
            mantenimiento_registrar_evento(
                $pdo,
                $usuario,
                'primera_configuracion_clave_mantenimiento',
                'Acceso habilitado para primera configuracion de clave.'
            );
        } catch (Throwable $e) {
        }
        redirigir_modulo_mantenimiento(['mantenimiento_ok' => 'setup']);
    }

    $password = trim((string) ($_POST['mantenimiento_password'] ?? ''));
    if ($password === '') {
        redirigir_modulo_mantenimiento(['mantenimiento_error' => 'clave_requerida']);
    }

    if (!mantenimiento_verificar_password($pdo, $password)) {
        redirigir_modulo_mantenimiento(['mantenimiento_error' => 'clave_invalida']);
    }

    $_SESSION['mantenimiento_unlock_until'] = time() + 900;
    try {
        mantenimiento_registrar_evento(
            $pdo,
            $usuario,
            'acceso_modulo_mantenimiento',
            'Acceso validado con clave de mantenimiento.'
        );
    } catch (Throwable $e) {
    }
    redirigir_modulo_mantenimiento(['mantenimiento_ok' => 'desbloqueado']);
}

$tokenActualizar = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($tokenActualizar, 'mantenimiento_update_password')) {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'csrf']);
}

$actual = trim((string) ($_POST['password_actual'] ?? ''));
$nueva = trim((string) ($_POST['password_nueva'] ?? ''));
$confirmar = trim((string) ($_POST['password_confirmar'] ?? ''));

if ($nueva === '' || $confirmar === '') {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'nueva_requerida']);
}

if (strlen($nueva) < 8) {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'nueva_corta']);
}

if ($nueva !== $confirmar) {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'nueva_diferente']);
}

if (mantenimiento_password_configurada($pdo)) {
    if ($actual === '') {
        redirigir_modulo_mantenimiento(['mantenimiento_error' => 'actual_requerida']);
    }

    if (!mantenimiento_verificar_password($pdo, $actual)) {
        redirigir_modulo_mantenimiento(['mantenimiento_error' => 'actual_invalida']);
    }
}

try {
    mantenimiento_actualizar_password($pdo, $nueva);
} catch (Throwable $e) {
    redirigir_modulo_mantenimiento(['mantenimiento_error' => 'db']);
}

$_SESSION['mantenimiento_unlock_until'] = time() + 900;
try {
    mantenimiento_registrar_evento(
        $pdo,
        $usuario,
        'actualizar_clave_mantenimiento',
        'Clave de mantenimiento actualizada correctamente.'
    );
} catch (Throwable $e) {
}
redirigir_modulo_mantenimiento(['mantenimiento_ok' => 'clave_actualizada']);
