<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

require_modulo('gestionar_proyectos');
global $pdo;
asegurar_tabla_proyectos($pdo);

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

$categoriasPortafolio = $pdo->query(
    'SELECT id, titulo
     FROM portafolio
     ORDER BY orden ASC, id ASC'
)->fetchAll();
$categoriasMap = [];
foreach ($categoriasPortafolio as $categoriaPortafolio) {
    $categoriasMap[(int) ($categoriaPortafolio['id'] ?? 0)] = (string) ($categoriaPortafolio['titulo'] ?? '');
}

$proyectos = $pdo->query(
    'SELECT pr.id,
            pr.titulo,
            pr.portafolio_id,
            pr.imagen_path,
            pr.orden,
            pr.activo,
            pf.titulo AS categoria_titulo
     FROM proyectos pr
     LEFT JOIN portafolio pf ON pf.id = pr.portafolio_id
     ORDER BY pr.orden ASC, pr.id ASC'
)->fetchAll();
$maximoProyectos = 6;
$limiteProyectosAlcanzado = count($proyectos) >= $maximoProyectos;
$sinCategoriasPortafolio = empty($categoriasPortafolio);

$vista = trim((string) ($_GET['vista'] ?? ''));
if ($vista !== 'crear' && $vista !== 'editar') {
    $vista = isset($_GET['id']) ? 'editar' : 'crear';
}

$proyectoId = (int) ($_GET['id'] ?? 0);
if ($vista === 'editar' && $proyectoId <= 0 && !empty($proyectos)) {
    $proyectoId = (int) $proyectos[0]['id'];
}

$proyectoEdicion = null;
foreach ($proyectos as $proyecto) {
    if ((int) $proyecto['id'] === $proyectoId) {
        $proyectoEdicion = $proyecto;
        break;
    }
}

if ($vista === 'editar' && $proyectoEdicion === null && !empty($proyectos)) {
    $proyectoEdicion = $proyectos[0];
    $proyectoId = (int) $proyectoEdicion['id'];
}

if ($vista === 'editar' && empty($proyectos)) {
    $vista = 'crear';
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'creado') {
    $mensaje = 'Proyecto creado correctamente.';
} elseif ($ok === 'actualizado') {
    $mensaje = 'Proyecto actualizado correctamente.';
} elseif ($ok === 'eliminado') {
    $mensaje = 'Proyecto eliminado correctamente.';
}

$errores = [
    'campos' => 'Completa los campos obligatorios.',
    'titulo' => 'El titulo es obligatorio y no puede exceder 120 caracteres.',
    'categoria' => 'Selecciona una categoria valida del portafolio.',
    'imagen_requerida' => 'Debes seleccionar una imagen.',
    'imagen_tamano' => 'La imagen supera el limite permitido (maximo 5 MB).',
    'imagen_tipo' => 'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.',
    'imagen_subida' => 'No se pudo subir la imagen.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'limite' => 'Se alcanzo el limite maximo de 6 proyectos. Elimina uno para agregar otro.',
    'id' => 'No se encontro el proyecto seleccionado.',
    'db' => 'No se pudo guardar por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($errores[$error])) {
    $mensaje = $errores[$error];
    $tipoMensaje = 'danger';
}
$csrfProyectos = csrf_token('admin_proyectos');

function ruta_imagen_proyecto_admin(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return '../../assets/img/fondoSietelsa.png';
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
        <meta name="description" content="Gestion de proyectos - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Gestionar Proyectos | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .project-thumb {
                width: 110px;
                height: 72px;
                object-fit: cover;
                border-radius: 0.55rem;
                border: 1px solid #dee2e6;
                background: #f8f9fa;
            }
            .project-preview {
                border: 1px solid #e9ecef;
                border-radius: 0.9rem;
                overflow: hidden;
                background: #fff;
            }
            .project-preview-media {
                width: 100%;
                aspect-ratio: 16 / 9;
                object-fit: cover;
                display: block;
            }
            .project-preview-body {
                padding: 1rem 1.15rem;
            }
            .project-preview-title {
                margin: 0;
                font-size: 1.05rem;
                font-weight: 700;
                color: #122557;
            }
            .project-preview-cat {
                margin: 0.4rem 0 0;
                color: #6c757d;
                font-size: 0.9rem;
            }
            .index-preview-card {
                border-radius: 1.05rem;
                overflow: hidden;
                border: 1px solid rgba(15, 33, 90, 0.16);
                box-shadow: 0 14px 35px rgba(15, 33, 90, 0.12);
                background: #fff;
                max-width: 430px;
            }
            .index-preview-media-wrap {
                position: relative;
                overflow: hidden;
            }
            .index-preview-media-wrap::after {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(180deg, rgba(16, 33, 89, 0.02) 45%, rgba(16, 33, 89, 0.22) 100%);
                pointer-events: none;
            }
            .index-preview-media {
                width: 100%;
                height: 240px;
                object-fit: cover;
                display: block;
            }
            .index-preview-content {
                padding: 1.1rem 1.15rem 1.2rem;
            }
            .index-preview-title {
                margin: 0;
                font-size: 1.35rem;
                line-height: 1.2;
                font-weight: 700;
                color: #0f1f56;
            }
            .index-preview-category {
                margin: 0.6rem 0 0;
                font-size: 0.9rem;
                color: #d71f2d;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-weight: 700;
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
                            <h1>Gestionar proyectos del index</h1>
                            <div class="d-flex gap-2">
                                <?php if ($limiteProyectosAlcanzado): ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled>
                                        <i class="fas fa-lock me-1"></i>Maximo 6 proyectos
                                    </button>
                                <?php elseif ($sinCategoriasPortafolio): ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled>
                                        <i class="fas fa-triangle-exclamation me-1"></i>Sin categorias
                                    </button>
                                <?php else: ?>
                                    <a class="btn btn-sm <?php echo $vista === 'crear' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/proyectos?vista=crear">
                                        <i class="fas fa-plus me-1"></i>Nuevo proyecto
                                    </a>
                                <?php endif; ?>
                                <a class="btn btn-sm <?php echo $vista === 'editar' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/proyectos?vista=editar<?php echo $proyectoId > 0 ? '&id=' . $proyectoId : ''; ?>">
                                    <i class="fas fa-pen me-1"></i>Editar proyecto
                                </a>
                            </div>
                        </div>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Proyectos</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($limiteProyectosAlcanzado): ?>
                            <div class="alert alert-warning" role="alert">
                                Ya hay <?php echo (int) $maximoProyectos; ?> proyectos registrados. Para crear uno nuevo, elimina alguno existente.
                            </div>
                        <?php endif; ?>

                        <?php if ($sinCategoriasPortafolio): ?>
                            <div class="alert alert-warning" role="alert">
                                Debes crear al menos un elemento en Portafolio para usarlo como categoria de proyectos.
                            </div>
                        <?php endif; ?>

                        <?php if ($vista === 'crear' && !$limiteProyectosAlcanzado && !$sinCategoriasPortafolio): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-plus me-1"></i>
                                    Nuevo proyecto
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/proyectos" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfProyectos, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="crear" />
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="titulo">Titulo del proyecto</label>
                                                <input class="form-control" id="titulo" name="titulo" type="text" maxlength="120" required />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="portafolio_id">Categoria (desde portafolio)</label>
                                                <select class="form-select" id="portafolio_id" name="portafolio_id" required>
                                                    <option value="">Selecciona una categoria...</option>
                                                    <?php foreach ($categoriasPortafolio as $categoriaPortafolio): ?>
                                                        <option value="<?php echo (int) ($categoriaPortafolio['id'] ?? 0); ?>">
                                                            <?php echo htmlspecialchars((string) ($categoriaPortafolio['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label" for="imagen">Imagen principal</label>
                                                <input class="form-control" id="imagen" name="imagen" type="file" accept="image/png,image/jpeg,image/webp,image/gif" required data-max-filesize="5242880" />
                                                <div class="form-text">Formatos permitidos: JPG, PNG, WEBP o GIF. Maximo 5 MB.</div>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="activo" name="activo" type="checkbox" value="1" checked />
                                                    <label class="form-check-label" for="activo">Mostrar en index</label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label mb-2">Vista previa</label>
                                                <div class="index-preview-card">
                                                    <div class="index-preview-media-wrap">
                                                        <img id="previewImagenCrear" class="index-preview-media" src="../../assets/img/fondoSietelsa.png" alt="Vista previa del proyecto" />
                                                    </div>
                                                    <div class="index-preview-content">
                                                        <h4 id="previewTituloCrear" class="index-preview-title">Titulo del proyecto</h4>
                                                        <p id="previewCategoriaCrear" class="index-preview-category">Categoria del portafolio</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Guardar proyecto
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
                                Proyectos registrados
                            </div>
                            <div class="card-body">
                                <?php if (empty($proyectos)): ?>
                                    <p class="mb-0 text-muted">No hay proyectos creados todavia.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Imagen</th>
                                                    <th>Titulo</th>
                                                    <th>Categoria</th>
                                                    <th>Estado</th>
                                                    <th>Accion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($proyectos as $fila): ?>
                                                    <tr>
                                                        <td>
                                                            <img
                                                                class="project-thumb"
                                                                src="<?php echo htmlspecialchars(ruta_imagen_proyecto_admin((string) ($fila['imagen_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                                alt="<?php echo htmlspecialchars((string) ($fila['titulo'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?>"
                                                            />
                                                        </td>
                                                        <td><?php echo htmlspecialchars((string) ($fila['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($fila['categoria_titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <?php if ((int) $fila['activo'] === 1): ?>
                                                                <span class="badge bg-success">Visible</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Oculto</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a class="btn btn-outline-primary btn-sm" href="/admin/proyectos?vista=editar&id=<?php echo (int) $fila['id']; ?>">Editar</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($vista === 'editar' && $proyectoEdicion !== null): ?>
                            <?php
                            $stmtDetalle = $pdo->prepare(
                                'SELECT pr.id,
                                        pr.titulo,
                                        pr.portafolio_id,
                                        pr.imagen_path,
                                        pr.activo,
                                        pf.titulo AS categoria_titulo
                                 FROM proyectos pr
                                 LEFT JOIN portafolio pf ON pf.id = pr.portafolio_id
                                 WHERE pr.id = :id
                                 LIMIT 1'
                            );
                            $stmtDetalle->execute([':id' => (int) $proyectoEdicion['id']]);
                            $detalle = $stmtDetalle->fetch();
                            if (!$detalle) {
                                $detalle = $proyectoEdicion;
                            }
                            ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-pen me-1"></i>
                                    Editando proyecto: <?php echo htmlspecialchars((string) ($detalle['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-2">Vista previa</label>
                                            <div class="index-preview-card">
                                                <div class="index-preview-media-wrap">
                                                    <img
                                                        id="previewImagenEditar"
                                                        class="index-preview-media"
                                                        src="<?php echo htmlspecialchars(ruta_imagen_proyecto_admin((string) ($detalle['imagen_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-original-src="<?php echo htmlspecialchars(ruta_imagen_proyecto_admin((string) ($detalle['imagen_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                        alt="<?php echo htmlspecialchars((string) ($detalle['titulo'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    />
                                                </div>
                                                <div class="index-preview-content">
                                                    <h4 id="previewTituloEditar" class="index-preview-title"><?php echo htmlspecialchars((string) ($detalle['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h4>
                                                    <p id="previewCategoriaEditar" class="index-preview-category"><?php echo htmlspecialchars((string) ($detalle['categoria_titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-8">
                                            <form action="/admin/acciones/proyectos" method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfProyectos, ENT_QUOTES, 'UTF-8'); ?>" />
                                                <input type="hidden" name="accion" value="editar" />
                                                <input type="hidden" name="proyecto_id" value="<?php echo (int) ($detalle['id'] ?? 0); ?>" />
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="titulo_editar">Titulo del proyecto</label>
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
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="portafolio_id_editar">Categoria (desde portafolio)</label>
                                                        <?php $portafolioIdActual = (int) ($detalle['portafolio_id'] ?? 0); ?>
                                                        <select class="form-select" id="portafolio_id_editar" name="portafolio_id" required>
                                                            <option value="">Selecciona una categoria...</option>
                                                            <?php foreach ($categoriasPortafolio as $categoriaPortafolio): ?>
                                                                <?php $categoriaId = (int) ($categoriaPortafolio['id'] ?? 0); ?>
                                                                <option value="<?php echo $categoriaId; ?>" <?php echo $categoriaId === $portafolioIdActual ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars((string) ($categoriaPortafolio['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="form-label" for="imagen_editar">Cambiar imagen (opcional)</label>
                                                        <input
                                                            class="form-control"
                                                            id="imagen_editar"
                                                            name="imagen"
                                                            type="file"
                                                            accept="image/png,image/jpeg,image/webp,image/gif"
                                                            data-max-filesize="5242880"
                                                        />
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <div class="form-check">
                                                            <input class="form-check-input" id="activo_editar" name="activo" type="checkbox" value="1" <?php echo ((int) ($detalle['activo'] ?? 0) === 1) ? 'checked' : ''; ?> />
                                                            <label class="form-check-label" for="activo_editar">Mostrar en index</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 d-flex flex-wrap gap-2">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="fas fa-save me-1"></i>Actualizar proyecto
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>

                                            <div class="border-top pt-3 mt-3">
                                                <form action="/admin/acciones/proyectos" method="post" data-confirm-message="Esta accion eliminara el proyecto. Deseas continuar?">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfProyectos, ENT_QUOTES, 'UTF-8'); ?>" />
                                                    <input type="hidden" name="accion" value="eliminar" />
                                                    <input type="hidden" name="proyecto_id" value="<?php echo (int) ($detalle['id'] ?? 0); ?>" />
                                                    <button class="btn btn-outline-danger" type="submit">
                                                        <i class="fas fa-trash me-1"></i>Eliminar proyecto
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($vista === 'editar'): ?>
                            <div class="alert alert-info" role="alert">
                                No hay proyectos disponibles para editar.
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
                function bindPreview(config) {
                    var image = document.getElementById(config.imageId);
                    if (!image) {
                        return;
                    }

                    var originalSrc = image.getAttribute('data-original-src') || image.getAttribute('src') || '';
                    var inputImagen = document.getElementById(config.imageInputId);
                    var inputTitulo = document.getElementById(config.titleInputId);
                    var inputCategoria = document.getElementById(config.categoryInputId);
                    var labelTitulo = document.getElementById(config.titlePreviewId);
                    var labelCategoria = document.getElementById(config.categoryPreviewId);

                    function refreshText() {
                        if (labelTitulo && inputTitulo) {
                            var titulo = (inputTitulo.value || '').trim();
                            labelTitulo.textContent = titulo !== '' ? titulo : 'Titulo del proyecto';
                        }
                        if (labelCategoria && inputCategoria) {
                            var categoria = '';
                            if (inputCategoria.tagName === 'SELECT') {
                                categoria = inputCategoria.options[inputCategoria.selectedIndex] ? inputCategoria.options[inputCategoria.selectedIndex].text : '';
                                if ((inputCategoria.value || '').trim() === '') {
                                    categoria = '';
                                }
                            } else {
                                categoria = (inputCategoria.value || '').trim();
                            }
                            labelCategoria.textContent = categoria !== '' ? categoria : 'Categoria del portafolio';
                        }
                    }

                    if (inputTitulo) {
                        inputTitulo.addEventListener('input', refreshText);
                    }
                    if (inputCategoria) {
                        inputCategoria.addEventListener('change', refreshText);
                        inputCategoria.addEventListener('input', refreshText);
                    }
                    refreshText();

                    if (!inputImagen) {
                        return;
                    }

                    inputImagen.addEventListener('change', function () {
                        var file = inputImagen.files && inputImagen.files[0] ? inputImagen.files[0] : null;
                        if (!file) {
                            image.setAttribute('src', originalSrc);
                            return;
                        }

                        var objectUrl = URL.createObjectURL(file);
                        image.setAttribute('src', objectUrl);
                        image.onload = function () {
                            URL.revokeObjectURL(objectUrl);
                        };
                    });
                }

                bindPreview({
                    imageInputId: 'imagen',
                    titleInputId: 'titulo',
                    categoryInputId: 'portafolio_id',
                    imageId: 'previewImagenCrear',
                    titlePreviewId: 'previewTituloCrear',
                    categoryPreviewId: 'previewCategoriaCrear'
                });

                bindPreview({
                    imageInputId: 'imagen_editar',
                    titleInputId: 'titulo_editar',
                    categoryInputId: 'portafolio_id_editar',
                    imageId: 'previewImagenEditar',
                    titlePreviewId: 'previewTituloEditar',
                    categoryPreviewId: 'previewCategoriaEditar'
                });
            })();
        </script>
    </body>
</html>

