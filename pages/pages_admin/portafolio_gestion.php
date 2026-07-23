<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

require_modulo('gestionar_portafolio');
global $pdo;
asegurar_tabla_portafolio($pdo);

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

$itemsPortafolio = $pdo->query(
    'SELECT p.id, p.titulo, p.imagen_path, p.orden, p.activo, COUNT(pi.id) AS total_imagenes
     FROM portafolio p
     LEFT JOIN portafolio_imagenes pi ON pi.portafolio_id = p.id
     GROUP BY p.id, p.titulo, p.imagen_path, p.orden, p.activo
     ORDER BY p.orden ASC, p.id ASC'
)->fetchAll();

$vista = trim((string) ($_GET['vista'] ?? ''));
if ($vista !== 'crear' && $vista !== 'editar') {
    $vista = isset($_GET['id']) ? 'editar' : 'crear';
}

$itemId = (int) ($_GET['id'] ?? 0);
if ($vista === 'editar' && $itemId <= 0 && !empty($itemsPortafolio)) {
    $itemId = (int) $itemsPortafolio[0]['id'];
}

$itemEdicion = null;
foreach ($itemsPortafolio as $item) {
    if ((int) $item['id'] === $itemId) {
        $itemEdicion = $item;
        break;
    }
}

if ($vista === 'editar' && $itemEdicion === null && !empty($itemsPortafolio)) {
    $itemEdicion = $itemsPortafolio[0];
    $itemId = (int) $itemEdicion['id'];
}

if ($vista === 'editar' && empty($itemsPortafolio)) {
    $vista = 'crear';
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'creado') {
    $mensaje = 'Elemento del portafolio creado correctamente.';
} elseif ($ok === 'actualizado') {
    $mensaje = 'Elemento del portafolio actualizado correctamente.';
} elseif ($ok === 'eliminado') {
    $mensaje = 'Elemento del portafolio eliminado correctamente.';
}

$errores = [
    'campos' => 'Completa los campos obligatorios.',
    'titulo' => 'El titulo es obligatorio y no puede exceder 120 caracteres.',
    'descripcion' => 'La descripcion no puede exceder 1200 caracteres.',
    'imagen_requerida' => 'Debes seleccionar una imagen.',
    'imagen_tamano' => 'La imagen supera el limite permitido (maximo 5 MB).',
    'imagen_tipo' => 'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.',
    'imagen_subida' => 'No se pudo subir la imagen.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'id' => 'No se encontro el elemento seleccionado.',
    'db' => 'No se pudo guardar por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($errores[$error])) {
    $mensaje = $errores[$error];
    $tipoMensaje = 'danger';
}
$csrfPortafolio = csrf_token('admin_portafolio');

function ruta_imagen_portafolio_admin(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return '../../assets/img/portfolio/1.jpg';
    }

    return '../../' . ltrim(str_replace('\\', '/', $ruta), '/');
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestion de portafolio - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Gestionar Portafolio | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .portfolio-thumb {
                width: 86px;
                height: 58px;
                object-fit: cover;
                border-radius: 0.4rem;
                border: 1px solid #dee2e6;
                background: #f8f9fa;
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
                            <h1>Gestionar portafolio del index</h1>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm <?php echo $vista === 'crear' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/portafolio?vista=crear">
                                    <i class="fas fa-plus me-1"></i>Nuevo elemento
                                </a>
                                <a class="btn btn-sm <?php echo $vista === 'editar' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/portafolio?vista=editar<?php echo $itemId > 0 ? '&id=' . $itemId : ''; ?>">
                                    <i class="fas fa-pen me-1"></i>Editar elemento
                                </a>
                            </div>
                        </div>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Portafolio</li>
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
                                    Nuevo elemento del portafolio
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/portafolio" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPortafolio, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="crear" />
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="titulo">Titulo</label>
                                                <input class="form-control" id="titulo" name="titulo" type="text" maxlength="120" required />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="imagenes">Imagenes del carrusel</label>
                                                <input class="form-control" id="imagenes" name="imagenes[]" type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple required data-max-filesize="5242880" />
                                                <div class="form-text">Puedes seleccionar varias imagenes. Formatos: JPG, PNG, WEBP o GIF. Maximo 5 MB por archivo.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion">Descripcion (opcional)</label>
                                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="1200"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="activo" name="activo" type="checkbox" value="1" checked />
                                                    <label class="form-check-label" for="activo">Mostrar en index</label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Guardar elemento
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
                                Elementos registrados
                            </div>
                            <div class="card-body">
                                <?php if (empty($itemsPortafolio)): ?>
                                    <p class="mb-0 text-muted">No hay elementos creados todavia.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Imagen</th>
                                                    <th>Titulo</th>
                                                    <th>Imagenes</th>
                                                    <th>Estado</th>
                                                    <th>Accion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($itemsPortafolio as $fila): ?>
                                                    <tr>
                                                        <td>
                                                            <img
                                                                class="portfolio-thumb"
                                                                src="<?php echo htmlspecialchars(ruta_imagen_portafolio_admin((string) ($fila['imagen_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                                alt="<?php echo htmlspecialchars((string) ($fila['titulo'] ?? 'Elemento portafolio'), ENT_QUOTES, 'UTF-8'); ?>"
                                                            />
                                                        </td>
                                                        <td><?php echo htmlspecialchars((string) ($fila['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <span class="badge bg-info text-dark"><?php echo (int) ($fila['total_imagenes'] ?? 0); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ((int) $fila['activo'] === 1): ?>
                                                                <span class="badge bg-success">Visible</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Oculto</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a class="btn btn-outline-primary btn-sm" href="/admin/portafolio?vista=editar&id=<?php echo (int) $fila['id']; ?>">Editar</a>
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
                            <?php
                            $stmtDetalle = $pdo->prepare(
                                'SELECT id, titulo, descripcion, imagen_path, activo
                                 FROM portafolio
                                 WHERE id = :id
                                 LIMIT 1'
                            );
                            $stmtDetalle->execute([':id' => (int) $itemEdicion['id']]);
                            $detalle = $stmtDetalle->fetch();
                            if (!$detalle) {
                                $detalle = $itemEdicion;
                            }

                            $stmtImagenesDetalle = $pdo->prepare(
                                'SELECT id, imagen_path
                                 FROM portafolio_imagenes
                                 WHERE portafolio_id = :portafolio_id
                                 ORDER BY orden ASC, id ASC'
                            );
                            $stmtImagenesDetalle->execute([':portafolio_id' => (int) ($detalle['id'] ?? 0)]);
                            $imagenesDetalle = $stmtImagenesDetalle->fetchAll();

                            if (empty($imagenesDetalle) && !empty($detalle['imagen_path'])) {
                                $imagenesDetalle = [
                                    [
                                        'id' => 0,
                                        'imagen_path' => (string) $detalle['imagen_path'],
                                    ],
                                ];
                            }
                            ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-pen me-1"></i>
                                    Editando elemento: <?php echo htmlspecialchars((string) ($detalle['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/portafolio" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPortafolio, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="editar" />
                                        <input type="hidden" name="portafolio_id" value="<?php echo (int) ($detalle['id'] ?? 0); ?>" />
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="titulo_editar">Titulo</label>
                                                <input
                                                    class="form-control"
                                                    id="titulo_editar"
                                                    name="titulo"
                                                    type="text"
                                                    maxlength="120"
                                                    value="<?php echo htmlspecialchars((string) ($detalle['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="imagenes_editar">Agregar imagenes al carrusel (opcional)</label>
                                                <input
                                                    class="form-control"
                                                    id="imagenes_editar"
                                                    name="imagenes[]"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp,image/gif"
                                                    multiple
                                                    data-max-filesize="5242880"
                                                />
                                                <div class="form-text">Puedes agregar varias imagenes nuevas. Las actuales se mantienen.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label d-block">Galeria actual</label>
                                                <?php if (empty($imagenesDetalle)): ?>
                                                    <p class="text-muted mb-0">No hay imagenes cargadas.</p>
                                                <?php else: ?>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php foreach ($imagenesDetalle as $imagenItem): ?>
                                                            <img
                                                                class="portfolio-thumb"
                                                                src="<?php echo htmlspecialchars(ruta_imagen_portafolio_admin((string) ($imagenItem['imagen_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                                alt="<?php echo htmlspecialchars((string) ($detalle['titulo'] ?? 'Elemento portafolio'), ENT_QUOTES, 'UTF-8'); ?>"
                                                            />
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="descripcion_editar">Descripcion (opcional)</label>
                                                <textarea class="form-control" id="descripcion_editar" name="descripcion" rows="3" maxlength="1200"><?php echo htmlspecialchars((string) ($detalle['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="activo_editar" name="activo" type="checkbox" value="1" <?php echo ((int) ($detalle['activo'] ?? 0) === 1) ? 'checked' : ''; ?> />
                                                    <label class="form-check-label" for="activo_editar">Mostrar en index</label>
                                                </div>
                                            </div>
                                            <div class="col-12 d-flex flex-wrap gap-2">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Actualizar elemento
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="border-top pt-3 mt-3">
                                        <form action="/admin/acciones/portafolio" method="post" data-confirm-message="Esta accion eliminara el elemento del portafolio. Deseas continuar?">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPortafolio, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="eliminar" />
                                            <input type="hidden" name="portafolio_id" value="<?php echo (int) ($detalle['id'] ?? 0); ?>" />
                                            <button class="btn btn-outline-danger" type="submit">
                                                <i class="fas fa-trash me-1"></i>Eliminar elemento
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($vista === 'editar'): ?>
                            <div class="alert alert-info" role="alert">
                                No hay elementos disponibles para editar.
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

