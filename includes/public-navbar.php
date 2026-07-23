<?php

declare(strict_types=1);

$publicSections = [
    'servicios' => 'SERVICIOS',
    'portafolio' => 'PORTAFOLIO',
    'nosotros' => 'NOSOTROS',
    'proyectos' => 'PROYECTOS',
    'ubicacion' => 'UBICACIÓN',
    'contacto' => 'CONTACTO',
];
?>
<a href="<?= auth_escape(app_url('index.php')) ?>" class="logo d-flex align-items-center" aria-label="SIETELSA - Inicio">
  <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-website-logo') ?>
</a>

<nav id="navmenu" class="navmenu" aria-label="Navegación principal">
  <ul>
    <?php foreach ($publicSections as $sectionId => $label): ?>
      <li>
        <a href="<?= auth_escape(app_url('index.php#' . $sectionId)) ?>" data-sietelsa-section="<?= auth_escape($sectionId) ?>">
          <?= auth_escape($label) ?>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (auth_is_admin()): ?>
      <li><a href="<?= auth_escape(app_url('src/dashboard_admin/index.php')) ?>">MI PANEL</a></li>
      <li>
        <form method="post" action="<?= auth_escape(app_url('src/login/logout.php')) ?>" class="sietelsa-nav-logout-form">
          <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
          <button type="submit" class="sietelsa-nav-logout">CERRAR SESIÓN</button>
        </form>
      </li>
    <?php else: ?>
      <li><a href="<?= auth_escape(app_url('src/login/login.php')) ?>">INICIAR SESIÓN</a></li>
    <?php endif; ?>
  </ul>
  <i class="mobile-nav-toggle d-xl-none bi bi-list" role="button" tabindex="0" aria-label="Abrir menú" aria-controls="navmenu" aria-expanded="false"></i>
</nav>
