<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

require_modulo('gestionar_nosotros');
global $pdo;
asegurar_tabla_nosotros($pdo);

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

$itemsNosotros = $pdo->query(
    'SELECT id, titulo, descripcion_1, descripcion_2, descripcion_3, imagen_path, orden, activo
     FROM nosotros
     ORDER BY orden ASC, id ASC'
)->fetchAll();

$vista = trim((string) ($_GET['vista'] ?? ''));
if ($vista !== 'crear' && $vista !== 'editar') {
    $vista = isset($_GET['id']) ? 'editar' : 'crear';
}

$soloPermiteUnRegistro = !empty($itemsNosotros);
if ($soloPermiteUnRegistro && $vista === 'crear') {
    $vista = 'editar';
}

$itemId = (int) ($_GET['id'] ?? 0);
if ($vista === 'editar' && $itemId <= 0 && !empty($itemsNosotros)) {
    $itemId = (int) $itemsNosotros[0]['id'];
}

$itemEdicion = null;
foreach ($itemsNosotros as $item) {
    if ((int) $item['id'] === $itemId) {
        $itemEdicion = $item;
        break;
    }
}

if ($vista === 'editar' && $itemEdicion === null && !empty($itemsNosotros)) {
    $itemEdicion = $itemsNosotros[0];
    $itemId = (int) $itemEdicion['id'];
}

if ($vista === 'editar' && empty($itemsNosotros)) {
    $vista = 'crear';
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'creado') {
    $mensaje = 'Contenido de Nosotros creado correctamente.';
} elseif ($ok === 'actualizado') {
    $mensaje = 'Contenido de Nosotros actualizado correctamente.';
} elseif ($ok === 'eliminado') {
    $mensaje = 'Contenido de Nosotros eliminado correctamente.';
}

$errores = [
    'titulo' => 'El titulo es obligatorio y no puede exceder 120 caracteres.',
    'descripcion_1' => 'El primer parrafo es obligatorio y no puede exceder 2000 caracteres.',
    'descripcion_2' => 'El segundo parrafo no puede exceder 2000 caracteres.',
    'descripcion_3' => 'El tercer parrafo no puede exceder 2000 caracteres.',
    'imagen_tamano' => 'No se pudo procesar la imagen seleccionada.',
    'imagen_tipo' => 'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.',
    'imagen_subida' => 'No se pudo subir la imagen.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'solo_uno' => 'Solo se permite un bloque en Nosotros. Edita el registro existente.',
    'id' => 'No se encontro el registro seleccionado.',
    'db' => 'No se pudo guardar por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($errores[$error])) {
    $mensaje = $errores[$error];
    $tipoMensaje = 'danger';
}
$csrfNosotros = csrf_token('admin_nosotros');

function ruta_imagen_nosotros_admin(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return '../../assets/img/fondoSietelsa.png';
    }

    return '../../' . ltrim(str_replace('\\', '/', $ruta), '/');
}

function resumen_nosotros(string $texto, int $max = 90): string
{
    $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    if (strlen($texto) <= $max) {
        return $texto;
    }
    return substr($texto, 0, $max - 3) . '...';
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestion de Nosotros - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Gestionar Nosotros | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .nosotros-preview {
                display: grid;
                grid-template-columns: 1.2fr 1fr;
                border: 1px solid #e9ecef;
                border-radius: 0.9rem;
                overflow: hidden;
                background: #f8f9fa;
                min-height: 240px;
            }
            .nosotros-preview-copy {
                padding: 1.25rem 1.25rem 1rem;
                background: #f7f8fa;
            }
            .nosotros-preview-title {
                font-size: 1.55rem;
                font-weight: 800;
                letter-spacing: 0.03em;
                color: #0b2063;
                margin-bottom: 0.35rem;
                text-transform: uppercase;
            }
            .nosotros-preview-accent {
                width: 120px;
                height: 6px;
                border-radius: 999px;
                background: #d81f30;
                display: block;
                margin-bottom: 1rem;
            }
            .nosotros-preview-copy p {
                margin-bottom: 0.8rem;
                color: #4f5660;
            }
            .nosotros-preview-media {
                min-height: 240px;
                background: #dee2e6;
            }
            .nosotros-preview-media img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            @media (max-width: 991.98px) {
                .nosotros-preview {
                    grid-template-columns: 1fr;
                }
                .nosotros-preview-media {
                    min-height: 190px;
                }
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
                            <h1>Gestionar Nosotros del index</h1>
                            <div class="d-flex gap-2">
                                <?php if (!$soloPermiteUnRegistro): ?>
                                    <a class="btn btn-sm <?php echo $vista === 'crear' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/nosotros?vista=crear">
                                        <i class="fas fa-plus me-1"></i>Nuevo bloque
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled>
                                        <i class="fas fa-lock me-1"></i>Solo 1 bloque permitido
                                    </button>
                                <?php endif; ?>
                                <a class="btn btn-sm <?php echo $vista === 'editar' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/nosotros?vista=editar<?php echo $itemId > 0 ? '&id=' . $itemId : ''; ?>">
                                    <i class="fas fa-pen me-1"></i>Editar bloque
                                </a>
                            </div>
                        </div>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Nosotros</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($vista === 'crear' && !$soloPermiteUnRegistro): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-plus me-1"></i>
                                    Nuevo bloque de Nosotros
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/nosotros" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfNosotros, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="crear" />
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="titulo">Titulo</label>
                                                <input class="form-control" id="titulo" name="titulo" type="text" maxlength="120" value="NOSOTROS" required />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="imagen">Imagen lateral (opcional)</label>
                                                <input class="form-control" id="imagen" name="imagen" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-max-filesize="5242880" />
                                                <div class="form-text">Si no subes imagen, se usa la imagen actual por defecto.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_1">Parrafo 1</label>
                                                <textarea class="form-control" id="descripcion_1" name="descripcion_1" rows="3" maxlength="2000" required></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_2">Parrafo 2 (opcional)</label>
                                                <textarea class="form-control" id="descripcion_2" name="descripcion_2" rows="3" maxlength="2000"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_3">Parrafo 3 (opcional)</label>
                                                <textarea class="form-control" id="descripcion_3" name="descripcion_3" rows="2" maxlength="2000"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="activo" name="activo" type="checkbox" value="1" checked />
                                                    <label class="form-check-label" for="activo">Mostrar en index</label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Guardar bloque
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-list me-1"></i>
                                Bloques registrados
                            </div>
                            <div class="card-body">
                                <?php if (empty($itemsNosotros)): ?>
                                    <p class="mb-0 text-muted">No hay bloques creados todavia.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Titulo</th>
                                                    <th>Resumen</th>
                                                    <th>Estado</th>
                                                    <th>Accion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($itemsNosotros as $fila): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars((string) ($fila['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars(resumen_nosotros((string) ($fila['descripcion_1'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <?php if ((int) ($fila['activo'] ?? 0) === 1): ?>
                                                                <span class="badge bg-success">Visible</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Oculto</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a class="btn btn-outline-primary btn-sm" href="/admin/nosotros?vista=editar&id=<?php echo (int) $fila['id']; ?>">Editar</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($vista === 'editar' && $itemEdicion !== null): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-pen me-1"></i>
                                    Editando bloque: <?php echo htmlspecialchars((string) ($itemEdicion['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/nosotros" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfNosotros, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="editar" />
                                        <input type="hidden" name="nosotros_id" value="<?php echo (int) ($itemEdicion['id'] ?? 0); ?>" />
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="titulo_editar">Titulo</label>
                                                <input
                                                    class="form-control"
                                                    id="titulo_editar"
                                                    name="titulo"
                                                    type="text"
                                                    maxlength="120"
                                                    value="<?php echo htmlspecialchars((string) ($itemEdicion['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="imagen_editar">Cambiar imagen lateral (opcional)</label>
                                                <input
                                                    class="form-control"
                                                    id="imagen_editar"
                                                    name="imagen"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp,image/gif"
                                                    data-max-filesize="5242880"
                                                />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_1_editar">Parrafo 1</label>
                                                <textarea class="form-control" id="descripcion_1_editar" name="descripcion_1" rows="3" maxlength="2000" required><?php echo htmlspecialchars((string) ($itemEdicion['descripcion_1'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_2_editar">Parrafo 2 (opcional)</label>
                                                <textarea class="form-control" id="descripcion_2_editar" name="descripcion_2" rows="3" maxlength="2000"><?php echo htmlspecialchars((string) ($itemEdicion['descripcion_2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_3_editar">Parrafo 3 (opcional)</label>
                                                <textarea class="form-control" id="descripcion_3_editar" name="descripcion_3" rows="2" maxlength="2000"><?php echo htmlspecialchars((string) ($itemEdicion['descripcion_3'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" id="activo_editar" name="activo" type="checkbox" value="1" <?php echo ((int) ($itemEdicion['activo'] ?? 0) === 1) ? 'checked' : ''; ?> />
                                                    <label class="form-check-label" for="activo_editar">Mostrar en index</label>
                                                </div>
                                                <?php if (trim((string) ($itemEdicion['imagen_path'] ?? '')) !== ''): ?>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" id="eliminar_imagen" name="eliminar_imagen" type="checkbox" value="1" />
                                                        <label class="form-check-label" for="eliminar_imagen">Eliminar imagen actual</label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Actualizar bloque
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="mt-4">
                                        <h6 class="text-muted text-uppercase mb-2">Vista previa</h6>
                                        <div class="nosotros-preview">
                                            <div class="nosotros-preview-copy">
                                                <div class="nosotros-preview-title"><?php echo htmlspecialchars((string) ($itemEdicion['titulo'] ?? 'NOSOTROS'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <span class="nosotros-preview-accent"></span>
                                                <p><?php echo nl2br(htmlspecialchars((string) ($itemEdicion['descripcion_1'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                                                <?php if (trim((string) ($itemEdicion['descripcion_2'] ?? '')) !== ''): ?>
                                                    <p><?php echo nl2br(htmlspecialchars((string) ($itemEdicion['descripcion_2'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                                                <?php endif; ?>
                                                <?php if (trim((string) ($itemEdicion['descripcion_3'] ?? '')) !== ''): ?>
                                                    <p><?php echo nl2br(htmlspecialchars((string) ($itemEdicion['descripcion_3'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="nosotros-preview-media">
                                                <img src="<?php echo htmlspecialchars(ruta_imagen_nosotros_admin((string) ($itemEdicion['imagen_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" alt="Vista previa Nosotros" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-top pt-3 mt-3">
                                        <form action="/admin/acciones/nosotros" method="post" data-confirm-message="Esta accion eliminara el bloque de Nosotros. Deseas continuar?">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfNosotros, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="eliminar" />
                                            <input type="hidden" name="nosotros_id" value="<?php echo (int) ($itemEdicion['id'] ?? 0); ?>" />
                                            <button class="btn btn-outline-danger" type="submit">
                                                <i class="fas fa-trash me-1"></i>Eliminar bloque
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($vista === 'editar'): ?>
                            <div class="alert alert-info" role="alert">
                                No hay bloques disponibles para editar.
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

