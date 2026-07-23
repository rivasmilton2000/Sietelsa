<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/user_profile.php';

function perfil_redirect_base(): string
{
    $fallback = ruta_inicio_usuario_actual();
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
        return $fallback;
    }

    $partes = parse_url($referer);
    if (!is_array($partes)) {
        return $fallback;
    }

    $hostReferer = strtolower(trim((string) ($partes['host'] ?? '')));
    $hostActual = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($hostReferer !== '' && $hostActual !== '' && $hostReferer !== $hostActual) {
        return $fallback;
    }

    $path = '/' . ltrim((string) ($partes['path'] ?? $fallback), '/');
    $query = [];
    if (isset($partes['query'])) {
        parse_str((string) $partes['query'], $query);
    }

    unset($query['perfil_ok'], $query['perfil_error'], $query['perfil_modal']);
    $queryString = http_build_query($query);

    return $path . ($queryString !== '' ? '?' . $queryString : '');
}

function perfil_redirigir(string $tipo, string $estado): void
{
    $base = perfil_redirect_base();
    $separador = str_contains($base, '?') ? '&' : '?';
    redirigir($base . $separador . $tipo . '=' . rawurlencode($estado) . '&perfil_modal=1');
}

function perfil_procesar_foto(?array $archivo): array
{
    if ($archivo === null || !isset($archivo['error'])) {
        return ['subido' => false, 'ruta_relativa' => null];
    }

    $error = (int) $archivo['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['subido' => false, 'ruta_relativa' => null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo cargar la foto.');
    }

    $size = (int) ($archivo['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('La foto no tiene contenido.');
    }

    $tmpPath = (string) ($archivo['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('La foto no es valida.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    if (!isset($mimeToExt[$mime])) {
        throw new RuntimeException('Formato no permitido.');
    }

    $uploadDir = __DIR__ . '/../assets/uploads/usuarios';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('No se pudo crear el directorio de fotos.');
    }

    $filename = 'user_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $mimeToExt[$mime];
    $targetPath = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('No se pudo guardar la foto.');
    }

    sietelsa_optimizar_imagen_subida($targetPath, 1200, 1200, 82);

    return [
        'subido' => true,
        'ruta_relativa' => 'assets/uploads/usuarios/' . $filename,
    ];
}

function perfil_eliminar_foto(?string $rutaRelativa): void
{
    $rutaRelativa = trim((string) $rutaRelativa);
    if ($rutaRelativa === '') {
        return;
    }

    $filename = basename($rutaRelativa);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $path = __DIR__ . '/../assets/uploads/usuarios/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

require_login();

global $pdo;
asegurar_compatibilidad_auth($pdo);

$usuarioSesion = obtener_usuario_actual() ?? [];
$usuarioId = (int) ($usuarioSesion['id'] ?? 0);
if ($usuarioId <= 0) {
    redirigir('/admin/login?error=auth');
}

$accion = trim((string) ($_POST['accion'] ?? ''));

if ($accion === 'foto') {
    $token = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!csrf_token_valido($token, 'perfil_foto')) {
        perfil_redirigir('perfil_error', 'csrf');
    }

    try {
        $stmtActual = $pdo->prepare('SELECT foto_path FROM usuarios WHERE id = :id LIMIT 1');
        $stmtActual->execute([':id' => $usuarioId]);
        $fotoAnterior = (string) ($stmtActual->fetchColumn() ?: '');

        $fotoSubida = perfil_procesar_foto($_FILES['foto'] ?? null);
        $fotoFinal = $fotoAnterior;
        if ((bool) ($fotoSubida['subido'] ?? false)) {
            $fotoFinal = (string) ($fotoSubida['ruta_relativa'] ?? '');
        } elseif (isset($_POST['eliminar_foto'])) {
            $fotoFinal = '';
        } else {
            perfil_redirigir('perfil_error', 'foto');
        }

        $stmt = $pdo->prepare('UPDATE usuarios SET foto_path = :foto_path WHERE id = :id LIMIT 1');
        $stmt->execute([
            ':foto_path' => $fotoFinal !== '' ? $fotoFinal : null,
            ':id' => $usuarioId,
        ]);

        if ($fotoAnterior !== '' && $fotoAnterior !== $fotoFinal) {
            perfil_eliminar_foto($fotoAnterior);
        }

        perfil_redirigir('perfil_ok', 'foto');
    } catch (PDOException $e) {
        perfil_redirigir('perfil_error', 'db');
    } catch (Throwable $e) {
        perfil_redirigir('perfil_error', 'foto');
    }
}

if ($accion === 'password') {
    $token = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!csrf_token_valido($token, 'perfil_password')) {
        perfil_redirigir('perfil_error', 'csrf');
    }

    $passwordActual = (string) ($_POST['password_actual'] ?? '');
    $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
    $passwordConfirmar = (string) ($_POST['password_confirmar'] ?? '');

    if (strlen($passwordNueva) < 6) {
        perfil_redirigir('perfil_error', 'password_largo');
    }
    if (!hash_equals($passwordNueva, $passwordConfirmar)) {
        perfil_redirigir('perfil_error', 'password_confirmar');
    }

    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $usuarioId]);
        $hashActual = (string) ($stmt->fetchColumn() ?: '');
        if ($hashActual === '' || !password_verify($passwordActual, $hashActual)) {
            perfil_redirigir('perfil_error', 'password_actual');
        }
        if (password_verify($passwordNueva, $hashActual)) {
            perfil_redirigir('perfil_error', 'password_igual');
        }

        $stmtUpdate = $pdo->prepare(
            'UPDATE usuarios
             SET password_hash = :password_hash,
                 must_change_password = 0
             WHERE id = :id
             LIMIT 1'
        );
        $stmtUpdate->execute([
            ':password_hash' => password_hash($passwordNueva, PASSWORD_DEFAULT),
            ':id' => $usuarioId,
        ]);

        $_SESSION['usuario']['must_change_password'] = 0;
        perfil_redirigir('perfil_ok', 'password');
    } catch (PDOException $e) {
        perfil_redirigir('perfil_error', 'db');
    } catch (Throwable $e) {
        perfil_redirigir('perfil_error', 'general');
    }
}

perfil_redirigir('perfil_error', 'general');
