<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

function es_perfil_base_protegido(string $nombrePerfil): bool
{
    $nombre = strtolower(trim($nombrePerfil));
    return in_array($nombre, ['admin', 'developer'], true);
}

require_modulo('gestionar_perfiles', true);

global $pdo;

$usuario = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuario['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuario['username'] ?? ''));
$perfilSesion = trim((string) ($usuario['perfil'] ?? ''));
$sesionEsAdmin = usuario_actual_es_admin();

$puedeGestionarServicios = tiene_permiso('gestionar_servicios');
$puedeGestionarPortafolio = tiene_permiso('gestionar_portafolio');
$puedeGestionarProyectos = tiene_permiso('gestionar_proyectos');
$puedeGestionarNosotros = tiene_permiso('gestionar_nosotros');
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');
$puedeGestionarBackup = tiene_permiso('gestionar_backup');
$puedeGestionarUsuarios = tiene_permiso('gestionar_usuarios');

$puedeGestionarContenido = $puedeGestionarServicios
    || $puedeGestionarPortafolio
    || $puedeGestionarProyectos
    || $puedeGestionarNosotros
    || $puedeGestionarContacto;

$etiquetaSesion = $nombreSesion !== '' ? $nombreSesion : 'Usuario';
if ($usernameSesion !== '') {
    $etiquetaSesion .= ' (' . $usernameSesion . ')';
}

$perfiles = $pdo->query(
    'SELECT p.id, p.nombre, p.descripcion, p.creado_en, COUNT(u.id) AS total_usuarios
     FROM perfiles p
     LEFT JOIN usuarios u ON u.perfil_id = p.id
     GROUP BY p.id, p.nombre, p.descripcion, p.creado_en
     ORDER BY p.nombre ASC'
)->fetchAll();

$modulosDisponibles = $pdo->query(
    "SELECT id, nombre_modulo, descripcion, estado
     FROM modulos
     ORDER BY nombre_modulo ASC"
)->fetchAll();

$perfilId = (int) ($_GET['id'] ?? 0);
$perfilEdicion = null;
foreach ($perfiles as $perfil) {
    if ((int) ($perfil['id'] ?? 0) === $perfilId) {
        $perfilEdicion = $perfil;
        break;
    }
}

$modulosPerfilSeleccionado = [];
if ($perfilEdicion !== null) {
    $stmtModulosPerfil = $pdo->prepare(
        'SELECT modulo_id
         FROM perfil_modulo
         WHERE perfil_id = :perfil_id AND aprobado = 1'
    );
    $stmtModulosPerfil->execute([
        ':perfil_id' => (int) ($perfilEdicion['id'] ?? 0),
    ]);
    foreach ($stmtModulosPerfil->fetchAll(PDO::FETCH_COLUMN) as $moduloId) {
        $modulosPerfilSeleccionado[(int) $moduloId] = true;
    }
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

$mensajesOk = [
    'creado' => 'Perfil creado correctamente.',
    'actualizado' => 'Perfil actualizado correctamente.',
    'eliminado' => 'Perfil eliminado correctamente.',
];
$mensajesError = [
    'accion' => 'Accion no valida.',
    'campos' => 'Completa los campos obligatorios.',
    'nombre' => 'El nombre del perfil debe tener 3 a 50 caracteres: letras minusculas, numeros o guion bajo.',
    'descripcion' => 'La descripcion excede 150 caracteres.',
    'id' => 'No se encontro el perfil seleccionado.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'protegido' => 'No se puede modificar de forma critica ni eliminar un perfil base protegido.',
    'en_uso' => 'No se puede eliminar un perfil con usuarios asignados. Solo se elimina cuando tiene 0 usuarios.',
    'duplicado' => 'Ya existe un perfil con ese nombre.',
    'db' => 'No se pudo guardar por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($mensajesOk[$ok])) {
    $mensaje = $mensajesOk[$ok];
} elseif (isset($mensajesError[$error])) {
    $mensaje = $mensajesError[$error];
    $tipoMensaje = 'danger';
}

$csrfPerfiles = csrf_token('admin_perfiles');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestion de perfiles - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Gestionar Perfiles | Sietelsa</title>
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
                        <h1 class="mt-4">Gestionar perfiles</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Perfiles</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-id-badge me-1"></i>
                                Crear perfil
                            </div>
                            <div class="card-body">
                                <form action="/admin/acciones/perfiles" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPerfiles, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <input type="hidden" name="accion" value="crear" />
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label" for="nombre">Nombre (codigo)</label>
                                            <input class="form-control" id="nombre" name="nombre" type="text" minlength="3" maxlength="50" pattern="[a-z0-9_]{3,50}" required placeholder="ej: supervisor" />
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label" for="descripcion">Descripcion</label>
                                            <input class="form-control" id="descripcion" name="descripcion" type="text" maxlength="150" />
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-plus me-1"></i>Crear perfil
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-list me-1"></i>
                                Perfiles existentes
                            </div>
                            <div class="card-body">
                                <?php if (empty($perfiles)): ?>
                                    <p class="mb-0 text-muted">No hay perfiles registrados.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>Descripcion</th>
                                                    <th>Usuarios</th>
                                                    <th>Creado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($perfiles as $itemPerfil): ?>
                                                    <?php
                                                    $idPerfil = (int) ($itemPerfil['id'] ?? 0);
                                                    $nombrePerfil = (string) ($itemPerfil['nombre'] ?? '');
                                                    $esBase = es_perfil_base_protegido($nombrePerfil);
                                                    $totalUsuarios = (int) ($itemPerfil['total_usuarios'] ?? 0);
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($nombrePerfil, ENT_QUOTES, 'UTF-8'); ?>
                                                            <?php if ($esBase): ?>
                                                                <span class="badge bg-secondary ms-1">Base</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars((string) ($itemPerfil['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo $totalUsuarios; ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($itemPerfil['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td class="d-flex gap-2">
                                                            <a class="btn btn-outline-primary btn-sm" href="/admin/perfiles?id=<?php echo $idPerfil; ?>">Editar</a>
                                                            <?php if (!$esBase && $totalUsuarios === 0): ?>
                                                                <form action="/admin/acciones/perfiles" method="post" class="m-0">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPerfiles, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="accion" value="eliminar" />
                                                                    <input type="hidden" name="perfil_id" value="<?php echo $idPerfil; ?>" />
                                                                    <button class="btn btn-outline-danger btn-sm js-eliminar-perfil" type="submit">Eliminar</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Bloqueado</button>
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

                        <?php if ($perfilEdicion !== null): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-pen-to-square me-1"></i>
                                    Editar perfil: <?php echo htmlspecialchars((string) ($perfilEdicion['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $nombreEdicion = (string) ($perfilEdicion['nombre'] ?? '');
                                    $esBaseEdicion = es_perfil_base_protegido($nombreEdicion);
                                    ?>
                                    <form action="/admin/acciones/perfiles" method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPerfiles, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="editar" />
                                        <input type="hidden" name="perfil_id" value="<?php echo (int) ($perfilEdicion['id'] ?? 0); ?>" />
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label" for="edit_nombre">Nombre (codigo)</label>
                                                <input
                                                    class="form-control"
                                                    id="edit_nombre"
                                                    name="nombre"
                                                    type="text"
                                                    minlength="3"
                                                    maxlength="50"
                                                    pattern="[a-z0-9_]{3,50}"
                                                    value="<?php echo htmlspecialchars($nombreEdicion, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $esBaseEdicion ? 'readonly' : ''; ?>
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label" for="edit_descripcion">Descripcion</label>
                                                <input
                                                    class="form-control"
                                                    id="edit_descripcion"
                                                    name="descripcion"
                                                    type="text"
                                                    maxlength="150"
                                                    value="<?php echo htmlspecialchars((string) ($perfilEdicion['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                />
                                            </div>
                                            <div class="col-12 d-flex gap-2">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Guardar cambios
                                                </button>
                                                <a class="btn btn-outline-secondary" href="/admin/perfiles">Cancelar</a>
                                            </div>
                                        </div>
                                    </form>

                                    <?php if ($sesionEsAdmin): ?>
                                        <hr class="my-4" />

                                        <h5 class="mb-3">Modulos aprobados para este perfil</h5>
                                        <form action="/admin/acciones/perfiles" method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPerfiles, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="actualizar_modulos" />
                                            <input type="hidden" name="perfil_id" value="<?php echo (int) ($perfilEdicion['id'] ?? 0); ?>" />
                                            <div class="row">
                                                <?php foreach ($modulosDisponibles as $modulo): ?>
                                                    <?php
                                                    $moduloId = (int) ($modulo['id'] ?? 0);
                                                    $nombreModulo = (string) ($modulo['nombre_modulo'] ?? '');
                                                    $estadoModulo = strtolower(trim((string) ($modulo['estado'] ?? 'activo')));
                                                    $esActivo = $estadoModulo === 'activo';
                                                    $checked = isset($modulosPerfilSeleccionado[$moduloId]);
                                                    if (strtolower(trim($nombreEdicion)) === 'admin' && $esActivo) {
                                                        $checked = true;
                                                    }
                                                    ?>
                                                    <div class="col-md-4 mb-2">
                                                        <div class="form-check border rounded p-2">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                id="perfil_modulo_<?php echo $moduloId; ?>"
                                                                name="modulos[]"
                                                                value="<?php echo $moduloId; ?>"
                                                                <?php echo $checked ? 'checked' : ''; ?>
                                                                <?php echo (strtolower(trim($nombreEdicion)) === 'admin' && $esActivo) ? 'disabled' : ''; ?>
                                                            />
                                                            <label class="form-check-label" for="perfil_modulo_<?php echo $moduloId; ?>">
                                                                <strong><?php echo htmlspecialchars($nombreModulo, ENT_QUOTES, 'UTF-8'); ?></strong><br />
                                                                <small><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small><br />
                                                                <span class="badge <?php echo $esActivo ? 'bg-success' : 'bg-secondary'; ?>">
                                                                    <?php echo $esActivo ? 'Activo' : 'Inactivo'; ?>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="mt-3 d-flex gap-2">
                                                <button class="btn btn-outline-primary" type="submit">
                                                    <i class="fas fa-shield-halved me-1"></i>Guardar modulos
                                                </button>
                                                <?php if (strtolower(trim($nombreEdicion)) === 'admin'): ?>
                                                    <div class="text-muted small align-self-center">
                                                        El perfil ADMIN conserva todos los modulos activos por defecto.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </form>
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="/admin/js/scripts.js?v=20260613e"></script>
        <script>
            (function () {
                var botonesEliminar = document.querySelectorAll('.js-eliminar-perfil');
                if (!botonesEliminar.length) {
                    return;
                }

                botonesEliminar.forEach(function (boton) {
                    boton.addEventListener('click', function (event) {
                        event.preventDefault();
                        var form = boton.closest('form');
                        if (!form) {
                            return;
                        }

                        Swal.fire({
                            title: 'Eliminar perfil',
                            text: 'Esta accion no se puede deshacer. Deseas continuar?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Si, eliminar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d'
                        }).then(function (result) {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    });
                });
            })();
        </script>
    </body>
</html>
