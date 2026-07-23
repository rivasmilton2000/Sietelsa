<?php
declare(strict_types=1);

require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/user_profile.php';

function admin_topnav_formatear_fecha(string $fechaRaw): string
{
    $fechaRaw = trim($fechaRaw);
    if ($fechaRaw === '') {
        return 'Sin fecha';
    }

    $timestamp = strtotime($fechaRaw);
    if ($timestamp === false) {
        return $fechaRaw;
    }

    return date('d/m/Y H:i', $timestamp);
}

function admin_topnav_estado_badge(string $estado): string
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

function admin_topnav_cargar_notificaciones(PDO $pdo, int $limite = 6): array
{
    try {
        $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);
        $cacheKey = 'admin_topnav_notif_v1|u:' . $usuarioId . '|l:' . $limite;
        $cache = sietelsa_cache_get('fragment', $cacheKey);
        if (is_array($cache)) {
            return $cache;
        }

        asegurar_compatibilidad_auth($pdo);
        asegurar_tabla_contacto_mensajes($pdo);

        $stmtConteo = $pdo->query("SELECT COUNT(*) FROM contacto_mensajes WHERE estado = 'nuevo'");
        $totalNuevos = (int) ($stmtConteo ? $stmtConteo->fetchColumn() : 0);

        $stmtListado = $pdo->prepare(
            'SELECT id, nombre, email, asunto, estado, creado_en
             FROM contacto_mensajes
             ORDER BY creado_en DESC
             LIMIT :limite'
        );
        $stmtListado->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmtListado->execute();
        $items = $stmtListado->fetchAll();

        $data = [
            'total_nuevos' => $totalNuevos,
            'items' => is_array($items) ? $items : [],
        ];
        sietelsa_cache_set('fragment', $cacheKey, $data, 20);
        return $data;
    } catch (Throwable $e) {
        return [
            'total_nuevos' => 0,
            'items' => [],
        ];
    }
}

function render_admin_topnav(PDO $pdo, array $usuarioActual, string $etiquetaSesion, string $perfilSesion, bool $puedeVerMensajes): void
{
    $usuarioPerfil = sietelsa_profile_current_user($pdo, $usuarioActual);
    $nombre = trim((string) ($usuarioActual['nombre'] ?? ''));
    $username = trim((string) ($usuarioActual['username'] ?? ''));
    $nombrePerfil = trim((string) ($usuarioPerfil['nombre'] ?? $nombre));
    $usernamePerfil = trim((string) ($usuarioPerfil['username'] ?? $username));
    $fotoPerfilUrl = sietelsa_profile_safe_asset_url($usuarioPerfil['foto_path'] ?? null);
    $perfilSesion = trim($perfilSesion);

    $dataNotificaciones = [
        'total_nuevos' => 0,
        'items' => [],
    ];

    if ($puedeVerMensajes) {
        $dataNotificaciones = admin_topnav_cargar_notificaciones($pdo, 6);
    }

    $totalNuevos = (int) ($dataNotificaciones['total_nuevos'] ?? 0);
    $itemsNotificaciones = is_array($dataNotificaciones['items'] ?? null) ? $dataNotificaciones['items'] : [];

    $perfilMostrar = $perfilSesion !== '' ? $perfilSesion : 'No definido';
    $perfilNormalizado = strtolower($perfilSesion);
    $esAdmin = in_array($perfilNormalizado, ['admin', 'administrador', 'developer'], true);
    $rutaRegreso = $esAdmin ? '/admin/dashboard' : '/';
    $labelRegreso = $esAdmin ? 'Dashboard' : 'Sitio';
    ?>
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <a class="navbar-brand ps-3 d-flex align-items-center gap-2" href="/admin/dashboard">
                <img src="/assets/img/logos/logoSietelsa.webp" alt="Sietelsa" width="120" height="30" decoding="async" style="height:30px;width:auto;border-radius:4px;" />
                <span>Sietelsa Admin</span>
            </a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" type="button" aria-label="Alternar menu lateral">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto me-3 me-lg-2 d-none d-md-flex align-items-center text-white-50 small">
                <?php if ($perfilSesion !== ''): ?>
                    Perfil: <?php echo htmlspecialchars($perfilSesion, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
            <a class="btn btn-outline-light btn-sm me-2" href="<?php echo htmlspecialchars($rutaRegreso, ENT_QUOTES, 'UTF-8'); ?>" title="Ir a <?php echo htmlspecialchars(strtolower($labelRegreso), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-house me-1"></i> <?php echo htmlspecialchars($labelRegreso, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <button class="btn btn-outline-light btn-sm me-2 admin-theme-toggle" type="button" data-sietelsa-theme-toggle aria-label="Activar modo oscuro" title="Cambiar tema">
                <i class="fas fa-moon me-1" data-sietelsa-theme-icon></i>
                <span class="d-none d-xl-inline" data-sietelsa-theme-label>Oscuro</span>
            </button>
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4 align-items-center">
                <li class="nav-item dropdown me-2">
                    <a class="nav-link position-relative" id="navbarNotificaciones" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones de contacto">
                        <i class="fas fa-bell fa-fw"></i>
                        <?php if ($totalNuevos > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $totalNuevos > 99 ? '99+' : (string) $totalNuevos; ?>
                                <span class="visually-hidden">mensajes nuevos</span>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end admin-notifications-menu" aria-labelledby="navbarNotificaciones">
                        <li><h6 class="dropdown-header">Correos recibidos</h6></li>
                        <?php if (!$puedeVerMensajes): ?>
                            <li><span class="dropdown-item-text text-muted">No tienes permiso para ver mensajes.</span></li>
                        <?php elseif (empty($itemsNotificaciones)): ?>
                            <li><span class="dropdown-item-text text-muted">No hay correos registrados.</span></li>
                        <?php else: ?>
                            <?php foreach ($itemsNotificaciones as $notificacion): ?>
                                <?php
                                $idMensaje = (int) ($notificacion['id'] ?? 0);
                                $estadoMensaje = trim((string) ($notificacion['estado'] ?? ''));
                                $nombreMensaje = trim((string) ($notificacion['nombre'] ?? ''));
                                $emailMensaje = trim((string) ($notificacion['email'] ?? ''));
                                $asuntoMensaje = trim((string) ($notificacion['asunto'] ?? ''));
                                $fechaMensaje = admin_topnav_formatear_fecha((string) ($notificacion['creado_en'] ?? ''));
                                ?>
                                <li>
                                    <a class="dropdown-item py-2" href="/admin/contacto?id=<?php echo $idMensaje; ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="me-2" style="min-width:0;">
                                                <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($nombreMensaje !== '' ? $nombreMensaje : $emailMensaje, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small text-muted text-truncate"><?php echo htmlspecialchars($asuntoMensaje !== '' ? $asuntoMensaje : 'Sin asunto', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small text-muted text-truncate"><?php echo htmlspecialchars($emailMensaje, ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge <?php echo admin_topnav_estado_badge($estadoMensaje); ?>"><?php echo htmlspecialchars($estadoMensaje !== '' ? $estadoMensaje : 'sin estado', ENT_QUOTES, 'UTF-8'); ?></span>
                                                <div class="small text-muted mt-1"><?php echo htmlspecialchars($fechaMensaje, ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider" /></li>
                        <?php if ($puedeVerMensajes): ?>
                            <li><a class="dropdown-item text-center" href="/admin/contacto">Ver todos los mensajes</a></li>
                        <?php else: ?>
                            <li><span class="dropdown-item-text text-muted text-center">Sin acceso a mensajes</span></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" id="navbarPerfilDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo htmlspecialchars($fotoPerfilUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Perfil" width="32" height="32" loading="lazy" decoding="async" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" />
                        <span class="d-none d-lg-inline text-white"><?php echo htmlspecialchars($nombrePerfil !== '' ? $nombrePerfil : ($usernamePerfil !== '' ? $usernamePerfil : 'Usuario'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarPerfilDropdown">
                        <li><span class="dropdown-item-text fw-semibold"><?php echo htmlspecialchars($nombrePerfil !== '' ? $nombrePerfil : 'Usuario', ENT_QUOTES, 'UTF-8'); ?></span></li>
                        <li><span class="dropdown-item-text text-muted small"><?php echo htmlspecialchars($usernamePerfil !== '' ? $usernamePerfil : 'sin usuario', ENT_QUOTES, 'UTF-8'); ?></span></li>
                        <li><span class="dropdown-item-text text-muted small">Rol: <?php echo htmlspecialchars($perfilMostrar, ENT_QUOTES, 'UTF-8'); ?></span></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#perfilUsuarioModal">Ver perfil</button></li>
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#perfilUsuarioModal">Cambiar foto de perfil</button></li>
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#perfilUsuarioModal">Cambiar contrasena</button></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="/admin/auth/logout">Cerrar sesion</a></li>
                    </ul>
                </li>
            </ul>
        </nav>

        <?php render_sietelsa_profile_modal($pdo, $usuarioPerfil, $perfilSesion, 'perfilUsuarioModal'); ?>
    <?php
}
