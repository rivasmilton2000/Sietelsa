<?php
declare(strict_types=1);

function google_oauth_client_id(): string
{
    return trim(sietelsa_env('GOOGLE_OAUTH_CLIENT_ID', ''));
}

function google_oauth_client_secret(): string
{
    return trim(sietelsa_env('GOOGLE_OAUTH_CLIENT_SECRET', ''));
}

function google_oauth_redirect_uri(): string
{
    $explicit = trim(sietelsa_env('GOOGLE_OAUTH_REDIRECT_URI', ''));
    if ($explicit !== '') {
        return $explicit;
    }

    $appUrl = rtrim(trim(sietelsa_env('APP_URL', '')), '/');
    if ($appUrl !== '') {
        return $appUrl . '/admin/auth/google/callback';
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $https . '://' . $host . '/admin/auth/google/callback';
}

function google_oauth_enabled(): bool
{
    return google_oauth_client_id() !== '' && google_oauth_client_secret() !== '';
}

function google_oauth_build_auth_url(string $state): string
{
    $params = [
        'client_id' => google_oauth_client_id(),
        'redirect_uri' => google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_oauth_http_request(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $headerLines = ['Accept: application/json'];
    foreach ($headers as $header) {
        $header = trim((string) $header);
        if ($header !== '') {
            $headerLines[] = $header;
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $statusCode = 0;
    $responseHeaders = $http_response_header ?? [];
    if (!empty($responseHeaders) && preg_match('/HTTP\/\S+\s+(\d{3})/', (string) $responseHeaders[0], $matches) === 1) {
        $statusCode = (int) $matches[1];
    }

    return [
        'status' => $statusCode,
        'body' => is_string($raw) ? $raw : '',
        'headers' => is_array($responseHeaders) ? $responseHeaders : [],
    ];
}

function google_oauth_exchange_code(string $code): ?array
{
    $payload = http_build_query([
        'code' => $code,
        'client_id' => google_oauth_client_id(),
        'client_secret' => google_oauth_client_secret(),
        'redirect_uri' => google_oauth_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);

    $response = google_oauth_http_request(
        'https://oauth2.googleapis.com/token',
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        $payload
    );

    if ((int) ($response['status'] ?? 0) < 200 || (int) ($response['status'] ?? 0) >= 300) {
        return null;
    }

    $data = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($data) || trim((string) ($data['access_token'] ?? '')) === '') {
        return null;
    }

    return $data;
}

function google_oauth_fetch_userinfo(string $accessToken): ?array
{
    $accessToken = trim($accessToken);
    if ($accessToken === '') {
        return null;
    }

    $response = google_oauth_http_request(
        'https://www.googleapis.com/oauth2/v3/userinfo',
        'GET',
        ['Authorization: Bearer ' . $accessToken]
    );

    if ((int) ($response['status'] ?? 0) < 200 || (int) ($response['status'] ?? 0) >= 300) {
        return null;
    }

    $data = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($data)) {
        return null;
    }

    return $data;
}
