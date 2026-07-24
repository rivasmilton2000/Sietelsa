<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
auth_require_admin();
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <li class="nav-item">
      <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/index.php')) ?>">
        <i class="mdi mdi-grid-large menu-icon"></i>
        <span class="menu-title">Dashboard</span>
      </a>
    </li>
    <li class="nav-item nav-category">Contenido SIETELSA</li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/editor.php')) ?>"><i class="mdi mdi-monitor-edit menu-icon"></i><span class="menu-title">Editor visual</span></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/content.php')) ?>"><i class="mdi mdi-file-document-edit menu-icon"></i><span class="menu-title">Contenido</span></a></li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#website-sections" aria-expanded="false" aria-controls="website-sections"><i class="mdi mdi-view-list menu-icon"></i><span class="menu-title">Secciones website</span><i class="menu-arrow"></i></a>
      <div class="collapse" id="website-sections"><ul class="nav flex-column sub-menu">
        <?php foreach (['hero' => 'Inicio', 'servicios' => 'Servicios', 'portafolio' => 'Portafolio', 'nosotros' => 'Nosotros', 'proyectos' => 'Proyectos', 'contacto' => 'Ubicación y contacto', 'equipo' => 'Equipo', 'preguntas' => 'Preguntas'] as $moduleKey => $moduleLabel): ?>
          <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/content.php?module=' . $moduleKey)) ?>"><?= auth_escape($moduleLabel) ?></a></li>
        <?php endforeach; ?>
      </ul></div>
    </li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/navigation.php')) ?>"><i class="mdi mdi-menu menu-icon"></i><span class="menu-title">Navbar</span></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/settings.php')) ?>"><i class="mdi mdi-settings menu-icon"></i><span class="menu-title">Configuración/SEO</span></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/media.php')) ?>"><i class="mdi mdi-image-multiple menu-icon"></i><span class="menu-title">Multimedia</span></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/versions.php')) ?>"><i class="mdi mdi-history menu-icon"></i><span class="menu-title">Historial</span></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/messages.php')) ?>"><i class="mdi mdi-email menu-icon"></i><span class="menu-title">Mensajes</span></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/users.php')) ?>"><i class="mdi mdi-account-multiple menu-icon"></i><span class="menu-title">Usuarios</span></a></li>
    <li class="nav-item nav-category">UI Elements</li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
        <i class="menu-icon mdi mdi-floor-plan"></i>
        <span class="menu-title">UI Elements</span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse" id="ui-basic">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/ui-features/buttons.php')) ?>">Buttons</a></li>
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/ui-features/dropdowns.php')) ?>">Dropdowns</a></li>
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/ui-features/typography.php')) ?>">Typography</a></li>
        </ul>
      </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#form-elements" aria-expanded="false" aria-controls="form-elements">
        <i class="menu-icon mdi mdi-card-text-outline"></i>
        <span class="menu-title">Form elements</span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse" id="form-elements">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"><a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/forms/basic_elements.php')) ?>">Basic Elements</a></li>
        </ul>
      </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#charts" aria-expanded="false" aria-controls="charts">
        <i class="menu-icon mdi mdi-chart-line"></i>
        <span class="menu-title">Charts</span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse" id="charts">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/charts/chartjs.php')) ?>">ChartJs</a></li>
        </ul>
      </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#tables" aria-expanded="false" aria-controls="tables">
        <i class="menu-icon mdi mdi-table"></i>
        <span class="menu-title">Tables</span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse" id="tables">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/tables/basic-table.php')) ?>">Basic table</a></li>
        </ul>
      </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#icons" aria-expanded="false" aria-controls="icons">
        <i class="menu-icon mdi mdi-layers-outline"></i>
        <span class="menu-title">Icons</span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse" id="icons">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/icons/font-awesome.php')) ?>">Font Awesome</a></li>
        </ul>
      </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#auth" aria-expanded="false" aria-controls="auth">
        <i class="menu-icon mdi mdi-account-circle-outline"></i>
        <span class="menu-title">User Pages</span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse" id="auth">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/samples/blank-page.php')) ?>"> Blank Page </a></li>
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/samples/error-404.php')) ?>"> 404 </a></li>
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/samples/error-500.php')) ?>"> 500 </a></li>
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/login/login.php')) ?>"> Login </a></li>
          <li class="nav-item"> <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/pages/samples/register.php')) ?>"> Register </a></li>
        </ul>
      </div>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?= auth_escape(app_url('src/dashboard_admin/docs/documentation.html')) ?>">
        <i class="menu-icon mdi mdi-file-document"></i>
        <span class="menu-title">Documentation</span>
      </a>
    </li>
  </ul>
</nav>
