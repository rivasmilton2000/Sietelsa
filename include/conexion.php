<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'sietelsa';
$DB_USER = getenv('DB_USER') ?: 'sietelsa';
$DB_PASS = getenv('DB_PASS');
if ($DB_PASS === false) {
    $DB_PASS = '';
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 3,
];

/** @var list<string> $candidateDsns */
$candidateDsns = [];
$socketPaths = [
    '/run/mysqld/mysqld.sock',
    '/var/run/mysqld/mysqld.sock',
];

if (in_array($DB_HOST, ['localhost', '127.0.0.1'], true)) {
    foreach ($socketPaths as $socketPath) {
        if (is_string($socketPath) && is_file($socketPath)) {
            $candidateDsns[] = "mysql:unix_socket={$socketPath};dbname={$DB_NAME};charset=utf8mb4";
            break;
        }
    }
}

/** @var list<string> $hosts */
$hosts = [$DB_HOST];
if ($DB_HOST === 'localhost') {
    $hosts[] = '127.0.0.1';
} elseif ($DB_HOST === '127.0.0.1') {
    $hosts[] = 'localhost';
}
$hosts = array_values(array_unique($hosts));
foreach ($hosts as $host) {
    $candidateDsns[] = "mysql:host={$host};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
}

$pdo = null;
$lastError = null;

foreach ($candidateDsns as $dsn) {
    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        $pdo->exec("SET time_zone = '-06:00'");
        break;
    } catch (PDOException $e) {
        $lastError = $e;

        if ((string) $e->getCode() === '1049') {
            // Si la BD no existe, intenta crearla con las mismas credenciales.
            foreach ($hosts as $hostForCreate) {
                try {
                    $pdoServer = new PDO("mysql:host={$hostForCreate};port={$DB_PORT};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
                    $pdoServer->exec("CREATE DATABASE `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $dsnCreate = "mysql:host={$hostForCreate};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
                    $pdo = new PDO($dsnCreate, $DB_USER, $DB_PASS, $options);
                    $pdo->exec("SET time_zone = '-06:00'");
                    break 2;
                } catch (PDOException $e2) {
                    $lastError = $e2;
                    continue;
                }
            }
        }
    }
}

if (!$pdo instanceof PDO) {
    http_response_code(500);
    if ($lastError instanceof Throwable) {
        sietelsa_log('Database connection failed', [
            'code' => $lastError->getCode(),
            'message' => $lastError->getMessage(),
        ]);
    }

    exit('Error de conexion a la base de datos.');
}
