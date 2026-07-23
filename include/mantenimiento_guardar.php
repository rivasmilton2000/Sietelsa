<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/maintenance.php';

function redirigir_mantenimiento_admin(string $estado, string $valor = ''): void
{
    $query = ['mantenimiento_error' => $estado];
    if ($valor !== '') {
        $query['mantenimiento_valor'] = $valor;
    }

    redirigir('/admin/dashboard?' . http_build_query($query));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_mantenimiento_admin('metodo');
}

require_login(['admin', 'gerente', 'tecnico', 'developer']);

$usuario = obtener_usuario_actual();
if (!usuario_puede_gestionar_mantenimiento($usuario)) {
    mostrar_pagina_error(403);
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'toggle_mantenimiento')) {
    redirigir_mantenimiento_admin('csrf');
}

$accion = strtolower(trim((string) ($_POST['accion'] ?? '')));
if (!in_array($accion, ['activar', 'desactivar'], true)) {
    redirigir_mantenimiento_admin('accion');
}

global $pdo;

if (!mantenimiento_password_configurada($pdo)) {
    redirigir_mantenimiento_admin('clave_config');
}

$passwordMantenimiento = trim((string) ($_POST['mantenimiento_password'] ?? ''));
if ($passwordMantenimiento === '') {
    redirigir_mantenimiento_admin('clave_requerida');
}

if (!mantenimiento_verificar_password($pdo, $passwordMantenimiento)) {
    redirigir_mantenimiento_admin('clave_invalida');
}

try {
    mantenimiento_actualizar_estado($pdo, $accion === 'activar');
} catch (Throwable $e) {
    redirigir_mantenimiento_admin('db');
}

try {
    mantenimiento_registrar_evento(
        $pdo,
        $usuario,
        $accion === 'activar' ? 'activar_mantenimiento' : 'desactivar_mantenimiento',
        $accion === 'activar' ? 'Modo mantenimiento activado desde dashboard.' : 'Modo mantenimiento desactivado desde dashboard.'
    );
} catch (Throwable $e) {
}

redirigir('/admin/dashboard?mantenimiento_ok=' . ($accion === 'activar' ? '1' : '0'));
