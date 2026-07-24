<?php

declare(strict_types=1);

require_once __DIR__ . '/content.php';

function render_public_detail(string $sectionKey): never
{
    global $cms_snapshot;
    $cms_snapshot = cms_snapshot('inicio', false);
    $section = $cms_snapshot['sections'][$sectionKey] ?? null;
    $requested = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $selected = null;
    foreach ($section['items'] ?? [] as $item) {
        if ($requested === null || $requested === false) {
            $selected = $item;
            break;
        }
        if ((int) $item['id'] === (int) $requested) {
            $selected = $item;
            break;
        }
    }
    if (!$selected) {
        http_response_code(404);
    }
    $data = $selected['data'] ?? $section['data'] ?? [];
    $title = $data['title'] ?? ($section['data']['title'] ?? 'SIETELSA');
    ?>
    <!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= auth_escape($title) ?> | SIETELSA</title>
    <link href="<?= auth_escape(sietelsa_logo_url()) ?>" rel="icon">
    <link href="<?= auth_escape(app_url('src/website/assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= auth_escape(app_url('src/website/assets/vendor/bootstrap-icons/bootstrap-icons.css')) ?>" rel="stylesheet">
    <link href="<?= auth_escape(app_url('src/website/assets/css/main.css')) ?>" rel="stylesheet">
    <link href="<?= auth_escape(app_url('src/website/assets/css/sietelsa.css')) ?>" rel="stylesheet"></head>
    <body class="starter-page-page"><header id="header" class="header d-flex align-items-center fixed-top"><div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between"><?php require __DIR__ . '/public-navbar.php'; ?></div></header>
    <main class="main"><div class="page-title dark-background"><div class="container position-relative"><h1><?= auth_escape($title) ?></h1><nav class="breadcrumbs"><ol><li><a href="<?= auth_escape(app_url('index.php')) ?>">Inicio</a></li><li class="current"><?= auth_escape($title) ?></li></ol></nav></div></div>
    <section class="section"><div class="container"><div class="row gy-4"><div class="col-lg-7"><?php if (!empty($selected['media'])): ?><img src="<?= auth_escape(cms_media_url($selected['media'], 'large')) ?>" class="img-fluid rounded" width="<?= (int) $selected['media']['width'] ?>" height="<?= (int) $selected['media']['height'] ?>" alt="<?= auth_escape($selected['media']['alt_text']) ?>"><?php endif; ?></div><div class="col-lg-5"><h2><?= auth_escape($title) ?></h2><?php if (!empty($data['subtitle'])): ?><h3 class="h5 text-muted"><?= auth_escape($data['subtitle']) ?></h3><?php endif; ?><p><?= nl2br(auth_escape($data['content'] ?? '')) ?></p><a class="btn btn-primary" href="<?= auth_escape(app_url('index.php#contacto')) ?>">Contactar</a></div></div></div></section></main>
    <footer class="footer dark-background"><div class="container text-center py-4"><?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-footer-logo') ?><p><?= auth_escape($cms_snapshot['settings']['footer.copyright'] ?? '') ?></p></div></footer>
    <script src="<?= auth_escape(app_url('src/website/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script><script src="<?= auth_escape(app_url('src/website/assets/js/main.js')) ?>"></script></body></html>
    <?php
    exit;
}
