<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';
require_once __DIR__ . '/../../include/maintenance.php';
require_once __DIR__ . '/../../include/visitas_analytics.php';
require_once __DIR__ . '/../../include/dte_integration.php';

require_modulo('ver_dashboard');
global $pdo;
$usuario = obtener_usuario_actual() ?? [];
$nombre = trim((string) ($usuario['nombre'] ?? ''));
$username = trim((string) ($usuario['username'] ?? ''));
$perfil = trim((string) ($usuario['perfil'] ?? ''));
$usuarioId = (int) ($usuario['id'] ?? 0);
$puedeGestionarUsuarios = tiene_permiso('gestionar_usuarios');
$puedeGestionarServicios = tiene_permiso('gestionar_servicios');
$puedeGestionarPortafolio = tiene_permiso('gestionar_portafolio');
$puedeGestionarProyectos = tiene_permiso('gestionar_proyectos');
$puedeGestionarNosotros = tiene_permiso('gestionar_nosotros');
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');
$puedeGestionarBackup = tiene_permiso('gestionar_backup');
$puedeGestionarPerfiles = tiene_permiso('gestionar_perfiles');
$puedeGestionarMantenimiento = usuario_puede_gestionar_mantenimiento($usuario);
$puedeConfigurarPasswordMantenimiento = usuario_puede_configurar_password_mantenimiento($usuario);
$puedeAccesoDte = usuario_tiene_acceso_dte($usuario);
$puedeDteEmitir = $puedeAccesoDte && (tiene_permiso('dte_emitir') || usuario_actual_es_admin());
$puedeDteReportes = $puedeAccesoDte && (tiene_permiso('dte_reportes') || usuario_actual_es_admin());
$puedeDteConfigurar = $puedeAccesoDte && (tiene_permiso('dte_configurar') || usuario_actual_es_admin());
$puedeGestionarContenido = $puedeGestionarServicios
    || $puedeGestionarPortafolio
    || $puedeGestionarProyectos
    || $puedeGestionarNosotros
    || $puedeGestionarContacto;

$etiquetaSesion = $nombre !== '' ? $nombre : 'Usuario';
if ($username !== '') {
    $etiquetaSesion .= ' (' . $username . ')';
}

function formatear_fecha_dashboard_visitas(string $fechaYmd): string
{
    $fecha = normalizar_fecha_filtro_visita($fechaYmd);
    if ($fecha === null) {
        return $fechaYmd;
    }

    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return $fechaYmd;
    }

    return date('d/m/Y', $timestamp);
}

function formatear_duracion_dashboard_visitas(int $segundos): string
{
    $segundos = max(0, $segundos);
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $restoSegundos = $segundos % 60;

    if ($horas > 0) {
        return $horas . 'h ' . $minutos . 'm';
    }
    if ($minutos > 0) {
        return $minutos . 'm ' . $restoSegundos . 's';
    }

    return $restoSegundos . 's';
}

function calcular_porcentaje_dashboard_visitas(int $total, int $base): float
{
    if ($base <= 0) {
        return 0.0;
    }

    $porcentaje = ($total * 100) / $base;

    return max(0.0, min(100.0, round($porcentaje, 1)));
}

function bandera_por_codigo_pais_dashboard(?string $codigo): string
{
    $codigo = strtoupper(trim((string) $codigo));
    if (preg_match('/^[A-Z]{2}$/', $codigo) !== 1) {
        return '🌐';
    }

    $base = 127397;
    $entidades = '&#' . ($base + ord($codigo[0])) . ';&#' . ($base + ord($codigo[1])) . ';';
    $bandera = html_entity_decode($entidades, ENT_NOQUOTES, 'UTF-8');

    return $bandera !== '' ? $bandera : '🌐';
}

$totalesDashboard = [
    'mensajes_nuevos' => null,
    'proyectos' => null,
    'portafolio' => null,
    'servicios' => null,
];

$totalesDte = [
    'documentos' => null,
    'pendientes' => null,
    'rechazados' => 0,
    'empresas' => null,
];

$hoyFiltro = (new DateTimeImmutable('today'))->format('Y-m-d');
$fechaHastaFiltro = normalizar_fecha_filtro_visita((string) ($_GET['hasta'] ?? '')) ?? $hoyFiltro;
$fechaDesdeDefault = (new DateTimeImmutable($fechaHastaFiltro . ' 00:00:00'))->modify('-29 days')->format('Y-m-d');
$fechaDesdeFiltro = normalizar_fecha_filtro_visita((string) ($_GET['desde'] ?? '')) ?? $fechaDesdeDefault;

if ($fechaDesdeFiltro > $fechaHastaFiltro) {
    $tmp = $fechaDesdeFiltro;
    $fechaDesdeFiltro = $fechaHastaFiltro;
    $fechaHastaFiltro = $tmp;
}

$resumenVisitas = [
    'labels' => [],
    'visitas' => [],
    'paises_distintos' => [],
    'total_periodo' => 0,
    'total_hoy' => 0,
    'usuarios_unicos' => 0,
    'paginas_vistas' => 0,
    'promedio_sesion_segundos' => 0,
    'sesiones' => 0,
    'dispositivos' => [],
    'navegadores' => [],
    'paginas_mas_visitadas' => [],
    'top_paises' => [],
    'paises' => [],
    'fecha_inicio' => $fechaDesdeFiltro,
    'fecha_fin' => $fechaHastaFiltro,
];

try {
    $cacheVisitasKey = 'admin_dashboard_visitas_v2'
        . '|desde:' . $fechaDesdeFiltro
        . '|hasta:' . $fechaHastaFiltro;
    $resumenCache = sietelsa_cache_remember('query', $cacheVisitasKey, 45, static function () use ($pdo, $fechaDesdeFiltro, $fechaHastaFiltro): array {
        asegurar_tabla_visitas_sitio($pdo);
        return obtener_resumen_visitas_sitio(
            $pdo,
            30,
            'inicio',
            $fechaDesdeFiltro,
            $fechaHastaFiltro
        );
    });
    if (is_array($resumenCache)) {
        $resumenVisitas = $resumenCache;
    }

    if (isset($resumenVisitas['fecha_inicio'])) {
        $fechaDesdeFiltro = (string) $resumenVisitas['fecha_inicio'];
    }
    if (isset($resumenVisitas['fecha_fin'])) {
        $fechaHastaFiltro = (string) $resumenVisitas['fecha_fin'];
    }
} catch (Throwable $e) {
    $resumenVisitas = [
        'labels' => [],
        'visitas' => [],
        'paises_distintos' => [],
        'total_periodo' => 0,
        'total_hoy' => 0,
        'usuarios_unicos' => 0,
        'paginas_vistas' => 0,
        'promedio_sesion_segundos' => 0,
        'sesiones' => 0,
        'dispositivos' => [],
        'navegadores' => [],
        'paginas_mas_visitadas' => [],
        'top_paises' => [],
        'paises' => [],
        'fecha_inicio' => $fechaDesdeFiltro,
        'fecha_fin' => $fechaHastaFiltro,
    ];
}

$actividadChartPayload = [
    'labels' => array_values(is_array($resumenVisitas['labels']) ? $resumenVisitas['labels'] : []),
    'visitas' => array_values(is_array($resumenVisitas['visitas']) ? $resumenVisitas['visitas'] : []),
    'datasetLabelVisitas' => 'Visitas',
];

$totalPeriodo = (int) ($resumenVisitas['total_periodo'] ?? 0);
$totalHoy = (int) ($resumenVisitas['total_hoy'] ?? 0);
$usuariosUnicos = (int) ($resumenVisitas['usuarios_unicos'] ?? 0);
$paginasVistas = (int) ($resumenVisitas['paginas_vistas'] ?? $totalPeriodo);
$promedioSesionSegundos = (int) ($resumenVisitas['promedio_sesion_segundos'] ?? 0);
$totalSesiones = (int) ($resumenVisitas['sesiones'] ?? 0);

$paisesTop = [];
if (!empty($resumenVisitas['paises']) && is_array($resumenVisitas['paises'])) {
    foreach (array_slice($resumenVisitas['paises'], 0, 6) as $itemPais) {
        $nombrePais = trim((string) ($itemPais['pais_nombre'] ?? 'Desconocido'));
        if ($nombrePais === '') {
            $nombrePais = trim((string) ($itemPais['pais_codigo'] ?? 'Desconocido'));
        }
        $totalPais = (int) ($itemPais['total'] ?? 0);
        $paisesTop[] = [
            'codigo' => strtoupper(trim((string) ($itemPais['pais_codigo'] ?? ''))),
            'nombre' => $nombrePais,
            'total' => $totalPais,
            'porcentaje' => calcular_porcentaje_dashboard_visitas($totalPais, $paginasVistas),
        ];
    }
}

$dispositivosMap = [
    'desktop' => 0,
    'mobile' => 0,
    'tablet' => 0,
];
if (!empty($resumenVisitas['dispositivos']) && is_array($resumenVisitas['dispositivos'])) {
    foreach ($resumenVisitas['dispositivos'] as $itemDispositivo) {
        $claveDispositivo = strtolower(trim((string) ($itemDispositivo['dispositivo'] ?? '')));
        if ($claveDispositivo === '' || !array_key_exists($claveDispositivo, $dispositivosMap)) {
            continue;
        }
        $dispositivosMap[$claveDispositivo] = (int) ($itemDispositivo['total'] ?? 0);
    }
}
$totalDispositivos = (int) array_sum($dispositivosMap);
$dispositivosResumen = [
    [
        'nombre' => 'Desktop',
        'total' => (int) $dispositivosMap['desktop'],
        'porcentaje' => calcular_porcentaje_dashboard_visitas((int) $dispositivosMap['desktop'], $totalDispositivos),
    ],
    [
        'nombre' => 'Mobile',
        'total' => (int) $dispositivosMap['mobile'],
        'porcentaje' => calcular_porcentaje_dashboard_visitas((int) $dispositivosMap['mobile'], $totalDispositivos),
    ],
    [
        'nombre' => 'Tablet',
        'total' => (int) $dispositivosMap['tablet'],
        'porcentaje' => calcular_porcentaje_dashboard_visitas((int) $dispositivosMap['tablet'], $totalDispositivos),
    ],
];

$navegadoresTop = [];
if (!empty($resumenVisitas['navegadores']) && is_array($resumenVisitas['navegadores'])) {
    foreach (array_slice($resumenVisitas['navegadores'], 0, 6) as $itemNavegador) {
        $nombreNavegador = trim((string) ($itemNavegador['navegador'] ?? 'Desconocido'));
        $totalNavegador = (int) ($itemNavegador['total'] ?? 0);
        $navegadoresTop[] = [
            'nombre' => $nombreNavegador !== '' ? $nombreNavegador : 'Desconocido',
            'total' => $totalNavegador,
            'porcentaje' => calcular_porcentaje_dashboard_visitas($totalNavegador, $paginasVistas),
        ];
    }
}

$mantenimientoActivo = false;
$mantenimientoPasswordConfigurada = false;
try {
    $mantenimientoActivo = mantenimiento_esta_activo($pdo);
    $mantenimientoPasswordConfigurada = mantenimiento_password_configurada($pdo);
} catch (Throwable $e) {
}

$mensajeMantenimiento = '';
$tipoMensajeMantenimiento = 'success';
$mantenimientoOk = trim((string) ($_GET['mantenimiento_ok'] ?? ''));
$mantenimientoError = trim((string) ($_GET['mantenimiento_error'] ?? ''));

if ($mantenimientoOk === '1') {
    $mensajeMantenimiento = 'Modo mantenimiento activado correctamente.';
} elseif ($mantenimientoOk === '0') {
    $mensajeMantenimiento = 'Modo mantenimiento desactivado correctamente.';
} elseif ($mantenimientoError !== '') {
    $tipoMensajeMantenimiento = 'danger';
    $mensajesErrorMantenimiento = [
        'metodo' => 'Solicitud no permitida para mantenimiento.',
        'csrf' => 'Token CSRF invalido o expirado. Intenta de nuevo.',
        'accion' => 'Accion de mantenimiento no valida.',
        'clave_config' => 'No hay clave configurada para mantenimiento. Define una en el modulo de mantenimiento.',
        'clave_requerida' => 'Debes ingresar la clave de mantenimiento.',
        'clave_invalida' => 'La clave de mantenimiento no es correcta.',
        'db' => 'No se pudo guardar el estado de mantenimiento en la base de datos.',
    ];
    $mensajeMantenimiento = $mensajesErrorMantenimiento[$mantenimientoError] ?? 'Ocurrio un error al actualizar mantenimiento.';
}

$csrfMantenimiento = $puedeGestionarMantenimiento ? csrf_token('toggle_mantenimiento') : '';

if ($puedeGestionarContenido) {
    try {
        $cacheTotalesDashboardKey = 'admin_dashboard_totales_v2'
            . '|u:' . $usuarioId
            . '|c:' . ($puedeGestionarContacto ? '1' : '0')
            . '|p:' . ($puedeGestionarProyectos ? '1' : '0')
            . '|po:' . ($puedeGestionarPortafolio ? '1' : '0')
            . '|s:' . ($puedeGestionarServicios ? '1' : '0');
        $totalesCache = sietelsa_cache_remember('query', $cacheTotalesDashboardKey, 20, static function () use (
            $pdo,
            $puedeGestionarContacto,
            $puedeGestionarProyectos,
            $puedeGestionarPortafolio,
            $puedeGestionarServicios
        ): array {
            $totales = [
                'mensajes_nuevos' => null,
                'proyectos' => null,
                'portafolio' => null,
                'servicios' => null,
            ];

            if ($puedeGestionarContacto) {
                asegurar_tabla_contacto_mensajes($pdo);
                $stmtMensajesNuevos = $pdo->query("SELECT COUNT(*) FROM contacto_mensajes WHERE estado = 'nuevo'");
                $totales['mensajes_nuevos'] = (int) ($stmtMensajesNuevos ? $stmtMensajesNuevos->fetchColumn() : 0);
            }

            if ($puedeGestionarProyectos) {
                asegurar_tabla_proyectos($pdo);
                $stmtProyectos = $pdo->query('SELECT COUNT(*) FROM proyectos');
                $totales['proyectos'] = (int) ($stmtProyectos ? $stmtProyectos->fetchColumn() : 0);
            }

            if ($puedeGestionarPortafolio) {
                asegurar_tabla_portafolio($pdo);
                $stmtPortafolio = $pdo->query('SELECT COUNT(*) FROM portafolio');
                $totales['portafolio'] = (int) ($stmtPortafolio ? $stmtPortafolio->fetchColumn() : 0);
            }

            if ($puedeGestionarServicios) {
                asegurar_tabla_servicios($pdo);
                $stmtServicios = $pdo->query('SELECT COUNT(*) FROM servicios');
                $totales['servicios'] = (int) ($stmtServicios ? $stmtServicios->fetchColumn() : 0);
            }

            return $totales;
        });
        if (is_array($totalesCache)) {
            $totalesDashboard = $totalesCache;
        }
    } catch (Throwable $e) {
        $totalesDashboard = [
            'mensajes_nuevos' => null,
            'proyectos' => null,
            'portafolio' => null,
            'servicios' => null,
        ];
    }
}

if ($puedeAccesoDte && dte_esquema_disponible($pdo)) {
    try {
        $cacheTotalesDteKey = 'admin_dashboard_dte_totales_v2'
            . '|u:' . $usuarioId
            . '|admin:' . (usuario_actual_es_admin() ? '1' : '0');
        $totalesDteCache = sietelsa_cache_remember('query', $cacheTotalesDteKey, 20, static function () use ($pdo, $usuario): array {
            $totales = [
                'documentos' => null,
                'pendientes' => null,
                'rechazados' => 0,
                'empresas' => null,
            ];

            $esAdminDte = usuario_actual_es_admin();
            $dteContexto = $esAdminDte ? null : dte_contexto_usuario($pdo, $usuario);
            $dteUserId = (int) ($dteContexto['id_usuario'] ?? 0);

            $filtroFacturas = '';
            $filtroLibros = '';
            $filtroEmpresas = '';
            $params = [];

            if (!$esAdminDte && $dteUserId > 0) {
                $filtroFacturas = ' WHERE id_usuario = :dte_user_id';
                $filtroLibros = ' WHERE l.id_usuario = :dte_user_id';
                $filtroEmpresas = ' WHERE id_usuario = :dte_user_id';
                $params[':dte_user_id'] = $dteUserId;
            } elseif (!$esAdminDte) {
                $filtroFacturas = ' WHERE 1 = 0';
                $filtroLibros = ' WHERE 1 = 0';
                $filtroEmpresas = ' WHERE 1 = 0';
            }

            $stmtDocumentos = $pdo->prepare('SELECT COUNT(*) FROM dte_facturas' . $filtroFacturas);
            $stmtDocumentos->execute($params);
            $totales['documentos'] = (int) $stmtDocumentos->fetchColumn();

            $stmtPendientes = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM (
                    SELECT l.id
                    FROM dte_libros l
                    LEFT JOIN dte_facturas f ON f.id_libro = l.id
                    ' . $filtroLibros . '
                    GROUP BY l.id
                    HAVING COUNT(f.id) = 0
                 ) pendientes'
            );
            $stmtPendientes->execute($params);
            $totales['pendientes'] = (int) $stmtPendientes->fetchColumn();

            $stmtEmpresas = $pdo->prepare('SELECT COUNT(*) FROM dte_empresas' . $filtroEmpresas);
            $stmtEmpresas->execute($params);
            $totales['empresas'] = (int) $stmtEmpresas->fetchColumn();

            return $totales;
        });
        if (is_array($totalesDteCache)) {
            $totalesDte = $totalesDteCache;
        }
    } catch (Throwable $e) {
        $totalesDte = [
            'documentos' => null,
            'pendientes' => null,
            'rechazados' => 0,
            'empresas' => null,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Panel administrativo Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Dashboard | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .analytics-kpi-card {
                border: 1px solid rgba(13, 110, 253, 0.2);
                box-shadow: 0 0.35rem 0.75rem rgba(15, 23, 42, 0.06);
            }

            .analytics-kpi-card .kpi-label {
                font-size: 0.74rem;
                letter-spacing: 0.04em;
            }

            .analytics-kpi-card .kpi-value {
                font-size: 1.95rem;
                line-height: 1.1;
                margin-top: 0.25rem;
                margin-bottom: 0.25rem;
            }

            .analytics-block-card {
                border: 1px solid #e9ecef;
            }

            .analytics-block-total {
                font-size: 0.82rem;
                color: #6c757d;
                margin-bottom: 0.75rem;
            }

            .analytics-list {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .analytics-list-item .analytics-item-label {
                font-size: 0.86rem;
                color: #495057;
            }

            .analytics-list-item .analytics-item-value {
                font-size: 0.86rem;
                font-weight: 700;
                color: #212529;
            }

            .analytics-country-label {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }

            .analytics-country-flag {
                font-size: 1rem;
                line-height: 1;
            }

            .analytics-progress {
                height: 0.38rem;
                background-color: rgba(13, 110, 253, 0.15);
                border-radius: 999px;
            }

            .analytics-progress .progress-bar {
                border-radius: 999px;
                background-image: linear-gradient(90deg, #0d6efd, #4f9dff);
            }

            @media (max-width: 575.98px) {
                .analytics-kpi-card .kpi-value {
                    font-size: 1.55rem;
                }
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
                <?php render_admin_topnav($pdo, $usuario, $etiquetaSesion, $perfil, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, isset($usuario) && is_array($usuario) ? $usuario : (isset($usuarioSesion) && is_array($usuarioSesion) ? $usuarioSesion : []), $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Panel de control</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                        <?php if ($mensajeMantenimiento !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensajeMantenimiento === 'success' ? 'success' : 'danger'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensajeMantenimiento, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($puedeGestionarMantenimiento): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-screwdriver-wrench me-1"></i>
                                    Modo mantenimiento
                                </div>
                                <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold mb-1">
                                            Estado actual:
                                            <span class="badge <?php echo $mantenimientoActivo ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $mantenimientoActivo ? 'ACTIVO' : 'INACTIVO'; ?>
                                            </span>
                                        </div>
                                        <div class="text-muted small">
                                            Publico: <?php echo $mantenimientoActivo ? 'maintenance.php (503)' : 'sitio habilitado'; ?>.
                                            Admin/developer autenticados mantienen acceso normal.
                                        </div>
                                        <?php if (!$mantenimientoPasswordConfigurada): ?>
                                            <div class="text-danger small mt-2">
                                                La clave de mantenimiento aun no esta configurada.
                                                <?php if ($puedeConfigurarPasswordMantenimiento): ?>
                                                    <a href="/admin/mantenimiento">Configurar ahora</a>.
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <form action="/admin/acciones/mantenimiento" method="post" class="d-inline-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfMantenimiento, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="<?php echo $mantenimientoActivo ? 'desactivar' : 'activar'; ?>" />
                                        <input
                                            type="password"
                                            name="mantenimiento_password"
                                            class="form-control"
                                            placeholder="Clave mantenimiento"
                                            autocomplete="current-password"
                                            required
                                        />
                                        <button class="btn <?php echo $mantenimientoActivo ? 'btn-success' : 'btn-warning'; ?>" type="submit">
                                            <?php echo $mantenimientoActivo ? 'Desactivar mantenimiento' : 'Activar mantenimiento'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">
                                        <div class="small text-uppercase">Mensajes nuevos</div>
                                        <div class="fs-2 fw-bold">
                                            <?php echo $totalesDashboard['mensajes_nuevos'] === null ? '--' : (int) $totalesDashboard['mensajes_nuevos']; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="<?php echo $puedeGestionarContacto ? '/admin/contacto?estado=nuevo' : '#'; ?>">Ver detalle</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-warning text-white mb-4">
                                    <div class="card-body">
                                        <div class="small text-uppercase">Proyectos</div>
                                        <div class="fs-2 fw-bold">
                                            <?php echo $totalesDashboard['proyectos'] === null ? '--' : (int) $totalesDashboard['proyectos']; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="<?php echo $puedeGestionarProyectos ? '/admin/proyectos' : '#'; ?>">Ver detalle</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-success text-white mb-4">
                                    <div class="card-body">
                                        <div class="small text-uppercase">Portafolio</div>
                                        <div class="fs-2 fw-bold">
                                            <?php echo $totalesDashboard['portafolio'] === null ? '--' : (int) $totalesDashboard['portafolio']; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="<?php echo $puedeGestionarPortafolio ? '/admin/portafolio' : '#'; ?>">Ver detalle</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-danger text-white mb-4">
                                    <div class="card-body">
                                        <div class="small text-uppercase">Servicios</div>
                                        <div class="fs-2 fw-bold">
                                            <?php echo $totalesDashboard['servicios'] === null ? '--' : (int) $totalesDashboard['servicios']; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="<?php echo $puedeGestionarServicios ? '/admin/servicios' : '#'; ?>">Ver detalle</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($puedeAccesoDte): ?>
                            <div class="card mb-4 border-info">
                                <div class="card-header bg-info-subtle">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>
                                    Gestion DTE
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="p-3 border rounded h-100">
                                                <div class="small text-uppercase text-muted">Documentos emitidos</div>
                                                <div class="fs-4 fw-bold text-info"><?php echo $totalesDte['documentos'] === null ? '--' : (int) $totalesDte['documentos']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="p-3 border rounded h-100">
                                                <div class="small text-uppercase text-muted">Libros pendientes</div>
                                                <div class="fs-4 fw-bold text-warning"><?php echo $totalesDte['pendientes'] === null ? '--' : (int) $totalesDte['pendientes']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="p-3 border rounded h-100">
                                                <div class="small text-uppercase text-muted">Empresas DTE</div>
                                                <div class="fs-4 fw-bold text-success"><?php echo $totalesDte['empresas'] === null ? '--' : (int) $totalesDte['empresas']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="p-3 border rounded h-100">
                                                <div class="small text-uppercase text-muted">Rechazados</div>
                                                <div class="fs-4 fw-bold text-danger"><?php echo (int) ($totalesDte['rechazados'] ?? 0); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-outline-info btn-sm" href="/admin/dte/">Panel DTE</a>
                                        <?php if ($puedeDteEmitir): ?>
                                            <a class="btn btn-outline-primary btn-sm" href="/admin/dte/src/pages/compras.php">Emitir / Importar</a>
                                            <a class="btn btn-outline-primary btn-sm" href="/admin/dte/src/pages/ventas_consumidor.php">Documentos emitidos</a>
                                        <?php endif; ?>
                                        <?php if ($puedeDteReportes): ?>
                                            <a class="btn btn-outline-secondary btn-sm" href="/admin/dte/src/pages/retencion_iva.php">Reportes DTE</a>
                                        <?php endif; ?>
                                        <?php if ($puedeDteConfigurar): ?>
                                            <a class="btn btn-outline-dark btn-sm" href="/admin/dte/">Configuracion DTE</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card analytics-kpi-card h-100">
                                    <div class="card-body">
                                        <div class="kpi-label text-muted text-uppercase fw-semibold">Visitas hoy</div>
                                        <div class="kpi-value fw-bold text-primary"><?php echo $totalHoy; ?></div>
                                        <div class="small text-muted">Registros del dia actual</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card analytics-kpi-card h-100">
                                    <div class="card-body">
                                        <div class="kpi-label text-muted text-uppercase fw-semibold">Usuarios unicos</div>
                                        <div class="kpi-value fw-bold text-primary"><?php echo $usuariosUnicos; ?></div>
                                        <div class="small text-muted">Identificados por IP/UA</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card analytics-kpi-card h-100">
                                    <div class="card-body">
                                        <div class="kpi-label text-muted text-uppercase fw-semibold">Paginas vistas</div>
                                        <div class="kpi-value fw-bold text-primary"><?php echo $paginasVistas; ?></div>
                                        <div class="small text-muted">Total acumulado del rango</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card analytics-kpi-card h-100">
                                    <div class="card-body">
                                        <div class="kpi-label text-muted text-uppercase fw-semibold">Promedio sesion</div>
                                        <div class="kpi-value fw-bold text-primary">
                                            <?php echo htmlspecialchars(formatear_duracion_dashboard_visitas($promedioSesionSegundos), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="small text-muted">Basado en <?php echo $totalSesiones; ?> sesiones</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 analytics-block-card">
                            <div class="card-header">
                                <i class="fas fa-chart-area me-1"></i>
                                Tendencia de visitas
                            </div>
                            <div class="card-body">
                                <form method="get" class="row g-3 align-items-end mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" for="filtro_desde">Desde</label>
                                        <input
                                            class="form-control"
                                            type="date"
                                            id="filtro_desde"
                                            name="desde"
                                            value="<?php echo htmlspecialchars($fechaDesdeFiltro, ENT_QUOTES, 'UTF-8'); ?>"
                                        />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" for="filtro_hasta">Hasta</label>
                                        <input
                                            class="form-control"
                                            type="date"
                                            id="filtro_hasta"
                                            name="hasta"
                                            value="<?php echo htmlspecialchars($fechaHastaFiltro, ENT_QUOTES, 'UTF-8'); ?>"
                                        />
                                    </div>
                                    <div class="col-md-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                                        <a href="/admin/dashboard" class="btn btn-outline-secondary">Limpiar</a>
                                    </div>
                                </form>

                                <canvas id="myAreaChart" width="100%" height="30"></canvas>
                                <div class="mt-3 small text-muted">
                                    Periodo: <strong><?php echo htmlspecialchars(formatear_fecha_dashboard_visitas($fechaDesdeFiltro), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    al
                                    <strong><?php echo htmlspecialchars(formatear_fecha_dashboard_visitas($fechaHastaFiltro), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <div class="small text-muted">
                                    Visitas del periodo: <strong><?php echo $totalPeriodo; ?></strong>
                                    | Usuarios unicos: <strong><?php echo $usuariosUnicos; ?></strong>
                                </div>
                            </div>
                            <div class="card-footer small text-muted">Actualizado automaticamente</div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12 col-lg-6">
                                <div class="card analytics-block-card h-100">
                                    <div class="card-header">
                                        <i class="fas fa-globe me-1"></i>
                                        Top paises
                                    </div>
                                    <div class="card-body">
                                        <div class="analytics-block-total">Distribucion sobre <?php echo $paginasVistas; ?> paginas vistas.</div>
                                        <?php if (empty($paisesTop)): ?>
                                            <div class="small text-muted">Sin datos para el rango seleccionado.</div>
                                        <?php else: ?>
                                            <div class="analytics-list">
                                                <?php foreach ($paisesTop as $itemPais): ?>
                                                    <div class="analytics-list-item">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span class="analytics-item-label analytics-country-label">
                                                                <span class="analytics-country-flag" aria-hidden="true"><?php echo htmlspecialchars(bandera_por_codigo_pais_dashboard((string) ($itemPais['codigo'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span><?php echo htmlspecialchars((string) $itemPais['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </span>
                                                            <span class="analytics-item-value"><?php echo (int) $itemPais['total']; ?></span>
                                                        </div>
                                                        <div class="progress analytics-progress">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo number_format((float) $itemPais['porcentaje'], 1, '.', ''); ?>%;"></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="card analytics-block-card h-100">
                                    <div class="card-header">
                                        <i class="fas fa-mobile-screen me-1"></i>
                                        Dispositivos
                                    </div>
                                    <div class="card-body">
                                        <div class="analytics-block-total">Total clasificado: <?php echo $totalDispositivos; ?> visitas.</div>
                                        <div class="analytics-list">
                                            <?php foreach ($dispositivosResumen as $itemDispositivo): ?>
                                                <div class="analytics-list-item">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="analytics-item-label"><?php echo htmlspecialchars((string) $itemDispositivo['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span class="analytics-item-value"><?php echo (int) $itemDispositivo['total']; ?></span>
                                                    </div>
                                                    <div class="progress analytics-progress">
                                                        <div class="progress-bar" role="progressbar" style="width: <?php echo number_format((float) $itemDispositivo['porcentaje'], 1, '.', ''); ?>%;"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="card analytics-block-card h-100">
                                    <div class="card-header">
                                        <i class="fas fa-window-maximize me-1"></i>
                                        Navegadores
                                    </div>
                                    <div class="card-body">
                                        <div class="analytics-block-total">Participacion sobre <?php echo $paginasVistas; ?> paginas vistas.</div>
                                        <?php if (empty($navegadoresTop)): ?>
                                            <div class="small text-muted">Sin datos para el rango seleccionado.</div>
                                        <?php else: ?>
                                            <div class="analytics-list">
                                                <?php foreach ($navegadoresTop as $itemNavegador): ?>
                                                    <div class="analytics-list-item">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span class="analytics-item-label"><?php echo htmlspecialchars((string) $itemNavegador['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="analytics-item-value"><?php echo (int) $itemNavegador['total']; ?></span>
                                                        </div>
                                                        <div class="progress analytics-progress">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo number_format((float) $itemNavegador['porcentaje'], 1, '.', ''); ?>%;"></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
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
                            <div class="text-muted">&copy;Derechos reservados SIETELSA S.A. DE C.V <?php echo date('Y'); ?></div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script defer src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script defer src="/admin/js/scripts.js?v=20260613e"></script>
        <script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script>
            window.dashboardActividadData = <?php echo json_encode($actividadChartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        </script>
        <script defer src="/admin/assets/demo/chart-area-demo.js"></script>
    </body>
</html>
