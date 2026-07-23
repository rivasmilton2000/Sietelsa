<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/maintenance.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limit.php';

function contacto_longitud(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function contacto_substring(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($value, $start, $length, 'UTF-8');
    }

    return substr($value, $start, $length);
}

function redirigir_contacto(string $estado): void
{
    header('Location: /contacto/' . rawurlencode($estado));
    exit;
}

function contacto_telefono_valido(string $telefono): bool
{
    return preg_match('/\A[0-9+\-\s]{1,15}\z/', $telefono) === 1;
}

function contacto_asunto_valido(string $asunto): bool
{
    return preg_match('/\A[\p{L}\p{N} .,_\-\/()#@&+]{1,120}\z/u', $asunto) === 1;
}

function contacto_mensaje_valido(string $mensaje): bool
{
    // Permite texto libre unicode y evita solo controles no imprimibles.
    return preg_match('/\A[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+\z/u', $mensaje) === 1;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_contacto('metodo');
}

global $pdo;

try {
    if (mantenimiento_esta_activo($pdo)) {
        $usuarioActual = obtener_usuario_actual();
        if (!usuario_puede_bypass_mantenimiento($usuarioActual)) {
            http_response_code(503);
            header('Retry-After: 600');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            require __DIR__ . '/../maintenance.php';
            exit;
        }
    }
} catch (Throwable $e) {
}

try {
    asegurar_tabla_contacto_mensajes($pdo);
} catch (Throwable $e) {
    redirigir_contacto('db');
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));
$asunto = trim((string) ($_POST['asunto'] ?? ''));
$mensaje = trim((string) ($_POST['mensaje'] ?? ''));
$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));

if (!csrf_cookie_token_valido($csrfToken, 'contacto_publico')) {
    redirigir_contacto('csrf');
}

if ($nombre === '' || $email === '' || $telefono === '' || $asunto === '' || $mensaje === '') {
    redirigir_contacto('campos');
}

if (contacto_longitud($nombre) > 120) {
    redirigir_contacto('nombre');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigir_contacto('email');
}

if (!contacto_telefono_valido($telefono)) {
    redirigir_contacto('telefono');
}

if (!contacto_asunto_valido($asunto)) {
    redirigir_contacto('asunto');
}

if (contacto_longitud($mensaje) < 5 || contacto_longitud($mensaje) > 5000) {
    redirigir_contacto('mensaje');
}

if (!contacto_mensaje_valido($mensaje)) {
    redirigir_contacto('mensaje');
}

$ipOrigen = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
if (contacto_longitud($ipOrigen) > 45) {
    $ipOrigen = contacto_substring($ipOrigen, 0, 45);
}

$rateKeyContacto = $ipOrigen !== '' ? $ipOrigen : 'anonimo';
$rateStatusContacto = sietelsa_rate_limit_guard('contacto_form', $rateKeyContacto, 6, 900);
if (!$rateStatusContacto['allowed']) {
    header('Retry-After: ' . (string) $rateStatusContacto['retry_after']);
    redirigir_contacto('rate_limit');
}
sietelsa_rate_limit_register_fail('contacto_form', $rateKeyContacto, 900);

$mensajeId = 0;

try {
    $stmtInsert = $pdo->prepare(
        'INSERT INTO contacto_mensajes (nombre, email, telefono, asunto, mensaje, estado, notificado, ip_origen)
         VALUES (:nombre, :email, :telefono, :asunto, :mensaje, :estado, 0, :ip_origen)'
    );
    $stmtInsert->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':telefono' => $telefono,
        ':asunto' => $asunto,
        ':mensaje' => $mensaje,
        ':estado' => 'nuevo',
        ':ip_origen' => $ipOrigen !== '' ? $ipOrigen : null,
    ]);
    $mensajeId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    redirigir_contacto('db');
} catch (Throwable $e) {
    redirigir_contacto('general');
}

$destino = smtp_correo_destino_contacto();
$asuntoNotificacion = 'Nuevo contacto web - ' . $asunto;
$cuerpoNotificacion = "Se recibio un nuevo mensaje desde el formulario de contacto.\r\n\r\n"
    . 'Nombre: ' . $nombre . "\r\n"
    . 'Correo: ' . $email . "\r\n"
    . 'Telefono: ' . $telefono . "\r\n"
    . 'Asunto: ' . $asunto . "\r\n"
    . 'IP origen: ' . ($ipOrigen !== '' ? $ipOrigen : 'No disponible') . "\r\n\r\n"
    . "Mensaje:\r\n" . $mensaje . "\r\n";

$errorNotificacion = '';
$notificado = smtp_enviar_correo(
    [$destino],
    $asuntoNotificacion,
    $cuerpoNotificacion,
    [
        'reply_to' => $email,
        'from_name' => 'Formulario Web Sietelsa',
    ],
    $errorNotificacion
);

try {
    if ($notificado) {
        $stmtUpdate = $pdo->prepare(
            'UPDATE contacto_mensajes
             SET notificado = 1, error_notificacion = NULL
             WHERE id = :id
             LIMIT 1'
        );
        $stmtUpdate->execute([':id' => $mensajeId]);
        redirigir_contacto('ok');
    }

    $errorNotificacion = trim($errorNotificacion);
    if ($errorNotificacion !== '' && contacto_longitud($errorNotificacion) > 255) {
        $errorNotificacion = contacto_substring($errorNotificacion, 0, 255);
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE contacto_mensajes
         SET notificado = 0, error_notificacion = :error
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':id' => $mensajeId,
        ':error' => $errorNotificacion !== '' ? $errorNotificacion : 'No fue posible enviar notificacion por SMTP.',
    ]);
} catch (Throwable $e) {
    // Ignora errores secundarios para no perder el envio principal.
}

redirigir_contacto('ok_pendiente');
