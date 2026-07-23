<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

function es_perfil_admin(string $perfil): bool
{
    $perfil = strtolower(trim($perfil));
    return $perfil === 'admin' || $perfil === 'administrador';
}

function es_perfil_developer(string $perfil): bool
{
    return strtolower(trim($perfil)) === 'developer';
}

require_modulo('gestionar_usuarios');

global $pdo;

$usuarioSesion = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuarioSesion['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuarioSesion['username'] ?? ''));
$perfilSesion = trim((string) ($usuarioSesion['perfil'] ?? ''));
$sesionEsDeveloper = es_perfil_developer($perfilSesion);
$usuarioSesionId = (int) ($usuarioSesion['id'] ?? 0);

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

$usuarios = $pdo->query(
    'SELECT u.id, u.nombre, u.username, u.email, u.activo, LOWER(TRIM(p.nombre)) AS perfil_normalizado, p.nombre AS perfil
     FROM usuarios u
     INNER JOIN perfiles p ON p.id = u.perfil_id
     ORDER BY u.nombre ASC'
)->fetchAll();

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'eliminado') {
    $mensaje = 'Usuario eliminado correctamente.';
}

$errores = [
    'metodo' => 'Solicitud no permitida para eliminar usuario.',
    'id' => 'No se encontro el usuario seleccionado.',
    'self' => 'No puedes eliminar la misma cuenta con la que tienes la sesion iniciada.',
    'developer' => 'Solo el developer puede eliminar cuentas con perfil developer.',
    'admin' => 'Solo admin o developer pueden eliminar cuentas con perfil administrador.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'db' => 'No se pudo eliminar el usuario por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($errores[$error])) {
    $mensaje = $errores[$error];
    $tipoMensaje = 'danger';
}
$csrfUsuariosEliminar = csrf_token('admin_usuarios_eliminar');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Eliminacion de usuarios - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Eliminar Usuarios | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
                <?php render_admin_topnav($pdo, $usuarioSesion, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, isset($usuario) && is_array($usuario) ? $usuario : (isset($usuarioSesion) && is_array($usuarioSesion) ? $usuarioSesion : []), $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Eliminar usuarios</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Eliminar usuarios</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user-minus me-1"></i>
                                Usuarios registrados
                            </div>
                            <div class="card-body">
                                <?php if (empty($usuarios)): ?>
                                    <p class="mb-0 text-muted">No hay usuarios para eliminar.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>Usuario</th>
                                                    <th>Correo</th>
                                                    <th>Perfil</th>
                                                    <th>Estado</th>
                                                    <th>Accion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usuarios as $filaUsuario): ?>
                                                    <?php
                                                    $idObjetivo = (int) ($filaUsuario['id'] ?? 0);
                                                    $perfilObjetivo = strtolower(trim((string) ($filaUsuario['perfil_normalizado'] ?? '')));
                                                    $esMismaSesion = $usuarioSesionId > 0 && $idObjetivo === $usuarioSesionId;
                                                    $esAdminObjetivo = es_perfil_admin($perfilObjetivo);
                                                    $esDeveloperObjetivo = es_perfil_developer($perfilObjetivo);
                                                    $bloqueadoPorDeveloper = $esDeveloperObjetivo && !$sesionEsDeveloper;
                                                    $bloqueadoPorAdmin = $esAdminObjetivo && !es_perfil_admin($perfilSesion) && !$sesionEsDeveloper;
                                                    $puedeEliminar = !$esMismaSesion && !$bloqueadoPorDeveloper && !$bloqueadoPorAdmin;

                                                    $motivoBloqueo = '';
                                                    if ($esMismaSesion) {
                                                        $motivoBloqueo = 'No puedes eliminar tu propia cuenta en sesion.';
                                                    } elseif ($bloqueadoPorDeveloper) {
                                                        $motivoBloqueo = 'Solo el developer puede eliminar cuentas developer.';
                                                    } elseif ($bloqueadoPorAdmin) {
                                                        $motivoBloqueo = 'Solo admin o developer pueden eliminar cuentas administrador.';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars((string) ($filaUsuario['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($filaUsuario['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($filaUsuario['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($filaUsuario['perfil'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <?php if ((int) ($filaUsuario['activo'] ?? 0) === 1): ?>
                                                                <span class="badge bg-success">Activo</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Inactivo</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($puedeEliminar): ?>
                                                                <form action="/admin/acciones/usuarios/eliminar" method="post" class="m-0">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfUsuariosEliminar, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="usuario_id" value="<?php echo $idObjetivo; ?>" />
                                                                    <button
                                                                        class="btn btn-outline-danger btn-sm"
                                                                        type="submit" data-confirm-message="Esta accion eliminara al usuario de forma permanente. Deseas continuar?"
                                                                    >
                                                                        Eliminar
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-secondary btn-sm" type="button" disabled title="<?php echo htmlspecialchars($motivoBloqueo, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    Bloqueado
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
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
