<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/conexion.php';

header('Content-Type: text/plain; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !auth_validate_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('La solicitud expiró. Recargue la página.');
}
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $message === '') {
    http_response_code(422);
    exit('Revise los campos del formulario.');
}
try {
    $statement = db_connection()->prepare(
        'INSERT INTO contact_messages (message_type, name, email, subject, message, ip_hash)
         VALUES ("contact", :name, :email, :subject, :message, :ip_hash)'
    );
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $statement->execute([
        'name' => mb_substr($name, 0, 150), 'email' => mb_substr($email, 0, 190),
        'subject' => mb_substr($subject, 0, 200), 'message' => mb_substr($message, 0, 5000),
        'ip_hash' => $ip === '' ? null : hash('sha256', $ip),
    ]);
    echo 'OK';
} catch (Throwable $exception) {
    error_log('[SIETELSA] Error al guardar contacto: ' . $exception->getMessage());
    http_response_code(500);
    echo 'No fue posible enviar el mensaje.';
}
