<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/bitacora.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../dte_sietelsa/src/config/session.php';

require_modulo('ver_bitacora');

global $pdo;
bitacora_asegurar_tabla($pdo);

$usuarioSesion = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuarioSesion['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuarioSesion['username'] ?? ''));
$perfilSesion = trim((string) ($usuarioSesion['perfil'] ?? ''));
$perfilSesionNormalizado = strtolower($perfilSesion);
$usuarioSesionId = (int) ($usuarioSesion['id'] ?? 0);
$esAdminSesion = usuario_actual_es_admin();
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');

$usarVistaDte = in_array($perfilSesionNormalizado, ['dte', 'contadro', 'contador'], true);

$etiquetaSesion = $nombreSesion !== '' ? $nombreSesion : 'Usuario';
if ($usernameSesion !== '') {
    $etiquetaSesion .= ' (' . $usernameSesion . ')';
}

$limite = (int) ($_GET['limite'] ?? 200);
if ($limite < 50) {
    $limite = 50;
}
if ($limite > 1000) {
    $limite = 1000;
}

$usuarioFiltroId = (int) ($_GET['usuario_id'] ?? 0);
if (!$esAdminSesion) {
    $usuarioFiltroId = $usuarioSesionId;
}

$opcionesUsuarios = [];
if ($esAdminSesion) {
    $cacheUsuariosKey = 'bitacora_opciones_usuarios_v2';
    $cacheUsuarios = sietelsa_cache_get('query', $cacheUsuariosKey);
    if (is_array($cacheUsuarios)) {
        $opcionesUsuarios = $cacheUsuarios;
    } else {
        $stmtUsuarios = $pdo->query(
            "SELECT DISTINCT
                    b.usuario_id,
                    COALESCE(NULLIF(u.nombre, ''), b.usuario_nombre, CONCAT('Usuario #', b.usuario_id)) AS nombre,
                    COALESCE(NULLIF(u.username, ''), b.usuario_username, '') AS username
             FROM actividad_bitacora b
             LEFT JOIN usuarios u ON u.id = b.usuario_id
             ORDER BY nombre ASC, username ASC"
        );
        $opcionesUsuarios = $stmtUsuarios ? $stmtUsuarios->fetchAll() : [];
        sietelsa_cache_set('query', $cacheUsuariosKey, $opcionesUsuarios, 30);
    }
}

$sql = "SELECT
            b.id,
            b.usuario_id,
            b.usuario_nombre,
            b.usuario_username,
            b.perfil_nombre,
            b.modulo_slug,
            b.ruta,
            b.metodo,
            b.ip_origen,
            b.creado_en,
            COALESCE(NULLIF(u.nombre, ''), b.usuario_nombre) AS nombre_actual,
            COALESCE(NULLIF(u.username, ''), b.usuario_username) AS username_actual,
            COALESCE(NULLIF(p.nombre, ''), b.perfil_nombre) AS perfil_actual
        FROM actividad_bitacora b
        LEFT JOIN usuarios u ON u.id = b.usuario_id
        LEFT JOIN perfiles p ON p.id = u.perfil_id";

$params = [];
if ($usuarioFiltroId > 0) {
    $sql .= ' WHERE b.usuario_id = :usuario_id';
    $params[':usuario_id'] = $usuarioFiltroId;
}

if ($esAdminSesion) {
    $sql .= " ORDER BY COALESCE(NULLIF(u.username, ''), b.usuario_username) ASC, b.creado_en DESC, b.id DESC";
} else {
    $sql .= ' ORDER BY b.creado_en DESC, b.id DESC';
}
$sql .= ' LIMIT :limite';

$cacheRegistrosKey = 'bitacora_registros_v2'
    . '|viewer:' . $usuarioSesionId
    . '|admin:' . ($esAdminSesion ? '1' : '0')
    . '|filtro:' . $usuarioFiltroId
    . '|lim:' . $limite;
$cacheRegistros = sietelsa_cache_get('query', $cacheRegistrosKey);

if (is_array($cacheRegistros)) {
    $registros = $cacheRegistros;
} else {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $registros = $stmt->fetchAll();
    sietelsa_cache_set('query', $cacheRegistrosKey, $registros, 12);
}

$grupos = [];
foreach ($registros as $registro) {
    $usuarioId = (int) ($registro['usuario_id'] ?? 0);
    $nombre = trim((string) ($registro['nombre_actual'] ?? $registro['usuario_nombre'] ?? ''));
    $username = trim((string) ($registro['username_actual'] ?? $registro['usuario_username'] ?? ''));
    $perfil = trim((string) ($registro['perfil_actual'] ?? $registro['perfil_nombre'] ?? ''));
    $clave = $usuarioId . '|' . strtolower($username);

    if (!isset($grupos[$clave])) {
        $grupos[$clave] = [
            'usuario_id' => $usuarioId,
            'nombre' => $nombre !== '' ? $nombre : 'Sin nombre',
            'username' => $username !== '' ? $username : 'sin-username',
            'perfil' => $perfil !== '' ? $perfil : 'sin perfil',
            'registros' => [],
        ];
    }

    $grupos[$clave]['registros'][] = $registro;
}

$basePath = app_url('src/');
$session = sessionData();
$empresaActivaNavbar = null;
?>
<?php if ($usarVistaDte): ?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Bitacora - <?php echo htmlspecialchars(app_name()); ?></title>
    <link rel="stylesheet" href="/admin/dte/assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="/admin/dte/assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="/admin/dte/assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="/admin/dte/assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="/admin/dte/assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="/admin/dte/assets/css/style.css?v=20260613e">
    <link rel="icon" type="image/jpeg" href="/assets/img/logos/sietelsaPestana.jpg" />
    <style>
      .bitacora-toolbar .form-select,
      .bitacora-toolbar .btn {
        min-height: 44px;
      }

      .bitacora-table {
        margin-bottom: 0;
      }

      .bitacora-table thead th {
        font-size: 0.76rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6c7383;
      }

      .bitacora-table tbody td {
        font-size: 0.9rem;
        vertical-align: middle;
      }

      .bitacora-method {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 66px;
        min-height: 28px;
        padding: 0 0.55rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.12);
        color: #34318f;
        font-weight: 700;
        font-size: 0.74rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .bitacora-ruta {
        color: #3b3f58;
        word-break: break-word;
      }

      .bitacora-empty {
        border: 1px solid rgba(124, 134, 153, 0.2);
      }
    </style>
  </head>
  <body>
    <div class="container-scroller">
      <?php include __DIR__ . '/../../dte_sietelsa/src/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/../../dte_sietelsa/src/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <h3 class="card-title mb-1">Bitacora</h3>
                    <p class="text-muted mb-0">Actividad reciente de usuarios por modulo, ruta, metodo e IP.</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mb-4">
              <div class="card-body">
                <form class="row g-3 bitacora-toolbar" method="get" action="/admin/bitacora">
                  <?php if ($esAdminSesion): ?>
                    <div class="col-md-6">
                      <label class="form-label" for="usuario_id">Usuario</label>
                      <select class="form-select" id="usuario_id" name="usuario_id">
                        <option value="0">Todos</option>
                        <?php foreach ($opcionesUsuarios as $opcion): ?>
                          <?php
                            $opcionUsuarioId = (int) ($opcion['usuario_id'] ?? 0);
                            $labelNombre = trim((string) ($opcion['nombre'] ?? 'Sin nombre'));
                            $labelUsername = trim((string) ($opcion['username'] ?? 'sin-username'));
                          ?>
                          <option value="<?php echo $opcionUsuarioId; ?>" <?php echo $opcionUsuarioId === $usuarioFiltroId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars('#' . $opcionUsuarioId . ' - ' . $labelNombre . ' (' . $labelUsername . ')', ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  <?php endif; ?>
                  <div class="col-md-3">
                    <label class="form-label" for="limite">Limite</label>
                    <select class="form-select" id="limite" name="limite">
                      <?php foreach ([50, 100, 200, 500, 1000] as $opcionLimite): ?>
                        <option value="<?php echo $opcionLimite; ?>" <?php echo $opcionLimite === $limite ? 'selected' : ''; ?>>
                          <?php echo $opcionLimite; ?> registros
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary" type="submit">Aplicar</button>
                    <a class="btn btn-light" href="/admin/bitacora">Limpiar</a>
                  </div>
                </form>
              </div>
            </div>

            <?php if (empty($grupos)): ?>
              <div class="card bitacora-empty">
                <div class="card-body text-muted">No hay actividad registrada para los filtros seleccionados.</div>
              </div>
            <?php else: ?>
              <?php foreach ($grupos as $grupo): ?>
                <div class="card mb-4">
                  <div class="card-body pb-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                      <h4 class="card-title mb-0">
                        <i class="ti-user me-1"></i>
                        <?php echo htmlspecialchars((string) $grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="text-muted">(<?php echo htmlspecialchars((string) $grupo['username'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                      </h4>
                      <span class="badge badge-primary"><?php echo htmlspecialchars((string) $grupo['perfil'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                  </div>
                  <div class="table-responsive mt-3">
                    <table class="table table-hover bitacora-table">
                      <thead>
                        <tr>
                          <th>Fecha</th>
                          <th>Metodo</th>
                          <th>Modulo</th>
                          <th>Ruta</th>
                          <th>IP</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($grupo['registros'] as $registro): ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string) ($registro['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="bitacora-method"><?php echo htmlspecialchars((string) ($registro['metodo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) ($registro['modulo_slug'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="bitacora-ruta"><?php echo htmlspecialchars((string) ($registro['ruta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($registro['ip_origen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php include __DIR__ . '/../../dte_sietelsa/src/partials/_footer.php'; ?>
          </div>
        </div>
      </div>
    </div>

    <script defer src="/admin/dte/assets/vendors/js/vendor.bundle.base.js"></script>
    <script defer src="/admin/dte/assets/js/off-canvas.js"></script>
    <script defer src="/admin/dte/assets/js/template.js?v=20260613e"></script>
  </body>
</html>
<?php else: ?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Bitacora de actividad de usuarios" />
        <meta name="author" content="Sietelsa" />
        <title>Bitacora | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script defer src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php render_admin_topnav($pdo, $usuarioSesion, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, $usuarioSesion, $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Bitacora</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Bitacora</li>
                        </ol>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-filter me-1"></i> Filtros
                            </div>
                            <div class="card-body">
                                <form class="row g-3" method="get" action="/admin/bitacora">
                                    <?php if ($esAdminSesion): ?>
                                        <div class="col-md-6">
                                            <label class="form-label" for="usuario_id">Usuario</label>
                                            <select class="form-select" id="usuario_id" name="usuario_id">
                                                <option value="0">Todos</option>
                                                <?php foreach ($opcionesUsuarios as $opcion): ?>
                                                    <?php
                                                    $opcionUsuarioId = (int) ($opcion['usuario_id'] ?? 0);
                                                    $labelNombre = trim((string) ($opcion['nombre'] ?? 'Sin nombre'));
                                                    $labelUsername = trim((string) ($opcion['username'] ?? 'sin-username'));
                                                    ?>
                                                    <option value="<?php echo $opcionUsuarioId; ?>" <?php echo $opcionUsuarioId === $usuarioFiltroId ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars('#' . $opcionUsuarioId . ' - ' . $labelNombre . ' (' . $labelUsername . ')', ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-3">
                                        <label class="form-label" for="limite">Limite</label>
                                        <select class="form-select" id="limite" name="limite">
                                            <?php foreach ([50, 100, 200, 500, 1000] as $opcionLimite): ?>
                                                <option value="<?php echo $opcionLimite; ?>" <?php echo $opcionLimite === $limite ? 'selected' : ''; ?>>
                                                    <?php echo $opcionLimite; ?> registros
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button class="btn btn-primary me-2" type="submit">Aplicar</button>
                                        <a class="btn btn-outline-secondary" href="/admin/bitacora">Limpiar</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (empty($grupos)): ?>
                            <div class="alert alert-info">No hay actividad registrada para los filtros seleccionados.</div>
                        <?php else: ?>
                            <?php foreach ($grupos as $grupo): ?>
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-user-clock me-1"></i>
                                            <?php echo htmlspecialchars((string) $grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                            <span class="text-muted">(<?php echo htmlspecialchars((string) $grupo['username'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                        </span>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars((string) $grupo['perfil'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Metodo</th>
                                                        <th>Modulo</th>
                                                        <th>Ruta</th>
                                                        <th>IP</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($grupo['registros'] as $registro): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars((string) ($registro['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string) ($registro['metodo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string) ($registro['modulo_slug'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string) ($registro['ruta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string) ($registro['ip_origen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
        <script defer src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script defer src="/admin/js/scripts.js?v=20260613e"></script>
    </body>
</html>
<?php endif; ?>
