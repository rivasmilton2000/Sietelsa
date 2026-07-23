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
sync_module_access_matrix($pdo);

$usuarioSesion = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuarioSesion['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuarioSesion['username'] ?? ''));
$perfilSesion = trim((string) ($usuarioSesion['perfil'] ?? ''));
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');

$etiquetaSesion = $nombreSesion !== '' ? $nombreSesion : 'Usuario';
if ($usernameSesion !== '') {
    $etiquetaSesion .= ' (' . $usernameSesion . ')';
}

$busquedaUsuario = trim((string) ($_GET['q'] ?? ''));
$usuarioSeleccionadoId = (int) ($_GET['user_id'] ?? 0);
$perfilSeleccionadoId = (int) ($_GET['profile_id'] ?? 0);

$perfiles = $pdo->query('SELECT id, nombre, descripcion FROM perfiles ORDER BY nombre ASC')->fetchAll();
$modulos = $pdo->query(
    'SELECT id,
            slug,
            display_name,
            route_path,
            category,
            permission_code,
            is_admin_only,
            approval_status,
            sidebar_order
     FROM system_modules
     ORDER BY category ASC, sidebar_order ASC, display_name ASC'
)->fetchAll();

$usuarios = [];
$sqlUsuarios = 'SELECT u.id,
                       u.nombre,
                       u.username,
                       u.email,
                       u.activo,
                       u.perfil_id,
                       p.nombre AS perfil_nombre
                FROM usuarios u
                INNER JOIN perfiles p ON p.id = u.perfil_id';
$paramsUsuarios = [];

if ($busquedaUsuario !== '') {
    if (ctype_digit($busquedaUsuario)) {
        $sqlUsuarios .= ' WHERE u.id = :id_exacto
                          OR u.nombre LIKE :texto
                          OR u.username LIKE :texto
                          OR u.email LIKE :texto';
        $paramsUsuarios[':id_exacto'] = (int) $busquedaUsuario;
    } else {
        $sqlUsuarios .= ' WHERE u.nombre LIKE :texto
                          OR u.username LIKE :texto
                          OR u.email LIKE :texto';
    }

    $paramsUsuarios[':texto'] = '%' . $busquedaUsuario . '%';
}

$sqlUsuarios .= ' ORDER BY u.nombre ASC, u.id ASC LIMIT 50';
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
$stmtUsuarios->execute($paramsUsuarios);
$usuarios = $stmtUsuarios->fetchAll();

$usuarioSeleccionado = null;
if ($usuarioSeleccionadoId > 0) {
    foreach ($usuarios as $usuarioItem) {
        if ((int) ($usuarioItem['id'] ?? 0) === $usuarioSeleccionadoId) {
            $usuarioSeleccionado = $usuarioItem;
            break;
        }
    }

    if (!is_array($usuarioSeleccionado)) {
        $stmtUsuarioUnico = $pdo->prepare(
            'SELECT u.id,
                    u.nombre,
                    u.username,
                    u.email,
                    u.activo,
                    u.perfil_id,
                    p.nombre AS perfil_nombre
             FROM usuarios u
             INNER JOIN perfiles p ON p.id = u.perfil_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmtUsuarioUnico->execute([':id' => $usuarioSeleccionadoId]);
        $usuarioSeleccionado = $stmtUsuarioUnico->fetch() ?: null;
    }
}

if (!is_array($usuarioSeleccionado) && !empty($usuarios)) {
    $usuarioSeleccionado = $usuarios[0];
    $usuarioSeleccionadoId = (int) ($usuarioSeleccionado['id'] ?? 0);
}

if ($perfilSeleccionadoId <= 0 && is_array($usuarioSeleccionado)) {
    $perfilSeleccionadoId = (int) ($usuarioSeleccionado['perfil_id'] ?? 0);
}
if ($perfilSeleccionadoId <= 0 && !empty($perfiles)) {
    $perfilSeleccionadoId = (int) ($perfiles[0]['id'] ?? 0);
}

$perfilSeleccionado = null;
foreach ($perfiles as $perfilItem) {
    if ((int) ($perfilItem['id'] ?? 0) === $perfilSeleccionadoId) {
        $perfilSeleccionado = $perfilItem;
        break;
    }
}

$cargarAccesosPerfil = static function (int $profileId) use ($pdo): array {
    if ($profileId <= 0) {
        return [];
    }

    $accesos = [];
    $stmt = $pdo->prepare(
        'SELECT module_id, allowed
         FROM profile_module_access
         WHERE profile_id = :profile_id'
    );
    $stmt->execute([':profile_id' => $profileId]);
    foreach ($stmt->fetchAll() as $filaPerfilAcceso) {
        $accesos[(int) ($filaPerfilAcceso['module_id'] ?? 0)] = ((int) ($filaPerfilAcceso['allowed'] ?? 0) === 1);
    }

    return $accesos;
};

$accesosPerfil = $cargarAccesosPerfil($perfilSeleccionadoId);
$perfilUsuarioSeleccionadoId = is_array($usuarioSeleccionado) ? (int) ($usuarioSeleccionado['perfil_id'] ?? 0) : 0;
$accesosPerfilUsuario = $perfilUsuarioSeleccionadoId === $perfilSeleccionadoId
    ? $accesosPerfil
    : $cargarAccesosPerfil($perfilUsuarioSeleccionadoId);

$overridesUsuario = [];
if ($usuarioSeleccionadoId > 0) {
    $stmtOverrides = $pdo->prepare(
        'SELECT module_id, effect
         FROM user_module_override
         WHERE user_id = :user_id'
    );
    $stmtOverrides->execute([':user_id' => $usuarioSeleccionadoId]);
    foreach ($stmtOverrides->fetchAll() as $filaOverride) {
        $moduleId = (int) ($filaOverride['module_id'] ?? 0);
        $effect = strtolower(trim((string) ($filaOverride['effect'] ?? 'inherit')));
        if (!in_array($effect, ['inherit', 'allow', 'deny'], true)) {
            $effect = 'inherit';
        }
        $overridesUsuario[$moduleId] = $effect;
    }
}

$ok = strtolower(trim((string) ($_GET['ok'] ?? '')));
$error = strtolower(trim((string) ($_GET['error'] ?? '')));
$mensaje = '';
$tipoMensaje = 'success';

$mensajesOk = [
    'perfil_guardado' => 'Permisos por perfil guardados correctamente.',
    'usuario_guardado' => 'Overrides por usuario guardados correctamente.',
    'usuario_restaurado' => 'Overrides de usuario restaurados a herencia de perfil.',
    'usuario_permitido_todo' => 'Se aplico permitir todo para el usuario seleccionado.',
    'usuario_denegado_todo' => 'Se aplico denegar todo para el usuario seleccionado.',
];
$mensajesError = [
    'metodo' => 'Metodo no permitido para esta accion.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga e intenta de nuevo.',
    'accion' => 'Accion no valida para permisos de modulos.',
    'modulos' => 'No se encontraron modulos del sistema para administrar.',
    'profile' => 'Perfil seleccionado no valido.',
    'user' => 'Usuario seleccionado no valido.',
    'db' => 'No se pudo guardar por un error de base de datos.',
    'general' => 'No se pudo completar la accion solicitada.',
];
if (isset($mensajesOk[$ok])) {
    $mensaje = $mensajesOk[$ok];
} elseif (isset($mensajesError[$error])) {
    $mensaje = $mensajesError[$error];
    $tipoMensaje = 'danger';
}

$csrfModulosPermisos = csrf_token('admin_modulos_permisos');

function modulos_permisos_badge_estado(string $estado): string
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

function modulos_permisos_label_override(string $effect): string
{
    if ($effect === 'allow') {
        return 'Permitir';
    }
    if ($effect === 'deny') {
        return 'Denegar';
    }

    return 'Usar perfil';
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Permisos de modulos por perfil y usuario" />
        <meta name="author" content="Sietelsa" />
        <title>Permisos de modulos | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php render_admin_topnav($pdo, $usuarioSesion, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, $usuarioSesion, $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Permisos de modulos</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Permisos de modulos</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user me-1"></i>
                                Seleccion de usuario
                            </div>
                            <div class="card-body">
                                <form class="row g-3" method="get" action="/admin/modulos/permisos">
                                    <div class="col-md-5">
                                        <label class="form-label" for="q">Buscar usuario (nombre, correo, username o ID)</label>
                                        <input class="form-control" id="q" name="q" type="text" value="<?php echo htmlspecialchars($busquedaUsuario, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="user_id">Usuario</label>
                                        <select class="form-select" id="user_id" name="user_id">
                                            <option value="0">Selecciona usuario</option>
                                            <?php foreach ($usuarios as $itemUsuario): ?>
                                                <?php
                                                $idUsuario = (int) ($itemUsuario['id'] ?? 0);
                                                $nombreUsuario = trim((string) ($itemUsuario['nombre'] ?? ''));
                                                $emailUsuario = trim((string) ($itemUsuario['email'] ?? ''));
                                                $perfilUsuario = trim((string) ($itemUsuario['perfil_nombre'] ?? ''));
                                                $labelUsuario = sprintf('#%d - %s (%s) [%s]', $idUsuario, $nombreUsuario !== '' ? $nombreUsuario : 'Sin nombre', $emailUsuario !== '' ? $emailUsuario : 'sin correo', $perfilUsuario !== '' ? $perfilUsuario : 'sin perfil');
                                                ?>
                                                <option value="<?php echo $idUsuario; ?>" <?php echo $idUsuario === $usuarioSeleccionadoId ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($labelUsuario, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="profile_id">Perfil para editar plantilla</label>
                                        <select class="form-select" id="profile_id" name="profile_id">
                                            <?php foreach ($perfiles as $itemPerfil): ?>
                                                <?php $idPerfil = (int) ($itemPerfil['id'] ?? 0); ?>
                                                <option value="<?php echo $idPerfil; ?>" <?php echo $idPerfil === $perfilSeleccionadoId ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars((string) ($itemPerfil['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 d-flex gap-2">
                                        <button class="btn btn-primary" type="submit">Cargar</button>
                                        <a class="btn btn-outline-secondary" href="/admin/modulos/permisos">Limpiar</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-xl-4">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-address-card me-1"></i>
                                        Usuario seleccionado
                                    </div>
                                    <div class="card-body">
                                        <?php if (!is_array($usuarioSeleccionado)): ?>
                                            <p class="text-muted mb-0">No hay usuario seleccionado.</p>
                                        <?php else: ?>
                                            <div class="mb-2"><strong>ID:</strong> <?php echo (int) ($usuarioSeleccionado['id'] ?? 0); ?></div>
                                            <div class="mb-2"><strong>Nombre:</strong> <?php echo htmlspecialchars((string) ($usuarioSeleccionado['nombre'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars((string) ($usuarioSeleccionado['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars((string) ($usuarioSeleccionado['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="mb-2"><strong>Perfil:</strong> <?php echo htmlspecialchars((string) ($usuarioSeleccionado['perfil_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="mb-0">
                                                <strong>Estado:</strong>
                                                <?php if ((int) ($usuarioSeleccionado['activo'] ?? 0) === 1): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-8">
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-id-badge me-1"></i>Plantilla por perfil</span>
                                        <span class="small text-muted">Perfil: <?php echo htmlspecialchars((string) ($perfilSeleccionado['nombre'] ?? 'No seleccionado'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($perfilSeleccionadoId <= 0): ?>
                                            <p class="text-muted mb-0">Selecciona un perfil para editar su plantilla.</p>
                                        <?php else: ?>
                                            <form method="post" action="/admin/acciones/modulos/permisos">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulosPermisos, ENT_QUOTES, 'UTF-8'); ?>" />
                                                <input type="hidden" name="accion" value="save_profile" />
                                                <input type="hidden" name="profile_id" value="<?php echo $perfilSeleccionadoId; ?>" />
                                                <input type="hidden" name="user_id" value="<?php echo $usuarioSeleccionadoId; ?>" />
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered align-middle">
                                                        <thead>
                                                            <tr>
                                                                <th>Permitir</th>
                                                                <th>Modulo</th>
                                                                <th>Ruta</th>
                                                                <th>Estado global</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($modulos as $modulo): ?>
                                                                <?php
                                                                $moduleId = (int) ($modulo['id'] ?? 0);
                                                                $allowed = (bool) ($accesosPerfil[$moduleId] ?? false);
                                                                ?>
                                                                <tr>
                                                                    <td class="text-center">
                                                                        <input class="form-check-input" type="checkbox" name="allowed_modules[]" value="<?php echo $moduleId; ?>" <?php echo $allowed ? 'checked' : ''; ?> />
                                                                    </td>
                                                                    <td>
                                                                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($modulo['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($modulo['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                    </td>
                                                                    <td><code><?php echo htmlspecialchars((string) ($modulo['route_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                                                    <td>
                                                                        <span class="badge <?php echo modulos_permisos_badge_estado((string) ($modulo['approval_status'] ?? 'BORRADOR')); ?>">
                                                                            <?php echo htmlspecialchars(modulos_publicacion_normalizar_estado((string) ($modulo['approval_status'] ?? 'BORRADOR')), ENT_QUOTES, 'UTF-8'); ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <button class="btn btn-primary" type="submit">Guardar permisos de perfil</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-shield me-1"></i>Overrides por usuario</span>
                                <span class="small text-muted">
                                    <?php echo is_array($usuarioSeleccionado) ? ('Usuario #' . (int) ($usuarioSeleccionado['id'] ?? 0)) : 'Sin usuario seleccionado'; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (!is_array($usuarioSeleccionado)): ?>
                                    <p class="mb-0 text-muted">Selecciona un usuario para administrar sus overrides.</p>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <form method="post" action="/admin/acciones/modulos/permisos" class="m-0">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulosPermisos, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="restore_user_overrides" />
                                            <input type="hidden" name="user_id" value="<?php echo $usuarioSeleccionadoId; ?>" />
                                            <input type="hidden" name="profile_id" value="<?php echo $perfilSeleccionadoId; ?>" />
                                            <button class="btn btn-outline-secondary btn-sm" type="submit">Restaurar a perfil</button>
                                        </form>
                                        <form method="post" action="/admin/acciones/modulos/permisos" class="m-0">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulosPermisos, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="allow_all_user" />
                                            <input type="hidden" name="user_id" value="<?php echo $usuarioSeleccionadoId; ?>" />
                                            <input type="hidden" name="profile_id" value="<?php echo $perfilSeleccionadoId; ?>" />
                                            <button class="btn btn-success btn-sm" type="submit">Permitir todo</button>
                                        </form>
                                        <form method="post" action="/admin/acciones/modulos/permisos" class="m-0">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulosPermisos, ENT_QUOTES, 'UTF-8'); ?>" />
                                            <input type="hidden" name="accion" value="deny_all_user" />
                                            <input type="hidden" name="user_id" value="<?php echo $usuarioSeleccionadoId; ?>" />
                                            <input type="hidden" name="profile_id" value="<?php echo $perfilSeleccionadoId; ?>" />
                                            <button class="btn btn-danger btn-sm" type="submit">Denegar todo</button>
                                        </form>
                                    </div>

                                    <form method="post" action="/admin/acciones/modulos/permisos">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfModulosPermisos, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="save_user_overrides" />
                                        <input type="hidden" name="user_id" value="<?php echo $usuarioSeleccionadoId; ?>" />
                                        <input type="hidden" name="profile_id" value="<?php echo $perfilSeleccionadoId; ?>" />

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Modulo</th>
                                                        <th>Ruta</th>
                                                        <th>Categoria</th>
                                                        <th>Estado global</th>
                                                        <th>Permiso perfil</th>
                                                        <th>Override usuario</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($modulos as $modulo): ?>
                                                        <?php
                                                        $moduleId = (int) ($modulo['id'] ?? 0);
                                                        $permitidoPerfil = (bool) ($accesosPerfilUsuario[$moduleId] ?? false);
                                                        $effect = (string) ($overridesUsuario[$moduleId] ?? 'inherit');
                                                        $effect = in_array($effect, ['inherit', 'allow', 'deny'], true) ? $effect : 'inherit';
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($modulo['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($modulo['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <?php if ((int) ($modulo['is_admin_only'] ?? 0) === 1): ?>
                                                                    <span class="badge bg-dark mt-1">Solo admin</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <code><?php echo htmlspecialchars((string) ($modulo['route_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                                                <?php if (trim((string) ($modulo['permission_code'] ?? '')) !== ''): ?>
                                                                    <div class="small text-muted">Permiso: <?php echo htmlspecialchars((string) ($modulo['permission_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars((string) ($modulo['category'] ?? 'general'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo modulos_permisos_badge_estado((string) ($modulo['approval_status'] ?? 'BORRADOR')); ?>">
                                                                    <?php echo htmlspecialchars(modulos_publicacion_normalizar_estado((string) ($modulo['approval_status'] ?? 'BORRADOR')), ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($permitidoPerfil): ?>
                                                                    <span class="badge bg-success">Permitido</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Denegado</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <select class="form-select form-select-sm" name="override_effect[<?php echo $moduleId; ?>]">
                                                                    <option value="inherit" <?php echo $effect === 'inherit' ? 'selected' : ''; ?>>Usar perfil</option>
                                                                    <option value="allow" <?php echo $effect === 'allow' ? 'selected' : ''; ?>>Permitir</option>
                                                                    <option value="deny" <?php echo $effect === 'deny' ? 'selected' : ''; ?>>Denegar</option>
                                                                </select>
                                                                <div class="small text-muted mt-1"><?php echo htmlspecialchars(modulos_permisos_label_override($effect), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <button class="btn btn-primary" type="submit">Guardar permisos del usuario</button>
                                    </form>
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
