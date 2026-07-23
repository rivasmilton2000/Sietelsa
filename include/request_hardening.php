<?php
declare(strict_types=1);

function sietelsa_request_hardening_env_int(string $key, int $default, int $min, int $max): int
{
    $raw = trim((string) getenv($key));
    if ($raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
        return $default;
    }

    $value = (int) $raw;
    if ($value < $min) {
        $value = $min;
    }
    if ($value > $max) {
        $value = $max;
    }

    return $value;
}

function sietelsa_request_hardening_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function sietelsa_request_hardening_extract_host(string $source): string
{
    $source = trim($source);
    if ($source === '') {
        return '';
    }

    if (strpos($source, '://') === false && strpos($source, '//') !== 0) {
        $source = '//' . $source;
    }

    $parts = parse_url($source);
    $host = strtolower(trim((string) ($parts['host'] ?? '')));

    return $host;
}

function sietelsa_request_hardening_same_origin_header(string $headerValue, string $currentHost): bool
{
    $headerValue = trim($headerValue);
    if ($headerValue === '') {
        return true;
    }

    $host = sietelsa_request_hardening_extract_host($headerValue);
    if ($host === '') {
        return false;
    }

    return hash_equals($currentHost, $host);
}

function sietelsa_request_hardening_abort(int $statusCode = 400): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    exit('Solicitud rechazada.');
}

function sietelsa_request_hardening_is_mutation(string $method): bool
{
    return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

function sietelsa_request_hardening_path(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $path = '/' . ltrim($path, '/');

    return $path;
}

function sietelsa_enforce_request_hardening(): void
{
    static $applied = false;
    if ($applied || PHP_SAPI === 'cli') {
        return;
    }
    $applied = true;

    if (!headers_sent()) {
        header_remove('X-Powered-By');
    }

    $method = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $allowedMethods = ['GET', 'POST', 'HEAD', 'OPTIONS'];
    if (!in_array($method, $allowedMethods, true)) {
        sietelsa_request_hardening_abort(405);
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

    if (strlen($uri) > 4096 || strlen($query) > 4096) {
        sietelsa_request_hardening_abort(414);
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $uri) === 1 || preg_match('/[\x00-\x1F\x7F]/', $query) === 1) {
        sietelsa_request_hardening_abort(400);
    }

    $maxBodyBytes = sietelsa_request_hardening_env_int('SIETELSA_MAX_REQUEST_BYTES', 33554432, 1048576, 134217728);
    $contentLengthRaw = trim((string) ($_SERVER['CONTENT_LENGTH'] ?? ''));
    if ($contentLengthRaw !== '' && preg_match('/^\d+$/', $contentLengthRaw) === 1) {
        $contentLength = (int) $contentLengthRaw;
        if ($contentLength > $maxBodyBytes) {
            sietelsa_request_hardening_abort(413);
        }
    }

    if (sietelsa_request_hardening_is_mutation($method)) {
        $path = sietelsa_request_hardening_path();
        $requiresOriginCheck = str_starts_with($path, '/admin') || $path === '/contacto/enviar';

        if ($requiresOriginCheck) {
            $currentHost = sietelsa_request_hardening_extract_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($currentHost === '') {
                sietelsa_request_hardening_abort(400);
            }

            $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
            $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

            if ($origin !== '' && !sietelsa_request_hardening_same_origin_header($origin, $currentHost)) {
                sietelsa_request_hardening_abort(403);
            }
            if ($referer !== '' && !sietelsa_request_hardening_same_origin_header($referer, $currentHost)) {
                sietelsa_request_hardening_abort(403);
            }
        }
    }

    if (!headers_sent()) {
        if (sietelsa_request_hardening_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-site');
        header('X-Permitted-Cross-Domain-Policies: none');

        $path = sietelsa_request_hardening_path();
        if (str_starts_with($path, '/admin')) {
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
    }
}
