<?php
declare(strict_types=1);

function sietelsa_asset_url(string $ruta): string
{
    $ruta = str_replace('\\', '/', trim($ruta));
    if ($ruta === '') {
        return '';
    }

    $partes = explode('?', $ruta, 2);
    $rutaBase = ltrim(trim((string) ($partes[0] ?? '')), '/');
    if ($rutaBase === '') {
        return '';
    }

    $queryOriginal = isset($partes[1]) && trim((string) $partes[1]) !== '' ? trim((string) $partes[1]) : '';

    $absoluta = __DIR__ . '/../' . $rutaBase;
    $version = is_file($absoluta) ? (string) filemtime($absoluta) : (string) time();

    $url = '/' . $rutaBase;
    if ($queryOriginal !== '') {
        $url .= '?' . $queryOriginal . '&v=' . rawurlencode($version);
        return $url;
    }

    return $url . '?v=' . rawurlencode($version);
}
