<?php
declare(strict_types=1);

const RECAPTCHA_SITE_KEY_FALLBACK = '';
const RECAPTCHA_SECRET_KEY_FALLBACK = '';

function recaptcha_site_key(): string
{
    return recaptcha_config_value('RECAPTCHA_SITE_KEY', RECAPTCHA_SITE_KEY_FALLBACK);
}

function recaptcha_secret_key(): string
{
    return recaptcha_config_value('RECAPTCHA_SECRET_KEY', RECAPTCHA_SECRET_KEY_FALLBACK);
}

function recaptcha_config_value(string $key, string $fallback = ''): string
{
    $fromGetenv = getenv($key);
    if (is_string($fromGetenv) && trim($fromGetenv) !== '') {
        return trim($fromGetenv);
    }

    $fromEnv = $_ENV[$key] ?? '';
    if (is_string($fromEnv) && trim($fromEnv) !== '') {
        return trim($fromEnv);
    }

    $fromServer = $_SERVER[$key] ?? '';
    if (is_string($fromServer) && trim($fromServer) !== '') {
        return trim($fromServer);
    }

    return trim($fallback);
}

function recaptcha_configurada(): bool
{
    return recaptcha_site_key() !== '' && recaptcha_secret_key() !== '';
}

function validar_recaptcha(string $token, ?string $remoteIp = null): bool
{
    $secret = recaptcha_secret_key();
    $token = trim($token);

    if ($secret === '' || $token === '') {
        return false;
    }

    $postFields = [
        'secret' => $secret,
        'response' => $token,
    ];

    if ($remoteIp !== null && $remoteIp !== '') {
        $postFields['remoteip'] = $remoteIp;
    }

    $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
    $responseBody = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $curlResponse = curl_exec($ch);
            curl_close($ch);
            if (is_string($curlResponse)) {
                $responseBody = $curlResponse;
            }
        }
    }

    if ($responseBody === '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($postFields),
                'timeout' => 10,
            ],
        ]);

        $streamResponse = @file_get_contents($endpoint, false, $context);
        if (is_string($streamResponse)) {
            $responseBody = $streamResponse;
        }
    }

    if ($responseBody === '') {
        return false;
    }

    $data = json_decode($responseBody, true);
    if (!is_array($data)) {
        return false;
    }

    return (bool) ($data['success'] ?? false);
}
