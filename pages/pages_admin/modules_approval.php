<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

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

$estadoSolicitado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
$mapaFiltros = [
    'todos' => null,
    'borrador' => 'BORRADOR',
    'pendiente' => 'PENDIENTE',
    'aprobado' => 'APROBADO',
    'rechazado' => 'RECHAZADO',
];
if (!array_key_exists($estadoSolicitado, $mapaFiltros)) {
    $estadoSolicitado = 'todos';
}

$sqlModulos = 'SELECT sm.id,
                      sm.slug,
                      sm.display_name,
                      sm.route_path,
                      sm.category,
                      sm.permission_code,
                      sm.icon_class,
                      sm.sidebar_order,
                      sm.is_admin_only,
                      sm.approval_status,
                      sm.rejection_reason,
                      sm.reviewed_at,
                      u.nombre AS reviewed_by_nombre,
                      u.username AS reviewed_by_username
               FROM system_modules sm
               LEFT JOIN usuarios u ON u.id = sm.reviewed_by_user_id';
$paramsModulos = [];
if ($mapaFiltros[$estadoSolicitado] !== null) {
    $sqlModulos .= ' WHERE sm.approval_status = :status';
    $paramsModulos[':status'] = $mapaFiltros[$estadoSolicitado];
}
$sqlModulos .= ' ORDER BY sm.category ASC, sm.sidebar_order ASC, sm.display_name ASC';

$stmtModulos = $pdo->prepare($sqlModulos);
$stmtModulos->execute($paramsModulos);
$modulos = $stmtModulos->fetchAll();

$conteoEstados = [
    'BORRADOR' => 0,
    'PENDIENTE' => 0,
    'APROBADO' => 0,
    'RECHAZADO' => 0,
    'TOTAL' => 0,
];
$stmtConteo = $pdo->query('SELECT approval_status, COUNT(*) AS total FROM system_modules GROUP BY approval_status');
if ($stmtConteo) {
    foreach ($stmtConteo->fetchAll() as $fila) {
        $estadoFila = modulos_publicacion_normalizar_estado((string) ($fila['approval_status'] ?? ''));
        $totalFila = (int) ($fila['total'] ?? 0);
        if (isset($conteoEstados[$estadoFila])) {
            $conteoEstados[$estadoFila] = $totalFila;
            $conteoEstados['TOTAL'] += $totalFila;
        }
    }
}

$stmtAuditoria = $pdo->query(
    'SELECT a.id,
            a.module_id,
            a.from_status,
            a.to_status,
            a.reason,
            a.changed_at,
            a.changed_by_name,
            sm.slug,
            sm.display_name
     FROM system_module_approval_audit a
     INNER JOIN system_modules sm ON sm.id = a.module_id
     ORDER BY a.changed_at DESC
     LIMIT 20'
);
$auditoria = $stmtAuditoria ? $stmtAuditoria->fetchAll() : [];

$ok = strtolower(trim((string) ($_GET['ok'] ?? '')));
$error = strtolower(trim((string) ($_GET['error'] ?? '')));
$mensaje = '';
$tipoMensaje = 'success';

$mensajesOk = [
    'aprobado' => 'Modulo aprobado correctamente.',
    'pendiente' => 'Modulo marcado como pendiente.',
    'borrador' => 'Modulo regresado a borrador.',
    'rechazado' => 'Modulo rechazado correctamente.',
    'sin_cambios' => 'No hubo cambios en el estado del modulo.',
];
$mensajesError = [
    'metodo' => 'Metodo no permitido para esta accion.',
    'csrf' => 'Token CSRF invalido o expirado.',
    'accion' => 'Accion no valida para aprobacion de modulos.',
    'slug' => 'No se encontro el modulo solicitado.',
    'estado' => 'Estado de modulo no permitido.',
    'motivo' => 'Debes ingresar un motivo al rechazar un modulo.',
    'motivo_largo' => 'El motivo de rechazo excede 500 caracteres.',
    'db' => 'No se pudo actualizar el modulo por un error de base de datos.',
    'general' => 'No se pudo completar la operacion solicitada.',
];
if (isset($mensajesOk[$ok])) {
    $mensaje = $mensajesOk[$ok];
} elseif (isset($mensajesError[$error])) {
    $mensaje = $mensajesError[$error];
    $tipoMensaje = 'danger';
}

$csrfModulesApproval = csrf_token('admin_modules_approval');

function modulos_aprobacion_badge_class(string $estado): string
{
    $estado = modulos_publicacion_normalizar_estado($estado);
    if ($estado === 'APROBADO') {
        return 'bg-success';
    }
    if ($estado === 'PENDIENTE') {
        return 'bg-warning text-dark';
    }
    if ($estado === 'RECHAZADO') {
        return 'bg-danger';
    }
    return 'bg-secondary';
}

function modulos_aprobacion_formatear_fecha(?string $fechaRaw): string
{
    $fechaRaw = trim((string) $fechaRaw);
    if ($fechaRaw === '') {
        return 'Sin fecha';
    }

    $timestamp = strtotime($fechaRaw);
    if ($timestamp === false) {
        return $fechaRaw;
    }

    return date('d/m/Y H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Aprobacion de modulos - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Aprobacion de modulos | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .estado-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.4rem;
                align-items: center;
            }
            .estado-actions form {
                margin: 0;
            }
            .motivo-rechazo-form {
                display: flex;
                gap: 0.35rem;
                align-items: center;
                margin-top: 0.45rem;
            }
            .motivo-rechazo-form input {
                min-width: 180px;
                max-width: 280px;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php render_admin_topnav($pdo, $usuario, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, $usuario, $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Aprobacion de modulos</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Aprobacion de modulos</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-3">
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/modulos/aprobacion?estado=todos">
                                    <div class="card text-bg-primary h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Todos</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstados['TOTAL']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/modulos/aprobacion?estado=borrador">
                                    <div class="card text-bg-secondary h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Borrador</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstados['BORRADOR']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/modulos/aprobacion?estado=pendiente">
                                    <div class="card text-bg-warning h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Pendiente</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstados['PENDIENTE']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/modulos/aprobacion?estado=aprobado">
                                    <div class="card text-bg-success h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Aprobado</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstados['APROBADO']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <a class="text-decoration-none" href="/admin/modulos/aprobacion?estado=rechazado">
                                    <div class="card text-bg-danger h-100">
                                        <div class="card-body py-2">
                                            <div class="small">Rechazado</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int) $conteoEstados['RECHAZADO']; ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-list-check me-1"></i>Catalogo de modulos</span>
                                <span class="small text-muted">Filtro actual: <?php echo htmlspecialchars($estadoSolicitado, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($modulos)): ?>
                                    <p class="mb-0 text-muted">No hay modulos para este filtro.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Modulo</th>
                                                    <th>Ruta</th>
                                                    <th>Categoria</th>
                                                    <th>Estado</th>
                                                    <th>Ultima revision</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($modulos as $modulo): ?>
                                                    <?php
                                                    $slug = (string) ($modulo['slug'] ?? '');
                                                    $estado = modulos_publicacion_normalizar_estado((string) ($modulo['approval_status'] ?? 'BORRADOR'));
                                                    $motivoRechazo = trim((string) ($modulo['rejection_reason'] ?? ''));
                                                    $reviewedBy = trim((string) ($modulo['reviewed_by_nombre'] ?? ''));
                                                    if ($reviewedBy === '') {
                                                        $reviewedBy = trim((string) ($modulo['reviewed_by_username'] ?? ''));
                                                    }
                                                    if ($reviewedBy === '') {
                                                        $reviewedBy = 'Sin usuario';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($modulo['display_name'] ?? $slug), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php if ((int) ($modulo['is_admin_only'] ?? 0) === 1): ?>
                                                                <span class="badge bg-dark mt-1">Solo admin</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <code><?php echo htmlspecialchars((string) ($modulo['route_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                                            <?php if (trim((string) ($modulo['permission_code'] ?? '')) !== ''): ?>
                                                                <div class="small text-muted">Permiso: <?php echo htmlspecialchars((string) $modulo['permission_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars((string) ($modulo['category'] ?? 'general'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo modulos_aprobacion_badge_class($estado); ?>">
                                                                <?php echo htmlspecialchars($estado, ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                            <?php if ($motivoRechazo !== ''): ?>
                                                                <div class="small text-danger mt-1"><?php echo htmlspecialchars($motivoRechazo, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div><?php echo htmlspecialchars(modulos_aprobacion_formatear_fecha((string) ($modulo['reviewed_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($reviewedBy, ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="estado-actions">
                                                                <form action="/admin/acciones/modulos/aprobacion" method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulesApproval, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="accion" value="aprobar" />
                                                                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($estadoSolicitado, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <button class="btn btn-success btn-sm" type="submit" <?php echo $estado === 'APROBADO' ? 'disabled' : ''; ?>>Aprobar</button>
                                                                </form>
                                                                <form action="/admin/acciones/modulos/aprobacion" method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulesApproval, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="accion" value="pendiente" />
                                                                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($estadoSolicitado, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <button class="btn btn-warning btn-sm" type="submit" <?php echo $estado === 'PENDIENTE' ? 'disabled' : ''; ?>>Pendiente</button>
                                                                </form>
                                                                <form action="/admin/acciones/modulos/aprobacion" method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulesApproval, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="accion" value="borrador" />
                                                                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($estadoSolicitado, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                    <button class="btn btn-secondary btn-sm" type="submit" <?php echo $estado === 'BORRADOR' ? 'disabled' : ''; ?>>Borrador</button>
                                                                </form>
                                                            </div>
                                                            <form class="motivo-rechazo-form" action="/admin/acciones/modulos/aprobacion" method="post">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulesApproval, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                <input type="hidden" name="accion" value="rechazar" />
                                                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($estadoSolicitado, ENT_QUOTES, 'UTF-8'); ?>" />
                                                                <input class="form-control form-control-sm" type="text" name="motivo" maxlength="500" value="<?php echo htmlspecialchars($motivoRechazo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Motivo de rechazo" required />
                                                                <button class="btn btn-danger btn-sm" type="submit">Rechazar</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-clock-rotate-left me-1"></i>
                                Ultimos cambios de auditoria
                            </div>
                            <div class="card-body">
                                <?php if (empty($auditoria)): ?>
                                    <p class="mb-0 text-muted">No hay auditoria registrada todavia.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Modulo</th>
                                                    <th>Cambio</th>
                                                    <th>Motivo</th>
                                                    <th>Usuario</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($auditoria as $itemAuditoria): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(modulos_aprobacion_formatear_fecha((string) ($itemAuditoria['changed_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($itemAuditoria['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars((string) ($itemAuditoria['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo modulos_aprobacion_badge_class((string) ($itemAuditoria['from_status'] ?? 'BORRADOR')); ?>">
                                                                <?php echo htmlspecialchars((string) ($itemAuditoria['from_status'] ?? 'BORRADOR'), ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                            <i class="fas fa-arrow-right mx-1"></i>
                                                            <span class="badge <?php echo modulos_aprobacion_badge_class((string) ($itemAuditoria['to_status'] ?? 'BORRADOR')); ?>">
                                                                <?php echo htmlspecialchars((string) ($itemAuditoria['to_status'] ?? 'BORRADOR'), ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars((string) ($itemAuditoria['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($itemAuditoria['changed_by_name'] ?? 'Sin usuario'), ENT_QUOTES, 'UTF-8'); ?></td>
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
                            <div>
                                <a href="/">Sitio publico</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="/admin/js/scripts.js?v=20260613e"></script>
    </body>
</html>
