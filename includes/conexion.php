<?php

declare(strict_types=1);

/**
 * Obtiene una variable de entorno sin tratar la cadena "0" como vacía.
 */
function sietelsa_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
}

/**
 * Devuelve una única conexión PDO reutilizable durante la petición.
 *
 * Las variables SIETELSA_DB_* permiten reemplazar la configuración local
 * sin publicar credenciales en el código o en el navegador.
 */
function db_connection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = sietelsa_env('SIETELSA_DB_HOST', '127.0.0.1');
    $port = sietelsa_env('SIETELSA_DB_PORT', '3306');
    $database = sietelsa_env('SIETELSA_DB_NAME', 'db_sietelsa');
    $username = sietelsa_env('SIETELSA_DB_USER', 'root');
    $password = sietelsa_env('SIETELSA_DB_PASSWORD', '');

    if (!ctype_digit((string) $port) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $database)) {
        error_log('[SIETELSA] Configuración de base de datos inválida.');
        throw new RuntimeException('No fue posible conectar con el sistema.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    try {
        $connection = new PDO(
            $dsn,
            (string) $username,
            (string) $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );
    } catch (PDOException $exception) {
        error_log(
            sprintf(
                '[SIETELSA] Error de conexión PDO (%s): %s',
                $exception->getCode(),
                $exception->getMessage()
            )
        );

        throw new RuntimeException('No fue posible conectar con el sistema.');
    }

    return $connection;
}
