<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';
require_once __DIR__ . '/../../include/servicios_iconos.php';

require_modulo('gestionar_servicios');
global $pdo;
asegurar_tabla_servicios($pdo);

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

$servicios = $pdo->query(
    'SELECT id, titulo, icono, orden, activo
     FROM servicios
     ORDER BY orden ASC, id ASC'
)->fetchAll();

$vista = trim((string) ($_GET['vista'] ?? ''));
if ($vista !== 'crear' && $vista !== 'editar') {
    $vista = isset($_GET['id']) ? 'editar' : 'crear';
}

$servicioId = (int) ($_GET['id'] ?? 0);
if ($vista === 'editar' && $servicioId <= 0 && !empty($servicios)) {
    $servicioId = (int) $servicios[0]['id'];
}

$servicioEdicion = null;
foreach ($servicios as $servicio) {
    if ((int) $servicio['id'] === $servicioId) {
        $servicioEdicion = $servicio;
        break;
    }
}

if ($vista === 'editar' && $servicioEdicion === null && !empty($servicios)) {
    $servicioEdicion = $servicios[0];
    $servicioId = (int) $servicioEdicion['id'];
}

if ($vista === 'editar' && empty($servicios)) {
    $vista = 'crear';
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'creado') {
    $mensaje = 'Servicio creado correctamente.';
} elseif ($ok === 'actualizado') {
    $mensaje = 'Servicio actualizado correctamente.';
} elseif ($ok === 'eliminado') {
    $mensaje = 'Servicio eliminado correctamente.';
}

$errores = [
    'campos' => 'Completa los campos obligatorios.',
    'titulo' => 'El titulo es obligatorio y no puede exceder 120 caracteres.',
    'icono' => 'Selecciona un icono valido de la lista.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'id' => 'No se encontro el servicio seleccionado.',
    'db' => 'No se pudo guardar el servicio por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($errores[$error])) {
    $mensaje = $errores[$error];
    $tipoMensaje = 'danger';
}
$csrfServicios = csrf_token('admin_servicios');

$iconosDisponibles = obtener_iconos_servicio_disponibles();
$iconosDisponiblesEdicion = $iconosDisponibles;
if ($servicioEdicion !== null) {
    $iconoActualEdicion = normalizar_icono_servicio_lista((string) ($servicioEdicion['icono'] ?? ''));
    if ($iconoActualEdicion !== '' && !isset($iconosDisponiblesEdicion[$iconoActualEdicion])) {
        $iconosDisponiblesEdicion[$iconoActualEdicion] = 'Icono actual (' . $iconoActualEdicion . ')';
    }
}

$iconoCrearInicial = 'fa-circle-info';
if (!isset($iconosDisponibles[$iconoCrearInicial])) {
    reset($iconosDisponibles);
    $iconoCrearInicial = (string) key($iconosDisponibles);
}

$iconoEdicionInicial = $iconoCrearInicial;
if ($servicioEdicion !== null) {
    $iconoEdicionInicial = normalizar_icono_servicio_lista((string) ($servicioEdicion['icono'] ?? ''));
    if ($iconoEdicionInicial === '' || !isset($iconosDisponiblesEdicion[$iconoEdicionInicial])) {
        $iconoEdicionInicial = $iconoCrearInicial;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestion de servicios - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Gestionar Servicios | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .icon-options-list {
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                background: #fff;
                max-height: 360px;
                overflow: auto;
            }
            .icon-choice {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.65rem 0.85rem;
                border-bottom: 1px solid #f1f3f5;
                cursor: pointer;
                margin: 0;
            }
            .icon-choice:last-child {
                border-bottom: none;
            }
            .icon-choice.selected {
                background: #fff8e1;
            }
            .icon-choice input[type='radio'] {
                margin-top: 0;
                flex-shrink: 0;
            }
            .icon-choice-icon {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                background: #ffc107;
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                flex-shrink: 0;
            }
            .form-section-title {
                font-size: 0.85rem;
                letter-spacing: 0.04em;
                color: #6c757d;
                text-transform: uppercase;
                margin-bottom: 0.75rem;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
                <?php render_admin_topnav($pdo, $usuario, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, isset($usuario) && is_array($usuario) ? $usuario : (isset($usuarioSesion) && is_array($usuarioSesion) ? $usuarioSesion : []), $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <h1>Gestionar servicios del index</h1>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm <?php echo $vista === 'crear' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/servicios?vista=crear">
                                    <i class="fas fa-plus me-1"></i>Nuevo servicio
                                </a>
                                <a class="btn btn-sm <?php echo $vista === 'editar' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/servicios?vista=editar<?php echo $servicioId > 0 ? '&id=' . $servicioId : ''; ?>">
                                    <i class="fas fa-pen me-1"></i>Editar servicio
                                </a>
                            </div>
                        </div>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Servicios</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($vista === 'crear'): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-plus me-1"></i>
                                    Nuevo servicio
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/servicios" method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfServicios, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="crear" />
                                        <div class="row g-4 align-items-start">
                                            <div class="col-lg-7">
                                                <div class="form-section-title">Datos del servicio</div>
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label" for="titulo">Titulo</label>
                                                        <input class="form-control" id="titulo" name="titulo" type="text" maxlength="120" required />
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" id="activo" name="activo" type="checkbox" value="1" checked />
                                                            <label class="form-check-label" for="activo">Mostrar en index</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="fas fa-save me-1"></i>Guardar servicio
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="form-section-title">Seleccion de icono</div>
                                                <div class="icon-options-list" data-icon-list="crear">
                                                    <?php foreach ($iconosDisponibles as $valorIcono => $etiquetaIcono): ?>
                                                        <label class="icon-choice">
                                                            <input
                                                                class="form-check-input"
                                                                type="radio"
                                                                name="icono"
                                                                value="<?php echo htmlspecialchars($valorIcono, ENT_QUOTES, 'UTF-8'); ?>"
                                                                <?php echo $valorIcono === $iconoCrearInicial ? 'checked' : ''; ?>
                                                                required
                                                            />
                                                            <span class="icon-choice-icon"><i class="fas <?php echo htmlspecialchars($valorIcono, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                                            <span><?php echo htmlspecialchars($etiquetaIcono, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-list me-1"></i>
                                Servicios registrados
                            </div>
                            <div class="card-body">
                                <?php if (empty($servicios)): ?>
                                    <p class="mb-0 text-muted">No hay servicios creados todavia.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Icono</th>
                                                    <th>Titulo</th>
                                                    <th>Estado</th>
                                                    <th>Accion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($servicios as $filaServicio): ?>
                                                    <tr>
                                                        <td>
                                                            <i class="fas <?php echo htmlspecialchars((string) $filaServicio['icono'], ENT_QUOTES, 'UTF-8'); ?> me-2"></i>
                                                            <small><?php echo htmlspecialchars((string) $filaServicio['icono'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars((string) $filaServicio['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <?php if ((int) $filaServicio['activo'] === 1): ?>
                                                                <span class="badge bg-success">Visible</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Oculto</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a class="btn btn-outline-primary btn-sm" href="/admin/servicios?vista=editar&id=<?php echo (int) $filaServicio['id']; ?>">Editar</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($vista === 'editar' && $servicioEdicion !== null): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-pen me-1"></i>
                                    Editando servicio: <?php echo htmlspecialchars((string) $servicioEdicion['titulo'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/servicios" method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfServicios, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="editar" />
                                        <input type="hidden" name="servicio_id" value="<?php echo (int) $servicioEdicion['id']; ?>" />
                                        <div class="row g-4 align-items-start">
                                            <div class="col-lg-7">
                                                <div class="form-section-title">Datos del servicio</div>
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label" for="titulo_editar">Titulo</label>
                                                        <input
                                                            class="form-control"
                                                            id="titulo_editar"
                                                            name="titulo"
                                                            type="text"
                                                            maxlength="120"
                                                            value="<?php echo htmlspecialchars((string) $servicioEdicion['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" id="activo_editar" name="activo" type="checkbox" value="1" <?php echo ((int) $servicioEdicion['activo'] === 1) ? 'checked' : ''; ?> />
                                                            <label class="form-check-label" for="activo_editar">Mostrar en index</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 d-flex flex-wrap gap-2">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="fas fa-save me-1"></i>Actualizar servicio
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="form-section-title">Seleccion de icono</div>
                                                <div class="icon-options-list" data-icon-list="editar">
                                                    <?php foreach ($iconosDisponiblesEdicion as $valorIcono => $etiquetaIcono): ?>
                                                        <label class="icon-choice">
                                                            <input
                                                                class="form-check-input"
                                                                type="radio"
                                                                name="icono"
                                                                value="<?php echo htmlspecialchars($valorIcono, ENT_QUOTES, 'UTF-8'); ?>"
                                                                <?php echo $iconoEdicionInicial === $valorIcono ? 'checked' : ''; ?>
                                                                required
                                                            />
                                                            <span class="icon-choice-icon"><i class="fas <?php echo htmlspecialchars($valorIcono, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                                            <span><?php echo htmlspecialchars($etiquetaIcono, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="border-top pt-3 mt-3">
                                        <form action="/admin/acciones/servicios" method="post" data-confirm-message="Esta accion eliminara el servicio. Deseas continuar?">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfServicios, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="eliminar" />
                                            <input type="hidden" name="servicio_id" value="<?php echo (int) $servicioEdicion['id']; ?>" />
                                            <button class="btn btn-outline-danger" type="submit">
                                                <i class="fas fa-trash me-1"></i>Eliminar servicio
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($vista === 'editar'): ?>
                            <div class="alert alert-info" role="alert">
                                No hay servicios disponibles para editar.
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
        <script>
            (function () {
                function marcarSeleccionados() {
                    var lists = document.querySelectorAll('[data-icon-list]');
                    lists.forEach(function (list) {
                        var labels = list.querySelectorAll('.icon-choice');
                        labels.forEach(function (label) {
                            var radio = label.querySelector('input[type="radio"]');
                            label.classList.toggle('selected', !!(radio && radio.checked));
                        });
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    marcarSeleccionados();
                    document.addEventListener('change', function (event) {
                        if (event.target && event.target.matches('input[type="radio"][name="icono"]')) {
                            marcarSeleccionados();
                        }
                    });
                });
            })();
        </script>
    </body>
</html>

