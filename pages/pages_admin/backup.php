<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

require_modulo('gestionar_backup');
global $pdo;

$usuario = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuario['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuario['username'] ?? ''));
$perfilSesion = trim((string) ($usuario['perfil'] ?? ''));
$puedeGestionarUsuarios = tiene_permiso('gestionar_usuarios');
$puedeGestionarServicios = tiene_permiso('gestionar_servicios');
$puedeGestionarPortafolio = tiene_permiso('gestionar_portafolio');
$puedeGestionarProyectos = tiene_permiso('gestionar_proyectos');
$puedeGestionarNosotros = tiene_permiso('gestionar_nosotros');
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');
$puedeGestionarBackup = tiene_permiso('gestionar_backup');
$puedeGestionarPerfiles = tiene_permiso('gestionar_perfiles');
$puedeGestionarContenido = $puedeGestionarServicios
    || $puedeGestionarPortafolio
    || $puedeGestionarProyectos
    || $puedeGestionarNosotros
    || $puedeGestionarContacto;

$etiquetaSesion = $nombreSesion !== '' ? $nombreSesion : 'Usuario';
if ($usernameSesion !== '') {
    $etiquetaSesion .= ' (' . $usernameSesion . ')';
}

$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'danger';

$errores = [
    'dir' => 'No se pudo crear o acceder a la carpeta database para el respaldo.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'generar' => 'No se pudo generar el respaldo SQL.',
];
if (isset($errores[$error])) {
    $mensaje = $errores[$error];
}

function backup_formato_tamano(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return number_format($bytes / (1024 * 1024), 2) . ' MB';
}

$backupDir = __DIR__ . '/../../database';
$archivosBackup = [];

if (is_dir($backupDir)) {
    $lista = glob($backupDir . '/backup_*.sql');
    if (is_array($lista)) {
        foreach ($lista as $ruta) {
            if (!is_file($ruta)) {
                continue;
            }
            $timestamp = filemtime($ruta);
            $archivosBackup[] = [
                'nombre' => basename($ruta),
                'tamano' => (int) (filesize($ruta) ?: 0),
                'fecha' => $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : 'Sin fecha',
            ];
        }
    }
}

usort(
    $archivosBackup,
    static function (array $a, array $b): int {
        return strcmp((string) $b['nombre'], (string) $a['nombre']);
    }
);
$csrfBackup = csrf_token('admin_backup');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Backup de base de datos - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Backup BD | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
                <?php render_admin_topnav($pdo, $usuario, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, isset($usuario) && is_array($usuario) ? $usuario : (isset($usuarioSesion) && is_array($usuarioSesion) ? $usuarioSesion : []), $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Backup de base de datos</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Backup</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-file-arrow-down me-1"></i>
                                Generar respaldo SQL
                            </div>
                            <div class="card-body">
                                <p class="mb-3">Descarga una copia de seguridad de la DB <code></code>.</p>
                                <form action="/admin/acciones/backup" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfBackup, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-database me-1"></i>Generar Backup
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-clock-rotate-left me-1"></i>
                                Respaldos recientes en database/
                            </div>
                            <div class="card-body">
                                <?php if (empty($archivosBackup)): ?>
                                    <p class="mb-0 text-muted">No hay respaldos creados todavia.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Archivo</th>
                                                    <th>Tamano</th>
                                                    <th>Fecha</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($archivosBackup as $archivo): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars((string) ($archivo['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars(backup_formato_tamano((int) ($archivo['tamano'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($archivo['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
