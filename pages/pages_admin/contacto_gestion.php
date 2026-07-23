<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

require_modulo('gestionar_contacto');
global $pdo;
asegurar_tabla_contacto_mensajes($pdo);

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

$filtro = trim((string) ($_GET['estado'] ?? 'todos'));
$filtrosValidos = ['todos', 'nuevo', 'leido', 'respondido', 'pendiente'];
if (!in_array($filtro, $filtrosValidos, true)) {
    $filtro = 'todos';
}

$sqlListado = 'SELECT cm.id,
                      cm.nombre,
                      cm.email,
                      cm.telefono,
                      cm.asunto,
                      cm.mensaje,
                      cm.estado,
                      cm.respuesta_admin,
                      cm.respondido_en,
                      cm.notificado,
                      cm.error_notificacion,
                      cm.creado_en,
                      u.nombre AS respondido_por_nombre
               FROM contacto_mensajes cm
               LEFT JOIN usuarios u ON u.id = cm.respondido_por_usuario_id';

$where = [];
$params = [];
if ($filtro === 'pendiente') {
    $where[] = 'cm.notificado = 0';
} elseif ($filtro !== 'todos') {
    $where[] = 'cm.estado = :estado';
    $params[':estado'] = $filtro;
}

if (!empty($where)) {
    $sqlListado .= ' WHERE ' . implode(' AND ', $where);
}

$sqlListado .= ' ORDER BY CASE cm.estado
                    WHEN \'nuevo\' THEN 0
                    WHEN \'leido\' THEN 1
                    WHEN \'respondido\' THEN 2
                    ELSE 3
                END,
                cm.creado_en DESC';

$stmtListado = $pdo->prepare($sqlListado);
$stmtListado->execute($params);
$mensajes = $stmtListado->fetchAll();

$conteoEstado = [
    'nuevo' => 0,
    'leido' => 0,
    'respondido' => 0,
    'pendiente' => 0,
];
$conteoTotal = 0;
$stmtConteo = $pdo->query(
    'SELECT estado,
            COUNT(*) AS total,
            SUM(CASE WHEN notificado = 0 THEN 1 ELSE 0 END) AS pendientes
     FROM contacto_mensajes
     GROUP BY estado'
);
if ($stmtConteo) {
    foreach ($stmtConteo->fetchAll() as $filaConteo) {
        $estadoItem = trim((string) ($filaConteo['estado'] ?? ''));
        if (isset($conteoEstado[$estadoItem])) {
            $conteoEstado[$estadoItem] = (int) ($filaConteo['total'] ?? 0);
        }
        $conteoTotal += (int) ($filaConteo['total'] ?? 0);
        $conteoEstado['pendiente'] += (int) ($filaConteo['pendientes'] ?? 0);
    }
}

$mensajeId = (int) ($_GET['id'] ?? 0);
if ($mensajeId <= 0 && !empty($mensajes)) {
    $mensajeId = (int) ($mensajes[0]['id'] ?? 0);
}

$mensajeSeleccionado = null;
foreach ($mensajes as $index => $mensajeItem) {
    if ((int) ($mensajeItem['id'] ?? 0) === $mensajeId) {
        $mensajeSeleccionado = $mensajeItem;
        break;
    }
}

if ($mensajeSeleccionado === null && !empty($mensajes)) {
    $mensajeSeleccionado = $mensajes[0];
    $mensajeId = (int) ($mensajeSeleccionado['id'] ?? 0);
}

if (is_array($mensajeSeleccionado) && (string) ($mensajeSeleccionado['estado'] ?? '') === 'nuevo') {
    try {
        $stmtLeido = $pdo->prepare(
            'UPDATE contacto_mensajes
             SET estado = :estado
             WHERE id = :id AND estado = :estado_anterior
             LIMIT 1'
        );
        $stmtLeido->execute([
            ':estado' => 'leido',
            ':id' => (int) ($mensajeSeleccionado['id'] ?? 0),
            ':estado_anterior' => 'nuevo',
        ]);
        $mensajeSeleccionado['estado'] = 'leido';
    } catch (Throwable $e) {
        // Ignora error de marcado para mantener la vista disponible.
    }
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensajeFlash = '';
$tipoFlash = 'success';

if ($ok === 'respondido') {
    $mensajeFlash = 'Respuesta enviada por correo correctamente.';
}

$errores = [
    'id' => 'No se encontro el mensaje seleccionado.',
    'email' => 'El correo del contacto no es valido.',
    'respuesta' => 'Debes escribir una respuesta antes de enviar.',
    'respuesta_larga' => 'La respuesta es demasiado extensa.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'smtp' => 'No se pudo enviar la respuesta por correo. Verifica la configuracion SMTP.',
    'db' => 'No se pudo actualizar el mensaje por un error de base de datos.',
    'general' => 'No se pudo completar la accion solicitada.',
];
if (isset($errores[$error])) {
    $mensajeFlash = $errores[$error];
    $tipoFlash = 'danger';
}
$csrfContactoResponder = csrf_token('admin_contacto_responder');

function contacto_formatear_fecha(?string $fechaRaw): string
{
    $fechaRaw = trim((string) $fechaRaw);
    if ($fechaRaw === '') {
        return 'No disponible';
    }

    $timestamp = strtotime($fechaRaw);
    if ($timestamp === false) {
        return $fechaRaw;
    }

    return date('d/m/Y H:i', $timestamp);
}

function contacto_badge_estado(string $estado): string
{
    $estado = trim($estado);
    if ($estado === 'nuevo') {
        return 'bg-danger';
    }
    if ($estado === 'leido') {
        return 'bg-warning text-dark';
    }
    if ($estado === 'respondido') {
        return 'bg-success';
    }

    return 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestion de mensajes de contacto - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Mensajes de Contacto | Sietelsa</title>
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
                        <h1 class="mt-4">Mensajes de contacto</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Contacto</li>
                        </ol>

                        <?php if ($mensajeFlash !== ''): ?>
                            <div class="alert alert-<?php echo $tipoFlash === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensajeFlash, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-3">
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/contacto?estado=nuevo">
                                    <div class="card text-bg-danger h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Nuevos</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstado['nuevo']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/contacto?estado=leido">
                                    <div class="card text-bg-warning h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Leidos</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstado['leido']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/contacto?estado=respondido">
                                    <div class="card text-bg-success h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Respondidos</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstado['respondido']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/contacto?estado=pendiente">
                                    <div class="card text-bg-secondary h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Sin notificar</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstado['pendiente']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/contacto?estado=todos">
                                    <div class="card text-bg-primary h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Todos</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoTotal; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-5">
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-inbox me-1"></i>Bandeja</span>
                                        <span class="small text-muted">Filtro: <?php echo htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php if (empty($mensajes)): ?>
                                            <p class="m-3 text-muted mb-3">No hay mensajes en este filtro.</p>
                                        <?php else: ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($mensajes as $fila): ?>
                                                    <?php
                                                    $idFila = (int) ($fila['id'] ?? 0);
                                                    $activo = $idFila === $mensajeId;
                                                    ?>
                                                    <a
                                                        class="list-group-item list-group-item-action<?php echo $activo ? ' active' : ''; ?>"
                                                        href="/admin/contacto?estado=<?php echo urlencode($filtro); ?>&id=<?php echo $idFila; ?>"
                                                    >
                                                        <div class="d-flex w-100 justify-content-between align-items-start gap-2">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($fila['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <div class="small"><?php echo htmlspecialchars((string) ($fila['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <div class="small"><?php echo htmlspecialchars((string) ($fila['asunto'] ?? 'Sin asunto'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </div>
                                                            <span class="badge <?php echo contacto_badge_estado((string) ($fila['estado'] ?? '')); ?>">
                                                                <?php echo htmlspecialchars(strtoupper((string) ($fila['estado'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                        </div>
                                                        <div class="small mt-1">
                                                            <?php echo htmlspecialchars(contacto_formatear_fecha((string) ($fila['creado_en'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                                        </div>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-envelope me-1"></i>
                                        Detalle del mensaje
                                    </div>
                                    <div class="card-body">
                                        <?php if (!is_array($mensajeSeleccionado)): ?>
                                            <p class="text-muted mb-0">Selecciona un mensaje de la bandeja para verlo.</p>
                                        <?php else: ?>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="small text-muted">Nombre</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($mensajeSeleccionado['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="small text-muted">Correo</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($mensajeSeleccionado['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="small text-muted">Telefono</div>
                                                    <div><?php echo htmlspecialchars((string) ($mensajeSeleccionado['telefono'] ?? 'No proporcionado'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="small text-muted">Fecha</div>
                                                    <div><?php echo htmlspecialchars(contacto_formatear_fecha((string) ($mensajeSeleccionado['creado_en'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="small text-muted">Asunto</div>
                                                    <div><?php echo htmlspecialchars((string) ($mensajeSeleccionado['asunto'] ?? 'Sin asunto'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="small text-muted">Mensaje</div>
                                                    <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;"><?php echo htmlspecialchars((string) ($mensajeSeleccionado['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                            </div>

                                            <?php if ((int) ($mensajeSeleccionado['notificado'] ?? 0) === 0): ?>
                                                <div class="alert alert-warning" role="alert">
                                                    Este mensaje se guardo, pero no se pudo enviar notificacion SMTP al correo destino.
                                                    <?php if (trim((string) ($mensajeSeleccionado['error_notificacion'] ?? '')) !== ''): ?>
                                                        <br />
                                                        <small>Error: <?php echo htmlspecialchars((string) $mensajeSeleccionado['error_notificacion'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (trim((string) ($mensajeSeleccionado['respuesta_admin'] ?? '')) !== ''): ?>
                                                <div class="mb-3">
                                                    <div class="small text-muted">Respuesta registrada</div>
                                                    <div class="border rounded p-3 bg-body-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars((string) $mensajeSeleccionado['respuesta_admin'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <div class="small text-muted mt-1">
                                                        Respondido por <?php echo htmlspecialchars((string) ($mensajeSeleccionado['respondido_por_nombre'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8'); ?>
                                                        el <?php echo htmlspecialchars(contacto_formatear_fecha((string) ($mensajeSeleccionado['respondido_en'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <hr />

                                            <form action="/admin/acciones/contacto-responder" method="post">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfContactoResponder, ENT_QUOTES, 'UTF-8'); ?>" />
                                                <input type="hidden" name="mensaje_id" value="<?php echo (int) ($mensajeSeleccionado['id'] ?? 0); ?>" />
                                                <div class="mb-3">
                                                    <label class="form-label" for="respuesta">Responder por correo</label>
                                                    <textarea class="form-control" id="respuesta" name="respuesta" rows="7" maxlength="10000" required></textarea>
                                                    <div class="form-text">La respuesta se enviara al correo del contacto y quedara guardada en el sistema.</div>
                                                </div>
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-paper-plane me-1"></i>Enviar respuesta
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
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

