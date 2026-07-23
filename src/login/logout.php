<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}

if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('La solicitud no es válida.');
}

auth_logout();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . app_url('index.php'));
exit;
