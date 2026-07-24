<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/public-content.php';

$isPreview = ($_GET['preview'] ?? '') === '1' && auth_is_admin();
$cms_snapshot = cms_snapshot('inicio', $isPreview);
$seo = $cms_snapshot['page']['seo'] ?? [];
$settings = $cms_snapshot['settings'];
$GLOBALS['cms_snapshot'] = $cms_snapshot;
$styleVersion = (string) filemtime(__DIR__ . '/src/website/assets/css/sietelsa.css');
$mainScriptVersion = (string) filemtime(__DIR__ . '/src/website/assets/js/main.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= auth_escape($seo['title'] ?? 'SIETELSA') ?></title>
  <meta name="description" content="<?= auth_escape($seo['description'] ?? '') ?>">
  <meta name="keywords" content="<?= auth_escape($seo['keywords'] ?? '') ?>">
  <link href="<?= auth_escape(sietelsa_logo_url()) ?>" rel="icon" type="image/png">
  <link href="<?= auth_escape(sietelsa_logo_url()) ?>" rel="apple-touch-icon">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&family=Raleway:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/vendor/bootstrap-icons/bootstrap-icons.css')) ?>" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/vendor/aos/aos.css')) ?>" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/vendor/swiper/swiper-bundle.min.css')) ?>" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/vendor/glightbox/css/glightbox.min.css')) ?>" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/css/main.css')) ?>" rel="stylesheet">
  <link href="<?= auth_escape(app_url('src/website/assets/css/sietelsa.css?v=' . $styleVersion)) ?>" rel="stylesheet">
  <script>document.documentElement.classList.add('sietelsa-js');</script>
</head>
<body class="index-page<?= $isPreview ? ' cms-preview-mode' : '' ?>">
  <div class="sietelsa-page-loader" role="status" aria-label="Cargando SIETELSA">
    <div class="sietelsa-loader-content">
      <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-loader-logo') ?>
      <span class="sietelsa-loader-spinner" aria-hidden="true"></span>
    </div>
  </div>
  <header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
      <?php require __DIR__ . '/includes/public-navbar.php'; ?>
    </div>
  </header>
  <main class="main">
    <?php foreach ($cms_snapshot['sections'] as $section): ?>
      <?php public_render_section($section); ?>
    <?php endforeach; ?>
  </main>
  <footer id="footer" class="footer dark-background">
    <div class="container footer-top">
      <div class="row gy-4">
        <div class="col-lg-5 col-md-12 footer-about">
          <a href="<?= auth_escape(app_url('index.php')) ?>" class="logo d-flex align-items-center">
            <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-footer-logo') ?>
          </a>
          <p><?= auth_escape($settings['footer.newsletter_text'] ?? '') ?></p>
          <div class="social-links d-flex mt-4">
            <?php foreach (['twitter', 'facebook', 'instagram', 'linkedin'] as $social): ?>
              <?php if (!empty($settings['social.' . $social])): ?>
                <a href="<?= auth_escape(cms_clean_url($settings['social.' . $social])) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= auth_escape(ucfirst($social)) ?>"><i class="bi bi-<?= auth_escape($social) ?>"></i></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-lg-3 col-6 footer-contact">
          <h4>Contacto</h4>
          <p><?= auth_escape($settings['contact.address_line_1'] ?? '') ?></p>
          <p><?= auth_escape($settings['contact.address_line_2'] ?? '') ?></p>
          <p class="mt-4"><strong>Teléfono:</strong> <span><?= auth_escape($settings['contact.phone'] ?? '') ?></span></p>
          <p><strong>Correo:</strong> <span><?= auth_escape($settings['contact.email'] ?? '') ?></span></p>
          <p><strong>Horario:</strong> <span><?= auth_escape($settings['contact.hours'] ?? '') ?></span></p>
        </div>
        <div class="col-lg-4 col-md-12 footer-newsletter">
          <h4><?= auth_escape($settings['footer.newsletter_title'] ?? 'Boletín') ?></h4>
          <form action="<?= auth_escape(app_url('src/website/forms/newsletter.php')) ?>" method="post" class="php-email-form" data-sietelsa-loading>
            <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
            <div class="newsletter-form"><input type="email" name="email" aria-label="Correo electrónico" required><input type="submit" value="Suscribirme"></div>
            <div class="loading">Enviando</div><div class="error-message"></div><div class="sent-message">Suscripción recibida.</div>
          </form>
        </div>
      </div>
    </div>
    <div class="container copyright text-center mt-4">
      <p>© <span>Copyright</span> <strong class="px-1 sitename"><?= auth_escape($settings['site.name'] ?? 'SIETELSA') ?></strong> <span><?= auth_escape($settings['footer.copyright'] ?? '') ?></span></p>
    </div>
  </footer>
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center" aria-label="Volver arriba"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/php-email-form/validate.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/aos/aos.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/swiper/swiper-bundle.min.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/glightbox/js/glightbox.min.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/imagesloaded/imagesloaded.pkgd.min.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/isotope-layout/isotope.pkgd.min.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/js/main.js?v=' . $mainScriptVersion)) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
  <script src="<?= auth_escape(app_url('src/website/assets/js/sietelsa-ui.js')) ?>"></script>
  <?php if ($isPreview): ?>
    <script src="<?= auth_escape(app_url('src/website/assets/js/cms-preview.js')) ?>"></script>
  <?php endif; ?>
</body>
</html>
