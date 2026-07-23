<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function csrf_token(string $contexto = 'default'): string
{
    iniciar_sesion_segura();

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$contexto] = $token;

    return $token;
}

function csrf_token_valido(string $tokenRecibido, string $contexto = 'default'): bool
{
    iniciar_sesion_segura();

    if ($tokenRecibido === '') {
        return false;
    }

    $tokenSesion = (string) ($_SESSION['csrf_tokens'][$contexto] ?? '');
    if ($tokenSesion === '') {
        return false;
    }

    $esValido = hash_equals($tokenSesion, $tokenRecibido);
    if ($esValido) {
        unset($_SESSION['csrf_tokens'][$contexto]);
    }

    return $esValido;
}

function csrf_input(string $contexto = 'default'): string
{
    $token = csrf_token($contexto);
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" />';
}

function csrf_cookie_nombre(string $contexto): string
{
    return 'sietelsa_csrf_' . substr(sha1($contexto), 0, 12);
}

function csrf_cookie_token(string $contexto): string
{
    $nombreCookie = csrf_cookie_nombre($contexto);
    $token = trim((string) ($_COOKIE[$nombreCookie] ?? ''));

    if ($token === '' || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
    }

    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    setcookie($nombreCookie, $token, [
        'expires' => time() + 7200,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $token;
}

function csrf_cookie_token_valido(string $tokenRecibido, string $contexto): bool
{
    $nombreCookie = csrf_cookie_nombre($contexto);
    $tokenCookie = trim((string) ($_COOKIE[$nombreCookie] ?? ''));
    $tokenRecibido = trim($tokenRecibido);

    if ($tokenRecibido === '' || $tokenCookie === '') {
        return false;
    }

    $valido = hash_equals($tokenCookie, $tokenRecibido);
    if ($valido) {
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        setcookie($nombreCookie, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$nombreCookie]);
    }

    return $valido;
}
