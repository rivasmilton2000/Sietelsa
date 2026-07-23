<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/csrf.php';

function contacto_admin_longitud(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function redirigir_contacto_admin(string $estado, int $mensajeId = 0): void
{
    $query = $estado === 'respondido'
        ? 'ok=' . urlencode($estado)
        : 'error=' . urlencode($estado);

    if ($mensajeId > 0) {
        $query .= '&id=' . $mensajeId;
    }

    redirigir('/admin/contacto?' . $query);
}

function contacto_logo_url(): string
{
    $logoEnv = trim((string) smtp_config_value('SIETELSA_EMAIL_LOGO_URL', ''));
    if ($logoEnv !== '' && filter_var($logoEnv, FILTER_VALIDATE_URL)) {
        return $logoEnv;
    }

    $baseUrl = trim((string) smtp_config_value('SIETELSA_BASE_URL', ''));
    if ($baseUrl === '') {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '') {
            return '';
        }
        $baseUrl = 'https://' . $host;
    }

    $baseUrl = rtrim($baseUrl, '/');
    $logoUrl = $baseUrl . '/assets/img/logos/logoSietelsa.png';
    if (!filter_var($logoUrl, FILTER_VALIDATE_URL)) {
        return '';
    }

    return $logoUrl;
}

function contacto_logo_inline_adjunto(): array
{
    $logoPath = realpath(__DIR__ . '/../assets/img/logos/logoSietelsa.png');
    if (!is_string($logoPath) || $logoPath === '' || !is_file($logoPath) || !is_readable($logoPath)) {
        return [];
    }

    $logoRaw = @file_get_contents($logoPath);
    if (!is_string($logoRaw) || $logoRaw === '') {
        return [];
    }

    return [
        'cid' => 'logo_sietelsa',
        'mime' => 'image/png',
        'filename' => 'logoSietelsa.png',
        'content' => $logoRaw,
    ];
}

function contacto_html_respuesta(string $nombreDestino, string $respuesta, string $asuntoOriginal, string $logoSrc = ''): string
{
    $saludo = $nombreDestino !== '' ? 'Hola ' . $nombreDestino . ',' : 'Hola,';
    $asuntoInfo = $asuntoOriginal !== '' ? $asuntoOriginal : 'Sin asunto';
    $respuestaEscapada = nl2br(htmlspecialchars($respuesta, ENT_QUOTES, 'UTF-8'));

    $logoHtml = $logoSrc !== ''
        ? '<img src="' . htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') . '" alt="Sietelsa" style="display:block;height:54px;width:auto;max-width:100%;" />'
        : '<div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:.4px;">SIETELSA</div>';

    return '<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Respuesta Sietelsa</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1a2533;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #d8e0ec;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="background:#0f1d63;padding:18px 22px;border-top:5px solid #d71f2d;">'
                . $logoHtml .
            '</td>
          </tr>
          <tr>
            <td style="padding:26px 22px 18px 22px;">
              <p style="margin:0 0 16px 0;font-size:16px;line-height:1.55;">' . htmlspecialchars($saludo, ENT_QUOTES, 'UTF-8') . '</p>
              <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;">
                Gracias por contactarnos. Hemos recibido tu consulta y esta es nuestra respuesta:
              </p>
              <div style="margin:0 0 18px 0;padding:14px 16px;background:#f8fbff;border-left:4px solid #d71f2d;border-radius:6px;font-size:15px;line-height:1.7;color:#16263a;">
                ' . $respuestaEscapada . '
              </div>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 8px 0;">
                <tr>
                  <td style="font-size:12px;color:#4f5d71;padding:0 0 5px 0;">Referencia</td>
                </tr>
                <tr>
                  <td style="font-size:14px;color:#1e2b3c;font-weight:700;padding:0;">Asunto original: ' . htmlspecialchars($asuntoInfo, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 22px;background:#eef3fa;border-top:1px solid #dde6f2;">
              <p style="margin:0;font-size:13px;line-height:1.6;color:#46576d;">
                Saludos,<br />
                <span style="color:#0f1d63;font-weight:700;">Equipo Sietelsa</span>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_contacto_responder')) {
    redirigir_contacto_admin('csrf');
}

require_modulo('gestionar_contacto');
global $pdo;
asegurar_tabla_contacto_mensajes($pdo);

$usuario = obtener_usuario_actual() ?? [];
$usuarioId = (int) ($usuario['id'] ?? 0);

$mensajeId = (int) ($_POST['mensaje_id'] ?? 0);
$respuesta = trim((string) ($_POST['respuesta'] ?? ''));

if ($mensajeId <= 0) {
    redirigir_contacto_admin('id');
}

if ($respuesta === '') {
    redirigir_contacto_admin('respuesta', $mensajeId);
}

if (contacto_admin_longitud($respuesta) > 10000) {
    redirigir_contacto_admin('respuesta_larga', $mensajeId);
}

$stmt = $pdo->prepare(
    'SELECT id, nombre, email, asunto, estado
     FROM contacto_mensajes
     WHERE id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $mensajeId]);
$mensajeContacto = $stmt->fetch();

if (!is_array($mensajeContacto)) {
    redirigir_contacto_admin('id');
}

$nombreDestino = trim((string) ($mensajeContacto['nombre'] ?? ''));
$emailDestino = trim((string) ($mensajeContacto['email'] ?? ''));
$asuntoOriginal = trim((string) ($mensajeContacto['asunto'] ?? ''));

if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
    redirigir_contacto_admin('email', $mensajeId);
}

$asuntoRespuesta = $asuntoOriginal !== ''
    ? 'Re: ' . $asuntoOriginal . ' - Sietelsa'
    : 'Respuesta a tu mensaje de contacto - Sietelsa';

$saludo = $nombreDestino !== '' ? '' . $nombreDestino . ',' : ',';
$cuerpo = $saludo . "\r\n\r\n"
    . "Gracias por comunicarte con Sietelsa.\r\n\r\n"
    . $respuesta . "\r\n\r\n"
    . "Saludos,\r\nEquipo Sietelsa";
$logoSrc = '';
$inlineAdjuntos = [];
$adjuntoLogo = contacto_logo_inline_adjunto();
if (!empty($adjuntoLogo)) {
    $logoSrc = 'cid:' . (string) $adjuntoLogo['cid'];
    $inlineAdjuntos[] = $adjuntoLogo;
} else {
    $logoSrc = contacto_logo_url();
}
$cuerpoHtml = contacto_html_respuesta($nombreDestino, $respuesta, $asuntoOriginal, $logoSrc);

$errorCorreo = '';
$enviado = smtp_enviar_correo(
    [$emailDestino],
    $asuntoRespuesta,
    $cuerpo,
    [
        'reply_to' => smtp_correo_destino_contacto(),
        'from_name' => 'Soporte Sietelsa',
        'body_html' => $cuerpoHtml,
        'inline_attachments' => $inlineAdjuntos,
    ],
    $errorCorreo
);

if (!$enviado) {
    try {
        $errorCorreo = trim($errorCorreo);
        if ($errorCorreo !== '' && contacto_admin_longitud($errorCorreo) > 255) {
            if (function_exists('mb_substr')) {
                $errorCorreo = (string) mb_substr($errorCorreo, 0, 255, 'UTF-8');
            } else {
                $errorCorreo = substr($errorCorreo, 0, 255);
            }
        }

        $stmtError = $pdo->prepare(
            'UPDATE contacto_mensajes
             SET error_notificacion = :error
             WHERE id = :id
             LIMIT 1'
        );
        $stmtError->execute([
            ':id' => $mensajeId,
            ':error' => $errorCorreo !== '' ? $errorCorreo : 'No se pudo enviar el correo de respuesta por SMTP.',
        ]);
    } catch (Throwable $e) {
        // Evita romper el flujo si falla el guardado del error.
    }

    redirigir_contacto_admin('smtp', $mensajeId);
}

try {
    $stmtUpdate = $pdo->prepare(
        'UPDATE contacto_mensajes
         SET estado = :estado,
             respuesta_admin = :respuesta_admin,
             respondido_por_usuario_id = :respondido_por,
             respondido_en = NOW(),
             error_notificacion = NULL
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':estado' => 'respondido',
        ':respuesta_admin' => $respuesta,
        ':respondido_por' => $usuarioId > 0 ? $usuarioId : null,
        ':id' => $mensajeId,
    ]);
} catch (PDOException $e) {
    redirigir_contacto_admin('db', $mensajeId);
} catch (Throwable $e) {
    redirigir_contacto_admin('general', $mensajeId);
}

redirigir_contacto_admin('respondido', $mensajeId);
