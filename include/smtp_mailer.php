<?php
declare(strict_types=1);

function smtp_config_value(string $key, string $fallback = ''): string
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

function smtp_correo_destino_contacto(): string
{
    return smtp_config_value('SIETELSA_CONTACTO_DESTINO', 'sietelsa60@gmail.com');
}

function smtp_config_bool(string $key, bool $fallback = false): bool
{
    $default = $fallback ? '1' : '0';
    $raw = strtolower(trim(smtp_config_value($key, $default)));
    return in_array($raw, ['1', 'true', 'yes', 'si', 'on'], true);
}

function smtp_configuracion(): array
{
    $username = smtp_config_value('SIETELSA_SMTP_USERNAME', 'sietelsa60@gmail.com');

    $passwordFallback = '';

    return [
        'host' => smtp_config_value('SIETELSA_SMTP_HOST', 'smtp.gmail.com'),
        'port' => (int) smtp_config_value('SIETELSA_SMTP_PORT', '587'),
        'secure' => strtolower(smtp_config_value('SIETELSA_SMTP_SECURE', 'tls')),
        'username' => $username,
        'password' => str_replace(' ', '', smtp_config_value('SIETELSA_SMTP_PASSWORD', $passwordFallback)),
        'from_email' => smtp_config_value('SIETELSA_SMTP_FROM_EMAIL', $username),
        'from_name' => smtp_config_value('SIETELSA_SMTP_FROM_NAME', 'sietelsa'),
        'verify_peer' => smtp_config_bool('SIETELSA_SMTP_VERIFY_PEER', false),
    ];
}

function smtp_limpiar_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function smtp_codificar_header(string $value): string
{
    $value = smtp_limpiar_header($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_normalizar_cuerpo(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    $encoded = [];
    foreach ($lines as $line) {
        $line = rtrim($line, "\r\n");
        if (strpos($line, '.') === 0) {
            $line = '.' . $line;
        }
        $encoded[] = $line;
    }

    return implode("\r\n", $encoded);
}

function smtp_leer_respuesta($socket): string
{
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }

    return trim($response);
}

function smtp_esperar_codigo($socket, array $expectedCodes, string &$error): bool
{
    $response = smtp_leer_respuesta($socket);
    if ($response === '') {
        $error = 'Sin respuesta del servidor SMTP.';
        return false;
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        $error = 'SMTP respondio ' . $response;
        return false;
    }

    return true;
}

function smtp_comando($socket, string $command, array $expectedCodes, string &$error): bool
{
    $sent = fwrite($socket, $command . "\r\n");
    if ($sent === false) {
        $error = 'No se pudo enviar comando SMTP.';
        return false;
    }

    return smtp_esperar_codigo($socket, $expectedCodes, $error);
}

function smtp_enviar_correo(array $destinatarios, string $subject, string $body, array $options = [], ?string &$error = null): bool
{
    $error = null;
    $config = smtp_configuracion();

    $host = trim((string) ($config['host'] ?? ''));
    $port = (int) ($config['port'] ?? 0);
    $secure = (string) ($config['secure'] ?? 'tls');
    $username = trim((string) ($config['username'] ?? ''));
    $password = (string) ($config['password'] ?? '');
    $fromEmail = trim((string) ($config['from_email'] ?? ''));
    $fromName = trim((string) ($options['from_name'] ?? $config['from_name'] ?? 'Sietelsa Web'));
    $replyTo = trim((string) ($options['reply_to'] ?? ''));
    $verifyPeer = (bool) ($config['verify_peer'] ?? false);

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        $error = 'Configuracion SMTP incompleta. Define SIETELSA_SMTP_PASSWORD y credenciales SMTP.';
        return false;
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo remitente SMTP invalido.';
        return false;
    }

    $to = [];
    foreach ($destinatarios as $email) {
        $email = trim((string) $email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $to[] = $email;
        }
    }

    if (empty($to)) {
        $error = 'No hay destinatarios validos.';
        return false;
    }

    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = '';
    }

    $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => $verifyPeer,
            'verify_peer_name' => $verifyPeer,
            'allow_self_signed' => !$verifyPeer,
        ],
    ]);

    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if ($socket === false) {
        $error = 'No se pudo conectar al servidor SMTP: ' . $errstr . ' (' . $errno . ').';
        return false;
    }

    stream_set_timeout($socket, 20);
    $smtpError = '';
    $smtpQuitError = '';

    try {
        if (!smtp_esperar_codigo($socket, [220], $smtpError)) {
            $error = $smtpError;
            return false;
        }

        $serverName = preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        if (!smtp_comando($socket, 'EHLO ' . $serverName, [250], $smtpError)) {
            $error = $smtpError;
            return false;
        }

        if ($secure === 'tls') {
            if (!smtp_comando($socket, 'STARTTLS', [220], $smtpError)) {
                $error = $smtpError;
                return false;
            }

            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                $error = 'No se pudo activar TLS en la conexion SMTP.';
                return false;
            }

            if (!smtp_comando($socket, 'EHLO ' . $serverName, [250], $smtpError)) {
                $error = $smtpError;
                return false;
            }
        }

        if (!smtp_comando($socket, 'AUTH LOGIN', [334], $smtpError)) {
            $error = $smtpError;
            return false;
        }
        if (!smtp_comando($socket, base64_encode($username), [334], $smtpError)) {
            $error = $smtpError;
            return false;
        }
        if (!smtp_comando($socket, base64_encode($password), [235], $smtpError)) {
            $error = $smtpError;
            return false;
        }

        if (!smtp_comando($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $smtpError)) {
            $error = $smtpError;
            return false;
        }

        foreach ($to as $destinatario) {
            if (!smtp_comando($socket, 'RCPT TO:<' . $destinatario . '>', [250, 251], $smtpError)) {
                $error = $smtpError;
                return false;
            }
        }

        if (!smtp_comando($socket, 'DATA', [354], $smtpError)) {
            $error = $smtpError;
            return false;
        }

        $subject = smtp_codificar_header($subject);
        $fromLine = smtp_codificar_header($fromName) . ' <' . $fromEmail . '>';
        $toLine = implode(', ', $to);
        $dateLine = date('r');
        $messageIdDomain = strpos($fromEmail, '@') !== false ? substr($fromEmail, strpos($fromEmail, '@') + 1) : 'localhost';
        $messageId = '<' . bin2hex(random_bytes(8)) . '@' . preg_replace('/[^a-zA-Z0-9\.\-]/', '', $messageIdDomain) . '>';

        $bodyHtml = trim((string) ($options['body_html'] ?? ''));
        $inlineAttachments = [];
        $inlineRaw = $options['inline_attachments'] ?? [];
        if (is_array($inlineRaw)) {
            foreach ($inlineRaw as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $cid = preg_replace('/[^a-zA-Z0-9_.\-@]/', '', trim((string) ($item['cid'] ?? '')));
                $mime = strtolower(trim((string) ($item['mime'] ?? '')));
                $filename = smtp_limpiar_header(trim((string) ($item['filename'] ?? 'inline.bin')));
                $content = $item['content'] ?? null;

                if ($cid === '' || $mime === '' || !is_string($content) || $content === '') {
                    continue;
                }
                if (preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mime) !== 1) {
                    continue;
                }
                if ($filename === '') {
                    $filename = 'inline.bin';
                }

                $inlineAttachments[] = [
                    'cid' => $cid,
                    'mime' => $mime,
                    'filename' => $filename,
                    'content' => $content,
                ];
            }
        }
        $headers = [
            'Date: ' . $dateLine,
            'Message-ID: ' . $messageId,
            'From: ' . $fromLine,
            'To: ' . $toLine,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
        ];

        $messageBody = '';
        if ($bodyHtml !== '' && !empty($inlineAttachments)) {
            $relatedBoundary = '=_Sietelsa_rel_' . bin2hex(random_bytes(8));
            $altBoundary = '=_Sietelsa_alt_' . bin2hex(random_bytes(8));
            $headers[] = 'Content-Type: multipart/related; boundary="' . $relatedBoundary . '"';
            $headers[] = 'Content-Transfer-Encoding: 8bit';

            $messageBody = '--' . $relatedBoundary . "\r\n"
                . 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . "\r\n\r\n"
                . '--' . $altBoundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . smtp_normalizar_cuerpo($body) . "\r\n\r\n"
                . '--' . $altBoundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . smtp_normalizar_cuerpo($bodyHtml) . "\r\n\r\n"
                . '--' . $altBoundary . "--\r\n";

            foreach ($inlineAttachments as $attachment) {
                $messageBody .= '--' . $relatedBoundary . "\r\n"
                    . 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['filename'] . '"' . "\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . 'Content-ID: <' . $attachment['cid'] . ">\r\n"
                    . 'Content-Disposition: inline; filename="' . $attachment['filename'] . '"' . "\r\n\r\n"
                    . rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n")) . "\r\n";
            }

            $messageBody .= '--' . $relatedBoundary . '--';
        } elseif ($bodyHtml !== '') {
            $boundary = '=_Sietelsa_' . bin2hex(random_bytes(8));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $headers[] = 'Content-Transfer-Encoding: 8bit';

            $messageBody = '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . smtp_normalizar_cuerpo($body) . "\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . smtp_normalizar_cuerpo($bodyHtml) . "\r\n\r\n"
                . '--' . $boundary . '--';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $messageBody = smtp_normalizar_cuerpo($body);
        }

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . smtp_limpiar_header($replyTo);
        }

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $messageBody . "\r\n.";
        $sentPayload = fwrite($socket, $payload . "\r\n");
        if ($sentPayload === false) {
            $error = 'No se pudo enviar el contenido del correo.';
            return false;
        }

        if (!smtp_esperar_codigo($socket, [250], $smtpError)) {
            $error = $smtpError;
            return false;
        }

        smtp_comando($socket, 'QUIT', [221], $smtpQuitError);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return false;
    } finally {
        fclose($socket);
    }

    return true;
}
