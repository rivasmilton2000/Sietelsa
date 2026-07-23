<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/conexion.php';

if (auth_is_admin()) {
    auth_redirect('src/dashboard_admin/index.php');
}

$errorMessage = '';
$statusMessage = '';
$identifier = '';

if (($_GET['status'] ?? '') === 'denied') {
    $errorMessage = 'La cuenta no tiene acceso al área administrativa.';
} elseif (($_GET['status'] ?? '') === 'internal') {
    $errorMessage = 'El servicio no está disponible temporalmente. Intente más tarde.';
} elseif (($_GET['status'] ?? '') === 'logged_out') {
    $statusMessage = 'La sesión se cerró correctamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'La solicitud expiró. Actualice la página e intente nuevamente.';
    } elseif ($identifier === '' || $password === '') {
        $errorMessage = 'Ingrese su correo o usuario y su contraseña.';
    } elseif (strlen($identifier) > 120 || strlen($password) > 4096) {
        $errorMessage = 'Las credenciales proporcionadas no son válidas.';
    } else {
        try {
            $connection = db_connection();
            $statement = $connection->prepare(
                'SELECT
                    id,
                    nombre,
                    username,
                    email,
                    password_hash,
                    rol,
                    activo,
                    intentos_fallidos,
                    bloqueado_hasta
                 FROM usuarios
                 WHERE username = :username OR email = :email
                 LIMIT 1'
            );
            $normalizedIdentifier = strtolower($identifier);
            $statement->execute([
                'username' => $identifier,
                'email' => $normalizedIdentifier,
            ]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                if ($user) {
                    $failedStatement = $connection->prepare(
                        'UPDATE usuarios
                         SET
                            intentos_fallidos = intentos_fallidos + 1,
                            bloqueado_hasta = CASE
                                WHEN intentos_fallidos + 1 >= 5
                                THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                ELSE bloqueado_hasta
                            END
                         WHERE id = :id'
                    );
                    $failedStatement->execute(['id' => (int) $user['id']]);
                }

                $errorMessage = 'Las credenciales proporcionadas no son válidas.';
            } elseif (
                $user['bloqueado_hasta'] !== null
                && strtotime((string) $user['bloqueado_hasta']) > time()
            ) {
                $errorMessage = 'La cuenta está temporalmente bloqueada. Intente más tarde.';
            } elseif ((int) $user['activo'] !== 1) {
                $errorMessage = 'La cuenta se encuentra desactivada. Contacte al administrador.';
            } elseif ($user['rol'] !== 'admin') {
                $errorMessage = 'La cuenta no tiene acceso al área administrativa.';
            } else {
                if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
                    $rehashStatement = $connection->prepare(
                        'UPDATE usuarios SET password_hash = :password_hash WHERE id = :id'
                    );
                    $rehashStatement->execute([
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => (int) $user['id'],
                    ]);
                }

                $accessStatement = $connection->prepare(
                    'UPDATE usuarios
                     SET
                        ultimo_login = NOW(),
                        intentos_fallidos = 0,
                        bloqueado_hasta = NULL
                     WHERE id = :id'
                );
                $accessStatement->execute(['id' => (int) $user['id']]);

                auth_store_user($user);
                auth_redirect('src/dashboard_admin/index.php');
            }
        } catch (Throwable $exception) {
            error_log('[SIETELSA] Error controlado durante el inicio de sesión: ' . $exception->getMessage());
            $errorMessage = 'Ocurrió un error interno. Intente nuevamente más tarde.';
        }
    }

    auth_rotate_csrf_token();
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Iniciar sesión | SIETELSA</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../dashboard_admin/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../dashboard_admin/assets/css/style.css">
    <link rel="stylesheet" href="<?= auth_escape(app_url('src/website/assets/css/sietelsa.css')) ?>">
    <!-- endinject -->
    <link rel="icon" type="image/png" href="<?= auth_escape(sietelsa_logo_url()) ?>">
    <script>document.documentElement.classList.add('sietelsa-js');</script>
  </head>
  <body>
    <div class="sietelsa-page-loader" role="status" aria-label="Cargando inicio de sesión">
      <div class="sietelsa-loader-content">
        <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-loader-logo') ?>
        <span class="sietelsa-loader-spinner" aria-hidden="true"></span>
      </div>
    </div>
    <div class="container-scroller">
      <div class="container-fluid page-body-wrapper full-page-wrapper">
        <div class="content-wrapper d-flex align-items-center auth px-0">
          <div class="row w-100 mx-0">
            <div class="col-lg-4 mx-auto">
              <div class="auth-form-light text-left py-5 px-4 px-sm-5">
                <div class="brand-logo">
                  <?= sietelsa_logo_picture('sietelsa-brand-logo sietelsa-login-logo') ?>
                </div>
                <h4>Hello! let's get started</h4>
                <h6 class="fw-light">Sign in to continue.</h6>
                <?php if ($errorMessage !== ''): ?>
                  <div class="alert alert-danger" role="alert" aria-live="polite" data-sietelsa-alert="error">
                    <?= auth_escape($errorMessage) ?>
                  </div>
                <?php elseif ($statusMessage !== ''): ?>
                  <div class="alert alert-success" role="status" aria-live="polite" data-sietelsa-alert="success">
                    <?= auth_escape($statusMessage) ?>
                  </div>
                <?php endif; ?>
                <form class="pt-3" method="post" action="<?= auth_escape(app_url('src/login/login.php')) ?>" id="loginForm" data-sietelsa-loading>
                  <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                  <div class="form-group">
                    <label class="visually-hidden" for="exampleInputEmail1">Correo o usuario</label>
                    <input
                      type="text"
                      class="form-control form-control-lg"
                      id="exampleInputEmail1"
                      name="identifier"
                      value="<?= auth_escape($identifier) ?>"
                      placeholder="Correo o usuario"
                      autocomplete="username"
                      maxlength="120"
                      required
                      autofocus>
                  </div>
                  <div class="form-group">
                    <label class="visually-hidden" for="exampleInputPassword1">Contraseña</label>
                    <input
                      type="password"
                      class="form-control form-control-lg"
                      id="exampleInputPassword1"
                      name="password"
                      placeholder="Contraseña"
                      autocomplete="current-password"
                      required>
                  </div>
                  <div class="mt-3 d-grid gap-2">
                    <button
                      type="submit"
                      class="btn btn-block btn-primary btn-lg fw-medium auth-form-btn"
                      id="loginButton">SIGN IN</button>
                  </div>
                  <div class="my-2 d-flex justify-content-between align-items-center">
                    <div class="form-check">
                      <label class="form-check-label text-muted">
                        <input type="checkbox" class="form-check-input"> Keep me signed in </label>
                    </div>
                    <a href="<?= auth_escape(app_url('index.php#contact')) ?>" class="auth-link text-black">Forgot password?</a>
                  </div>
                  <div class="mb-2 d-grid gap-2">
                    <button type="button" class="btn btn-block btn-facebook auth-form-btn">
                      <i class="ti-facebook me-2"></i>Connect using facebook </button>
                  </div>
                  <div class="text-center mt-4 fw-light"> Don't have an account? <a href="<?= auth_escape(app_url('index.php#contact')) ?>" class="text-primary">Contact</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        <!-- content-wrapper ends -->
      </div>
      <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="../dashboard_admin/assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../dashboard_admin/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="../dashboard_admin/assets/js/off-canvas.js"></script>
    <script src="../dashboard_admin/assets/js/template.js"></script>
    <script src="../dashboard_admin/assets/js/settings.js"></script>
    <script src="../dashboard_admin/assets/js/hoverable-collapse.js"></script>
    <script src="../dashboard_admin/assets/js/todolist.js"></script>
    <!-- endinject -->
    <script src="<?= auth_escape(app_url('src/website/assets/vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
    <script src="<?= auth_escape(app_url('src/website/assets/js/sietelsa-ui.js')) ?>"></script>
  </body>
</html>
