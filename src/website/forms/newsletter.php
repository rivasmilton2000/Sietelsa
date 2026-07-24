<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/conexion.php';

header('Content-Type: text/plain; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !auth_validate_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('La solicitud expiró. Recargue la página.');
}
$email = trim((string) ($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    exit('Ingrese un correo válido.');
}
try {
    $statement = db_connection()->prepare(
        'INSERT INTO contact_messages (message_type, email, subject, message, ip_hash)
         VALUES ("newsletter", :email, "Suscripción al boletín", :message, :ip_hash)'
    );
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $statement->execute([
        'email' => mb_substr($email, 0, 190), 'message' => 'Solicitud de suscripción',
        'ip_hash' => $ip === '' ? null : hash('sha256', $ip),
    ]);
    echo 'OK';
} catch (Throwable $exception) {
    error_log('[SIETELSA] Error al guardar suscripción: ' . $exception->getMessage());
    http_response_code(500);
    echo 'No fue posible registrar la suscripción.';
}
