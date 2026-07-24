<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
auth_require_authenticated();
if (auth_is_admin()) {
    auth_redirect('src/dashboard_admin/index.php');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi cuenta | SIETELSA</title>
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/dashboard_admin/assets/vendors/css/vendor.bundle.base.css')) ?>">
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/dashboard_admin/assets/css/style.css')) ?>">
  <link rel="icon" href="<?= auth_escape(sietelsa_logo_url()) ?>">
</head>
<body>
  <main class="container py-5">
    <div class="card mx-auto" style="max-width:680px">
      <div class="card-body p-5 text-center">
        <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-login-logo') ?>
        <h1 class="h3 mt-4">Bienvenido, <?= auth_escape(auth_user_name()) ?></h1>
        <p class="text-muted">Tu cuenta está activa. Este perfil no tiene permisos para administrar el website.</p>
        <?php if (($_GET['status'] ?? '') === 'denied'): ?>
          <div class="alert alert-warning">El dashboard administrativo está reservado para administradores.</div>
        <?php endif; ?>
        <a class="btn btn-outline-primary me-2" href="<?= auth_escape(app_url('index.php')) ?>">Ir al website</a>
        <form method="post" action="<?= auth_escape(app_url('src/login/logout.php')) ?>" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
          <button class="btn btn-primary" type="submit">Cerrar sesión</button>
        </form>
      </div>
    </div>
  </main>
</body>
</html>
