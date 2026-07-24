<?php

declare(strict_types=1);

$navigation = $cms_snapshot['navigation'] ?? cms_snapshot()['navigation'];
$isLoggedIn = auth_is_authenticated();
$panelPath = auth_is_admin() ? 'src/dashboard_admin/index.php' : 'src/user/panel.php';
?>
<a href="<?= auth_escape(app_url('index.php')) ?>" class="logo d-flex align-items-center" aria-label="SIETELSA - Inicio">
  <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-website-logo') ?>
</a>
<nav id="navmenu" class="navmenu" aria-label="Navegación principal">
  <ul>
    <?php foreach ($navigation as $nav): ?>
      <?php
      if (!(int) $nav['visible']) {
          continue;
      }
      $access = $nav['access_level'];
      if (
          !(int) $nav['is_system']
          && (($access === 'guest' && $isLoggedIn) || ($access === 'authenticated' && !$isLoggedIn) || ($access === 'admin' && !auth_is_admin()))
      ) {
          continue;
      }
      if ((int) $nav['is_system'] === 1) {
          $label = $isLoggedIn ? 'MI PANEL' : 'INICIAR SESIÓN';
          $url = $isLoggedIn ? app_url($panelPath) : app_url('src/login/login.php');
      } else {
          $label = $nav['label'];
          $rawUrl = cms_clean_url($nav['url']);
          $url = str_starts_with($rawUrl, '#') ? app_url('index.php' . $rawUrl) : app_url($rawUrl);
      }
      ?>
      <li><a href="<?= auth_escape($url) ?>" target="<?= auth_escape($nav['target']) ?>" data-sietelsa-section="<?= auth_escape(ltrim((string) $nav['url'], '#')) ?>"><?= auth_escape($label) ?></a></li>
    <?php endforeach; ?>
    <?php if ($isLoggedIn): ?>
      <li>
        <form method="post" action="<?= auth_escape(app_url('src/login/logout.php')) ?>" class="sietelsa-nav-logout-form">
          <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
          <button type="submit" class="sietelsa-nav-logout">CERRAR SESIÓN</button>
        </form>
      </li>
    <?php endif; ?>
  </ul>
  <button class="mobile-nav-toggle sietelsa-mobile-only" type="button" aria-label="Abrir menú" aria-controls="navmenu" aria-expanded="false" style="position:fixed;top:14px;right:14px;z-index:10000;color:#fff;background:transparent;border:0;width:44px;height:44px"><span class="sietelsa-menu-glyph" aria-hidden="true">☰</span></button>
</nav>
