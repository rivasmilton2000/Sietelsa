<?php

declare(strict_types=1);

const SIETELSA_LOGO_RELATIVE_PATH = 'img/logoSietelsa.png';
const SIETELSA_LOGO_WEBP_RELATIVE_PATH = 'img/logoSietelsa.webp';

function sietelsa_logo_url(): string
{
    return app_url(SIETELSA_LOGO_RELATIVE_PATH);
}

function sietelsa_logo_webp_url(): string
{
    return app_url(SIETELSA_LOGO_WEBP_RELATIVE_PATH);
}

function sietelsa_logo_filesystem_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, SIETELSA_LOGO_RELATIVE_PATH);
}

function sietelsa_logo_picture(
    string $imageClass = 'sietelsa-brand-logo',
    string $pictureClass = '',
    bool $eager = true
): string {
    $safeImageClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', $imageClass) ?? '';
    $safePictureClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', $pictureClass) ?? '';
    $loading = $eager ? 'eager' : 'lazy';
    $priority = $eager ? ' fetchpriority="high"' : '';

    return sprintf(
        '<picture class="%s"><source srcset="%s" type="image/webp"><img src="%s" width="450" height="250" class="%s" alt="Logo de SIETELSA" loading="%s" decoding="async"%s></picture>',
        auth_escape($safePictureClass),
        auth_escape(sietelsa_logo_webp_url()),
        auth_escape(sietelsa_logo_url()),
        auth_escape($safeImageClass),
        $loading,
        $priority
    );
}

