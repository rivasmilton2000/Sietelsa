<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

require_once __DIR__ . '/../includes/conexion.php';

$name = sietelsa_env('SIETELSA_ADMIN_NAME');
$username = sietelsa_env('SIETELSA_ADMIN_USERNAME');
$email = sietelsa_env('SIETELSA_ADMIN_EMAIL');
$password = sietelsa_env('SIETELSA_ADMIN_PASSWORD');

if (
    $name === null
    || $username === null
    || $email === null
    || $password === null
) {
    fwrite(
        STDERR,
        "Defina SIETELSA_ADMIN_NAME, SIETELSA_ADMIN_USERNAME, "
        . "SIETELSA_ADMIN_EMAIL y SIETELSA_ADMIN_PASSWORD.\n"
    );
    exit(1);
}

if (
    !preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($password) < 12
) {
    fwrite(STDERR, "Los datos del administrador no cumplen los requisitos de seguridad.\n");
    exit(1);
}

try {
    $statement = db_connection()->prepare(
        'INSERT INTO usuarios
            (nombre, username, email, password_hash, rol, activo)
         VALUES
            (:nombre, :username, :email, :password_hash, :rol, :activo)'
    );
    $statement->execute([
        'nombre' => $name,
        'username' => $username,
        'email' => strtolower($email),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'rol' => 'admin',
        'activo' => 1,
    ]);
} catch (PDOException $exception) {
    error_log('[SIETELSA] No fue posible crear el administrador: ' . $exception->getMessage());
    fwrite(STDERR, "No fue posible crear el administrador. Revise el log técnico.\n");
    exit(1);
}

fwrite(STDOUT, "Administrador creado correctamente.\n");
