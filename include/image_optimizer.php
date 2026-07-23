<?php
declare(strict_types=1);

function sietelsa_imagen_gd_disponible(): bool
{
    return extension_loaded('gd')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled');
}

function sietelsa_normalizar_ruta_local_imagen(string $ruta): string
{
    $ruta = trim(str_replace('\\', '/', $ruta));
    if ($ruta === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $ruta) === 1) {
        return '';
    }

    while (strpos($ruta, './') === 0) {
        $ruta = substr($ruta, 2);
    }

    return ltrim($ruta, '/');
}

function sietelsa_imagen_accepta_webp(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return strpos($accept, 'image/webp') !== false;
}

function sietelsa_imagen_cache_dir(): string
{
    $dir = __DIR__ . '/../assets/cache/img';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function sietelsa_imagen_cache_url(string $file): string
{
    return 'assets/cache/img/' . ltrim(str_replace('\\', '/', $file), '/');
}

function sietelsa_abrir_imagen_desde_archivo(string $ruta, int &$tipo = 0)
{
    $info = @getimagesize($ruta);
    if (!is_array($info) || !isset($info[2])) {
        return false;
    }

    $tipo = (int) $info[2];

    if ($tipo === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        return @imagecreatefromjpeg($ruta);
    }
    if ($tipo === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        return @imagecreatefrompng($ruta);
    }
    if ($tipo === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($ruta);
    }
    if ($tipo === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
        return @imagecreatefromgif($ruta);
    }

    return false;
}

function sietelsa_guardar_imagen_en_archivo($imagen, string $destino, string $formato, int $calidad): bool
{
    $calidad = max(60, min(90, $calidad));

    if ($formato === 'jpg' || $formato === 'jpeg') {
        if (!function_exists('imagejpeg')) {
            return false;
        }
        return @imagejpeg($imagen, $destino, $calidad);
    }

    if ($formato === 'png') {
        if (!function_exists('imagepng')) {
            return false;
        }
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        return @imagepng($imagen, $destino, 6);
    }

    if ($formato === 'webp') {
        if (!function_exists('imagewebp')) {
            return false;
        }
        return @imagewebp($imagen, $destino, $calidad);
    }

    return false;
}

function sietelsa_redimensionar_imagen(
    string $rutaOrigen,
    string $rutaDestino,
    int $anchoObjetivo,
    int $altoObjetivo,
    string $fit,
    string $formatoSalida,
    int $calidad = 82
): bool {
    if (!sietelsa_imagen_gd_disponible()) {
        return false;
    }

    $tipo = 0;
    $origen = sietelsa_abrir_imagen_desde_archivo($rutaOrigen, $tipo);
    if ($origen === false) {
        return false;
    }

    $anchoOriginal = imagesx($origen);
    $altoOriginal = imagesy($origen);

    if ($anchoOriginal <= 0 || $altoOriginal <= 0) {
        imagedestroy($origen);
        return false;
    }

    $anchoObjetivo = max(1, $anchoObjetivo);
    $altoObjetivo = max(1, $altoObjetivo);

    if ($fit === 'contain') {
        $escala = min($anchoObjetivo / $anchoOriginal, $altoObjetivo / $altoOriginal);
        $escala = min(1.0, $escala);
        $anchoFinal = max(1, (int) floor($anchoOriginal * $escala));
        $altoFinal = max(1, (int) floor($altoOriginal * $escala));
        $srcX = 0;
        $srcY = 0;
        $srcW = $anchoOriginal;
        $srcH = $altoOriginal;
    } else {
        $escala = max($anchoObjetivo / $anchoOriginal, $altoObjetivo / $altoOriginal);
        $srcW = (int) round($anchoObjetivo / $escala);
        $srcH = (int) round($altoObjetivo / $escala);
        $srcW = max(1, min($srcW, $anchoOriginal));
        $srcH = max(1, min($srcH, $altoOriginal));
        $srcX = (int) floor(($anchoOriginal - $srcW) / 2);
        $srcY = (int) floor(($altoOriginal - $srcH) / 2);
        $anchoFinal = $anchoObjetivo;
        $altoFinal = $altoObjetivo;
    }

    $destino = imagecreatetruecolor($anchoFinal, $altoFinal);
    if ($destino === false) {
        imagedestroy($origen);
        return false;
    }

    if (in_array($formatoSalida, ['png', 'webp'], true)) {
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
        imagefilledrectangle($destino, 0, 0, $anchoFinal, $altoFinal, $transparente);
    }

    $ok = imagecopyresampled(
        $destino,
        $origen,
        0,
        0,
        $srcX,
        $srcY,
        $anchoFinal,
        $altoFinal,
        $srcW,
        $srcH
    );

    if ($ok) {
        $ok = sietelsa_guardar_imagen_en_archivo($destino, $rutaDestino, $formatoSalida, $calidad);
    }

    imagedestroy($destino);
    imagedestroy($origen);

    return $ok;
}

function sietelsa_generar_variantes_imagen(string $ruta, int $ancho, int $alto, string $fit = 'cover', int $calidad = 82): array
{
    $rutaLocal = sietelsa_normalizar_ruta_local_imagen($ruta);
    if ($rutaLocal === '') {
        return [
            'fallback' => $ruta,
            'webp' => null,
            'width' => max(1, $ancho),
            'height' => max(1, $alto),
        ];
    }

    $rutaOrigen = __DIR__ . '/../' . $rutaLocal;
    if (!is_file($rutaOrigen) || !sietelsa_imagen_gd_disponible()) {
        return [
            'fallback' => $rutaLocal,
            'webp' => null,
            'width' => max(1, $ancho),
            'height' => max(1, $alto),
        ];
    }

    $fit = $fit === 'contain' ? 'contain' : 'cover';
    $ancho = max(1, $ancho);
    $alto = max(1, $alto);

    $ext = strtolower((string) pathinfo($rutaLocal, PATHINFO_EXTENSION));
    $fallbackExt = in_array($ext, ['png', 'gif'], true) ? 'png' : 'jpg';
    $hash = sha1($rutaLocal . '|' . filemtime($rutaOrigen) . '|' . filesize($rutaOrigen) . '|' . $ancho . 'x' . $alto . '|' . $fit . '|' . $calidad);

    $fileFallback = $hash . '_' . $ancho . 'x' . $alto . '_' . $fit . '.' . $fallbackExt;
    $fileWebp = $hash . '_' . $ancho . 'x' . $alto . '_' . $fit . '.webp';

    $cacheDir = sietelsa_imagen_cache_dir();
    $rutaFallback = $cacheDir . '/' . $fileFallback;
    $rutaWebp = $cacheDir . '/' . $fileWebp;

    if (!is_file($rutaFallback)) {
        sietelsa_redimensionar_imagen($rutaOrigen, $rutaFallback, $ancho, $alto, $fit, $fallbackExt, $calidad);
    }

    if (!is_file($rutaWebp) && function_exists('imagewebp')) {
        sietelsa_redimensionar_imagen($rutaOrigen, $rutaWebp, $ancho, $alto, $fit, 'webp', $calidad);
    }

    return [
        'fallback' => is_file($rutaFallback) ? sietelsa_imagen_cache_url($fileFallback) : $rutaLocal,
        'webp' => is_file($rutaWebp) ? sietelsa_imagen_cache_url($fileWebp) : null,
        'width' => $ancho,
        'height' => $alto,
    ];
}

function sietelsa_optimizar_imagen_subida(string $rutaAbsoluta, int $maxAncho = 1920, int $maxAlto = 1920, int $calidad = 82): void
{
    if (!is_file($rutaAbsoluta) || !sietelsa_imagen_gd_disponible()) {
        return;
    }

    $size = @getimagesize($rutaAbsoluta);
    if (!is_array($size) || !isset($size[0], $size[1])) {
        return;
    }

    $ancho = (int) $size[0];
    $alto = (int) $size[1];
    if ($ancho <= 0 || $alto <= 0) {
        return;
    }

    $ext = strtolower((string) pathinfo($rutaAbsoluta, PATHINFO_EXTENSION));
    $formato = in_array($ext, ['png', 'gif'], true) ? 'png' : (in_array($ext, ['webp'], true) ? 'webp' : 'jpg');

    $debeReducir = $ancho > $maxAncho || $alto > $maxAlto;
    if ($debeReducir) {
        sietelsa_redimensionar_imagen($rutaAbsoluta, $rutaAbsoluta, $maxAncho, $maxAlto, 'contain', $formato, $calidad);
    } else {
        sietelsa_redimensionar_imagen($rutaAbsoluta, $rutaAbsoluta, $ancho, $alto, 'contain', $formato, $calidad);
    }

    if (function_exists('imagewebp')) {
        $rutaWebp = preg_replace('/\.[a-z0-9]+$/i', '.webp', $rutaAbsoluta);
        if (is_string($rutaWebp) && $rutaWebp !== '') {
            $finalSize = @getimagesize($rutaAbsoluta);
            $w = (int) ($finalSize[0] ?? $ancho);
            $h = (int) ($finalSize[1] ?? $alto);
            sietelsa_redimensionar_imagen($rutaAbsoluta, $rutaWebp, $w, $h, 'contain', 'webp', $calidad);
        }
    }
}
