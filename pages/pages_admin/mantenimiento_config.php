<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';
require_once __DIR__ . '/../../include/maintenance.php';

require_admin();
ensure_rbac_runtime();

global $pdo;
sync_modules($pdo);

$usuario = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuario['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuario['username'] ?? ''));
$perfilSesion = trim((string) ($usuario['perfil'] ?? ''));
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');

$etiquetaSesion = $nombreSesion !== '' ? $nombreSesion : 'Usuario';
if ($usernameSesion !== '') {
    $etiquetaSesion .= ' (' . $usernameSesion . ')';
}

function mantenimiento_config_usuario_evento(array $evento): string
{
    $nombre = trim((string) ($evento['usuario_nombre_actual'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string) ($evento['usuario_nombre'] ?? ''));
    }

    $username = trim((string) ($evento['usuario_username_actual'] ?? ''));
    if ($username === '') {
        $username = trim((string) ($evento['usuario_username'] ?? ''));
    }

    if ($nombre !== '' && $username !== '') {
        return $nombre . ' (' . $username . ')';
    }

    if ($nombre !== '') {
        return $nombre;
    }

    if ($username !== '') {
        return $username;
    }

    return 'Sistema';
}

function mantenimiento_config_fecha_evento(string $fecha): string
{
    $fecha = trim($fecha);
    if ($fecha === '') {
        return '';
    }

    $ts = strtotime($fecha);
    if ($ts === false) {
        return $fecha;
    }

    return date('d/m/Y H:i:s', $ts);
}

$passwordConfigurada = false;
try {
    $passwordConfigurada = mantenimiento_password_configurada($pdo);
} catch (Throwable $e) {
    $passwordConfigurada = false;
}

$desbloqueoHasta = (int) ($_SESSION['mantenimiento_unlock_until'] ?? 0);
$accesoDesbloqueado = !$passwordConfigurada || $desbloqueoHasta > time();
$eventosMantenimiento = [];
if ($accesoDesbloqueado) {
    try {
        $eventosMantenimiento = mantenimiento_obtener_eventos($pdo, 300);
    } catch (Throwable $e) {
        $eventosMantenimiento = [];
    }
}

$ok = trim((string) ($_GET['mantenimiento_ok'] ?? ''));
$error = trim((string) ($_GET['mantenimiento_error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

$mensajesOk = [
    'setup' => 'Primera configuracion habilitada. Define la clave de mantenimiento.',
    'desbloqueado' => 'Acceso al modulo desbloqueado por 15 minutos.',
    'clave_actualizada' => 'Clave de mantenimiento actualizada correctamente.',
];
$mensajesError = [
    'metodo' => 'Solicitud no permitida.',
    'accion' => 'Accion no valida.',
    'csrf' => 'Token CSRF invalido o expirado. Intenta de nuevo.',
    'clave_requerida' => 'Debes ingresar la clave de mantenimiento.',
    'clave_invalida' => 'La clave de mantenimiento no es correcta.',
    'actual_requerida' => 'Debes ingresar la clave actual para cambiarla.',
    'actual_invalida' => 'La clave actual no coincide.',
    'nueva_requerida' => 'Debes completar la nueva clave y su confirmacion.',
    'nueva_corta' => 'La nueva clave debe tener al menos 8 caracteres.',
    'nueva_diferente' => 'La confirmacion no coincide con la nueva clave.',
    'desbloqueo_requerido' => 'Debes desbloquear el modulo para descargar reportes.',
    'reporte_formato' => 'Formato de reporte no valido.',
    'db' => 'No se pudo guardar la clave de mantenimiento.',
];

if ($ok !== '' && isset($mensajesOk[$ok])) {
    $mensaje = $mensajesOk[$ok];
    $tipoMensaje = 'success';
} elseif ($error !== '') {
    $mensaje = $mensajesError[$error] ?? 'Ocurrio un error al procesar la solicitud.';
    $tipoMensaje = 'danger';
}

$csrfUnlock = csrf_token('mantenimiento_unlock');
$csrfUpdate = csrf_token('mantenimiento_update_password');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Configuracion de mantenimiento - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Mantenimiento | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
                <?php render_admin_topnav($pdo, $usuario, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, $usuario, $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Mantenimiento</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Mantenimiento</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-shield-halved me-1"></i>
                                Seguridad del modo mantenimiento
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                                    <div>
                                        <div class="fw-semibold mb-1">
                                            Clave configurada:
                                            <span class="badge <?php echo $passwordConfigurada ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo $passwordConfigurada ? 'SI' : 'NO'; ?>
                                            </span>
                                        </div>
                                        <div class="text-muted small">
                                            Esta clave es la misma que se solicita para activar o desactivar el modo mantenimiento.
                                        </div>
                                    </div>
                                    <?php if ($passwordConfigurada): ?>
                                        <div class="text-muted small">
                                            Desbloqueo del modulo vigente por 15 minutos.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$accesoDesbloqueado): ?>
                                    <div class="alert alert-info" role="alert">
                                        Ingresa la clave de mantenimiento para entrar al modulo.
                                    </div>
                                    <form action="/admin/acciones/mantenimiento/password" method="post" class="row g-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfUnlock, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="desbloquear" />
                                        <div class="col-md-6">
                                            <label class="form-label" for="mantenimiento_password">Clave de mantenimiento</label>
                                            <input
                                                class="form-control"
                                                type="password"
                                                id="mantenimiento_password"
                                                name="mantenimiento_password"
                                                autocomplete="current-password"
                                                required
                                            />
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-lock-open me-1"></i>Entrar al modulo
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <form action="/admin/acciones/mantenimiento/password" method="post" class="row g-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfUpdate, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="actualizar_clave" />

                                        <?php if ($passwordConfigurada): ?>
                                            <div class="col-md-6">
                                                <label class="form-label" for="password_actual">Clave actual</label>
                                                <input
                                                    class="form-control"
                                                    type="password"
                                                    id="password_actual"
                                                    name="password_actual"
                                                    autocomplete="current-password"
                                                    required
                                                />
                                            </div>
                                        <?php endif; ?>

                                        <div class="col-md-6">
                                            <label class="form-label" for="password_nueva">Nueva clave</label>
                                            <input
                                                class="form-control"
                                                type="password"
                                                id="password_nueva"
                                                name="password_nueva"
                                                minlength="8"
                                                maxlength="120"
                                                autocomplete="new-password"
                                                required
                                            />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="password_confirmar">Confirmar nueva clave</label>
                                            <input
                                                class="form-control"
                                                type="password"
                                                id="password_confirmar"
                                                name="password_confirmar"
                                                minlength="8"
                                                maxlength="120"
                                                autocomplete="new-password"
                                                required
                                            />
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-warning" type="submit">
                                                <i class="fas fa-key me-1"></i>Actualizar clave
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($accesoDesbloqueado): ?>
                            <div class="card mb-4">
                                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                    <div>
                                        <i class="fas fa-clock-rotate-left me-1"></i>
                                        Bitacora de mantenimiento
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-outline-success" href="/admin/acciones/mantenimiento/reporte?formato=excel">
                                            <i class="fas fa-file-excel me-1"></i>Excel
                                        </a>
                                        <a class="btn btn-sm btn-outline-danger" href="/admin/acciones/mantenimiento/reporte?formato=pdf">
                                            <i class="fas fa-file-pdf me-1"></i>PDF
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Registro de usuarios, dia y hora de cada accion de mantenimiento.</p>
                                    <?php if (empty($eventosMantenimiento)): ?>
                                        <p class="mb-0 text-muted">No hay eventos registrados todavia.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Fecha y hora</th>
                                                        <th>Accion</th>
                                                        <th>Usuario</th>
                                                        <th>IP</th>
                                                        <th>Detalle</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($eventosMantenimiento as $evento): ?>
                                                        <tr>
                                                            <td><?php echo (int) ($evento['id'] ?? 0); ?></td>
                                                            <td><?php echo htmlspecialchars(mantenimiento_config_fecha_evento((string) ($evento['creado_en'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(mantenimiento_label_accion((string) ($evento['accion'] ?? 'evento')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(mantenimiento_config_usuario_evento($evento), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string) ($evento['ip_origen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string) ($evento['detalle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; Sietelsa <?php echo date('Y'); ?></div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="/admin/js/scripts.js?v=20260613e"></script>
    </body>
</html>
