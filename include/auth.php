<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/modules_approval.php';
require_once __DIR__ . '/dte_integration.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/request_hardening.php';

sietelsa_enforce_request_hardening();

function invalidar_cache_permisos_usuario(): void
{
    iniciar_sesion_segura();
    unset($_SESSION['permisos_cache']);
}

function usuario_actual_es_admin(): bool
{
    iniciar_sesion_segura();
    $perfil = strtolower(trim((string) ($_SESSION['usuario']['perfil'] ?? '')));
    return $perfil === 'admin' || $perfil === 'administrador';
}

function usuario_actual_es_developer(): bool
{
    iniciar_sesion_segura();
    $perfil = strtolower(trim((string) ($_SESSION['usuario']['perfil'] ?? '')));
    return $perfil === 'developer';
}

function usuario_tiene_acceso_dte(?array $usuario = null): bool
{
    iniciar_sesion_segura();

    if (!usuario_autenticado()) {
        return false;
    }

    $usuario = is_array($usuario) ? $usuario : ($_SESSION['usuario'] ?? []);
    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    if (in_array($perfil, ['admin', 'administrador', 'developer'], true)) {
        return true;
    }

    global $pdo;
    if ($pdo instanceof PDO) {
        dte_asegurar_rbac_basico($pdo);
    }

    if ($perfil === 'dte') {
        return true;
    }

    if (tiene_permiso('acceso_dte')) {
        return true;
    }

    $usuarioId = (int) ($usuario['id'] ?? 0);
    if ($usuarioId <= 0) {
        return false;
    }

    if (!$pdo instanceof PDO) {
        return false;
    }

    sync_modules($pdo);
    sync_module_access_matrix($pdo);

    $slugsDte = [
        'dte_dashboard',
        'dte_compras',
        'dte_ventas_consumidor',
        'dte_ventas_contribuyente',
        'dte_retencion_iva',
        'dte',
    ];

    foreach ($slugsDte as $slugDte) {
        if (usuario_puede_acceder_modulo($usuarioId, $slugDte)) {
            return true;
        }
    }

    return false;
}

function usuario_tiene_acceso_dashboard_general(?array $usuario = null): bool
{
    iniciar_sesion_segura();

    if (!usuario_autenticado()) {
        return false;
    }

    $usuario = is_array($usuario) ? $usuario : ($_SESSION['usuario'] ?? []);
    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    if (in_array($perfil, ['admin', 'administrador', 'developer'], true)) {
        return true;
    }
    if (in_array($perfil, ['dte', 'contadro', 'contador'], true)) {
        return false;
    }

    $usuarioId = (int) ($usuario['id'] ?? 0);
    if ($usuarioId <= 0) {
        return false;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        return false;
    }

    try {
        sync_modules($pdo);
        sync_module_access_matrix($pdo);

        return usuario_puede_acceder_modulo($usuarioId, 'dashboard');
    } catch (Throwable $e) {
        return false;
    }
}

function usuario_tiene_acceso_panel_control(?array $usuario = null): bool
{
    $usuario = is_array($usuario) ? $usuario : ($_SESSION['usuario'] ?? []);

    return usuario_tiene_acceso_dashboard_general($usuario) || usuario_tiene_acceso_dte($usuario);
}

function ruta_inicio_usuario(array $usuario): string
{
    if (usuario_tiene_acceso_dashboard_general($usuario)) {
        return '/admin/dashboard';
    }

    if (usuario_tiene_acceso_dte($usuario)) {
        return '/admin/dte/';
    }

    return '/admin/dashboard';
}

function ruta_inicio_usuario_actual(): string
{
    $usuario = obtener_usuario_actual();
    if (!is_array($usuario)) {
        return '/admin/dashboard';
    }

    return ruta_inicio_usuario($usuario);
}

function ruta_request_actual(): string
{
    $ruta = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $ruta = '/' . ltrim($ruta, '/');
    if ($ruta !== '/') {
        $ruta = rtrim($ruta, '/');
    }

    return $ruta !== '' ? $ruta : '/';
}

function usuario_actual_debe_cambiar_password(): bool
{
    iniciar_sesion_segura();
    return (int) ($_SESSION['usuario']['must_change_password'] ?? 0) === 1;
}

function ruta_permitida_password_forzado(string $ruta): bool
{
    static $permitidas = [
        '/admin/primer-acceso/cambiar-clave',
        '/admin/auth/primer-acceso/cambiar-clave',
        '/admin/auth/logout',
    ];

    return in_array($ruta, $permitidas, true);
}

function sietelsa_setup_admin_habilitado_en_request(): bool
{
    return sietelsa_install_enabled() && sietelsa_request_es_localhost();
}

function existe_admin_configurado(PDO $pdo): bool
{
    if (!tabla_existe($pdo, 'usuarios') || !tabla_existe($pdo, 'perfiles')) {
        return false;
    }

    $stmt = $pdo->query(
        "SELECT 1
         FROM usuarios u
         INNER JOIN perfiles p ON p.id = u.perfil_id
         WHERE u.activo = 1
           AND LOWER(TRIM(p.nombre)) IN ('admin', 'administrador')
         LIMIT 1"
    );

    return (bool) ($stmt ? $stmt->fetchColumn() : false);
}

function hay_admin_configurado(): bool
{
    global $pdo;
    if (!$pdo instanceof PDO) {
        return false;
    }

    try {
        return existe_admin_configurado($pdo);
    } catch (Throwable $e) {
        sietelsa_log('Admin existence check failed', ['message' => $e->getMessage()]);
        return false;
    }
}

function password_fuerte_valido(string $password): bool
{
    return strlen($password) >= 12
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
}

function password_fuerte_requisitos(): string
{
    return 'Minimo 12 caracteres, con mayuscula, minuscula, numero y simbolo.';
}

function obtener_ruta_pagina_error(int $codigo): ?string
{
    $paginas = [
        401 => __DIR__ . '/../pages/pages_admin/401.html',
        403 => __DIR__ . '/../pages/pages_admin/403.html',
        404 => __DIR__ . '/../pages/pages_admin/404.html',
        500 => __DIR__ . '/../pages/pages_admin/500.html',
    ];

    return $paginas[$codigo] ?? null;
}

function mostrar_pagina_error(int $codigo): void
{
    http_response_code($codigo);

    $ruta = obtener_ruta_pagina_error($codigo);
    if ($ruta !== null && is_file($ruta)) {
        readfile($ruta);
        exit;
    }

    if ($codigo === 401) {
        exit('No autorizado.');
    }

    if ($codigo === 403) {
        exit('Acceso prohibido.');
    }

    if ($codigo === 404) {
        exit('Pagina no encontrada.');
    }

    exit('Error interno del servidor.');
}

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        return;
    }

    if (headers_sent()) {
        mostrar_pagina_error(500);
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }

    if (!session_start()) {
        mostrar_pagina_error(500);
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
}

function usuario_autenticado(): bool
{
    iniciar_sesion_segura();

    if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
        return false;
    }

    $id = (int) ($_SESSION['usuario']['id'] ?? 0);
    $email = (string) ($_SESSION['usuario']['email'] ?? '');
    return $id > 0 && $email !== '';
}

function obtener_usuario_actual(): ?array
{
    iniciar_sesion_segura();
    return $_SESSION['usuario'] ?? null;
}

function sietelsa_huella_sesion_actual(): string
{
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $acceptLanguage = trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    return hash('sha256', $userAgent . '|' . $acceptLanguage);
}

function sietelsa_huella_red_actual(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return '';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $partes = explode('.', $ip);
        if (count($partes) === 4) {
            return $partes[0] . '.' . $partes[1] . '.' . $partes[2] . '.0/24';
        }
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = @inet_pton($ip);
        if (is_string($packed) && strlen($packed) === 16) {
            $red = substr($packed, 0, 8) . str_repeat("\x00", 8);
            $normalizada = @inet_ntop($red);
            if (is_string($normalizada) && $normalizada !== '') {
                return $normalizada . '/64';
            }
        }
    }

    return '';
}

function sietelsa_guardar_huella_sesion(): void
{
    $_SESSION['security_fingerprint'] = sietelsa_huella_sesion_actual();
    $_SESSION['security_network'] = sietelsa_huella_red_actual();
}

function sietelsa_huella_sesion_valida(): bool
{
    $huellaSesion = trim((string) ($_SESSION['security_fingerprint'] ?? ''));
    if ($huellaSesion === '') {
        return false;
    }

    if (!hash_equals($huellaSesion, sietelsa_huella_sesion_actual())) {
        return false;
    }

    $redSesion = trim((string) ($_SESSION['security_network'] ?? ''));
    $redActual = sietelsa_huella_red_actual();
    if ($redSesion !== '' && $redActual !== '' && !hash_equals($redSesion, $redActual)) {
        return false;
    }

    return true;
}

function autenticar_usuario_en_sesion(array $usuario): void
{
    iniciar_sesion_segura();
    session_regenerate_id(true);
    invalidar_cache_permisos_usuario();
    $_SESSION = [];

    $_SESSION['usuario'] = [
        'id' => (int) ($usuario['id'] ?? 0),
        'nombre' => (string) ($usuario['nombre'] ?? ''),
        'username' => (string) ($usuario['username'] ?? ''),
        'email' => (string) ($usuario['email'] ?? ''),
        'perfil_id' => (int) ($usuario['perfil_id'] ?? 0),
        'perfil' => (string) ($usuario['perfil'] ?? ''),
        'must_change_password' => (int) ($usuario['must_change_password'] ?? 0),
    ];
    $_SESSION['last_activity'] = time();
    sietelsa_guardar_huella_sesion();
}

function redirigir(string $ruta): void
{
    header('Location: ' . $ruta);
    exit;
}

function require_login(array $perfilesPermitidos = []): void
{
    $timeoutInactividad = 1800;

    iniciar_sesion_segura();

    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $timeoutInactividad) {
        cerrar_sesion();
        redirigir('/admin/login?error=timeout');
    }
    $_SESSION['last_activity'] = time();

    if (!usuario_autenticado()) {
        mostrar_pagina_error(401);
    }

    if (!sietelsa_huella_sesion_valida()) {
        $metodoActual = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
        if (in_array($metodoActual, ['GET', 'HEAD', 'OPTIONS'], true)) {
            // Evita cierres de sesion falsos positivos en navegacion normal (filtros GET/calendario).
            session_regenerate_id(true);
            sietelsa_guardar_huella_sesion();
        } else {
            cerrar_sesion();
            redirigir('/admin/login?error=timeout');
        }
    }

    $rutaActual = ruta_request_actual();
    global $pdo;
    if ($pdo instanceof PDO) {
        bitacora_registrar_request_si_aplica($pdo, $_SESSION['usuario'] ?? [], $rutaActual, (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    if (usuario_actual_debe_cambiar_password() && !ruta_permitida_password_forzado($rutaActual)) {
        redirigir('/admin/primer-acceso/cambiar-clave');
    }

    if (!empty($perfilesPermitidos)) {
        $perfil = strtolower(trim((string) ($_SESSION['usuario']['perfil'] ?? '')));
        $permitidosNormalizados = array_map(static fn (string $item): string => strtolower(trim($item)), $perfilesPermitidos);
        if ($perfil !== 'developer' && !in_array($perfil, $permitidosNormalizados, true)) {
            mostrar_pagina_error(401);
        }
    }
}

function ensure_rbac_runtime(): void
{
    return;
}

function permisos_cache_sesion(int $usuarioId, int $perfilId): ?array
{
    $cacheSesion = $_SESSION['permisos_cache'] ?? null;
    if (!is_array($cacheSesion)) {
        return null;
    }

    $cacheTs = (int) ($cacheSesion['ts'] ?? 0);
    $cacheUsuario = (int) ($cacheSesion['usuario_id'] ?? 0);
    $cachePerfil = (int) ($cacheSesion['perfil_id'] ?? 0);
    $cachePermisos = is_array($cacheSesion['permisos'] ?? null) ? $cacheSesion['permisos'] : null;

    if (
        $cacheTs <= (time() - 300)
        || $cacheUsuario !== $usuarioId
        || $cachePerfil !== $perfilId
        || !is_array($cachePermisos)
    ) {
        return null;
    }

    return $cachePermisos;
}

function construir_cache_permisos_usuario(PDO $pdo, int $usuarioId, int $perfilId, bool $esAdmin): array
{
    $permisos = [];

    if (function_exists('tabla_existe') && tabla_existe($pdo, 'modulos') && tabla_existe($pdo, 'perfil_modulo')) {
        $stmt = $pdo->prepare(
            'SELECT m.nombre_modulo,
                    m.estado,
                    COALESCE(pm.aprobado, 0) AS perfil_aprobado,
                    COALESCE(pm_totales.total_aprobados, 0) AS total_aprobados
             FROM modulos m
             LEFT JOIN perfil_modulo pm
                    ON pm.modulo_id = m.id
                   AND pm.perfil_id = :perfil_id
             LEFT JOIN (
                 SELECT modulo_id, SUM(CASE WHEN aprobado = 1 THEN 1 ELSE 0 END) AS total_aprobados
                 FROM perfil_modulo
                 GROUP BY modulo_id
             ) pm_totales ON pm_totales.modulo_id = m.id'
        );
        $stmt->execute([':perfil_id' => $perfilId]);

        foreach ($stmt->fetchAll() as $fila) {
            $codigo = trim((string) ($fila['nombre_modulo'] ?? ''));
            if ($codigo === '') {
                continue;
            }

            $estado = strtolower(trim((string) ($fila['estado'] ?? 'activo')));
            $perfilAprobado = (int) ($fila['perfil_aprobado'] ?? 0) === 1;
            $totalAprobados = (int) ($fila['total_aprobados'] ?? 0);

            if ($estado !== 'activo') {
                $permisos[$codigo] = $esAdmin;
                continue;
            }

            if ($totalAprobados <= 0) {
                $permisos[$codigo] = $esAdmin;
                continue;
            }

            $permisos[$codigo] = $perfilAprobado;
        }
    }

    if (function_exists('tabla_existe') && tabla_existe($pdo, 'permisos')) {
        $tablaUsuarioPermiso = tabla_existe($pdo, 'usuario_permiso');
        $tablaPerfilPermiso = tabla_existe($pdo, 'perfil_permiso');

        if ($tablaUsuarioPermiso || $tablaPerfilPermiso) {
            $sql = 'SELECT p.codigo';
            $params = [];

            if ($tablaUsuarioPermiso) {
                $sql .= ', up.permitido AS usuario_permitido';
            } else {
                $sql .= ', NULL AS usuario_permitido';
            }

            if ($tablaPerfilPermiso) {
                $sql .= ', CASE WHEN pp.perfil_id IS NULL THEN 0 ELSE 1 END AS perfil_permitido';
            } else {
                $sql .= ', 0 AS perfil_permitido';
            }

            $sql .= ' FROM permisos p';

            if ($tablaUsuarioPermiso) {
                $sql .= ' LEFT JOIN usuario_permiso up
                          ON up.permiso_id = p.id
                         AND up.usuario_id = :usuario_id';
                $params[':usuario_id'] = $usuarioId;
            }

            if ($tablaPerfilPermiso) {
                $sql .= ' LEFT JOIN perfil_permiso pp
                          ON pp.permiso_id = p.id
                         AND pp.perfil_id = :perfil_id';
                $params[':perfil_id'] = $perfilId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll() as $fila) {
                $codigo = trim((string) ($fila['codigo'] ?? ''));
                if ($codigo === '' || array_key_exists($codigo, $permisos)) {
                    continue;
                }

                $usuarioPermitido = $fila['usuario_permitido'] ?? null;
                if ($usuarioPermitido !== null && $usuarioPermitido !== '') {
                    $permisos[$codigo] = (int) $usuarioPermitido === 1;
                    continue;
                }

                $permisos[$codigo] = (int) ($fila['perfil_permitido'] ?? 0) === 1;
            }
        }
    }

    return $permisos;
}

function tiene_permiso(string $codigoPermiso): bool
{
    global $pdo;
    static $cacheRequest = [];

    $codigoPermiso = trim($codigoPermiso);
    if ($codigoPermiso === '') {
        return false;
    }

    if (!usuario_autenticado()) {
        return false;
    }

    $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);
    $perfilId = (int) ($_SESSION['usuario']['perfil_id'] ?? 0);
    $perfilNombre = strtolower(trim((string) ($_SESSION['usuario']['perfil'] ?? '')));
    if ($usuarioId <= 0) {
        return false;
    }

    if ($perfilId <= 0) {
        return false;
    }

    $esAdmin = ($perfilNombre === 'admin' || $perfilNombre === 'administrador');
    if ($perfilNombre === 'dte') {
        $permisosDtePorDefecto = [
            'ver_dashboard',
            'ver_bitacora',
            'acceso_dte',
            'dte_ver',
            'dte_emitir',
            'dte_reportes',
        ];
        if (in_array($codigoPermiso, $permisosDtePorDefecto, true)) {
            return true;
        }
    }

    if ($pdo instanceof PDO) {
        dte_asegurar_rbac_basico($pdo);
    }

    ensure_rbac_runtime();

    $cacheRequestKey = $usuarioId . '|' . $perfilId . '|' . $codigoPermiso;
    if (array_key_exists($cacheRequestKey, $cacheRequest)) {
        return (bool) $cacheRequest[$cacheRequestKey];
    }

    $cachePermisos = permisos_cache_sesion($usuarioId, $perfilId);
    if ($cachePermisos === null) {
        $cachePermisos = construir_cache_permisos_usuario($pdo, $usuarioId, $perfilId, $esAdmin);
        $_SESSION['permisos_cache'] = [
            'ts' => time(),
            'usuario_id' => $usuarioId,
            'perfil_id' => $perfilId,
            'permisos' => $cachePermisos,
        ];
    }

    $permitido = (bool) ($cachePermisos[$codigoPermiso] ?? false);
    $cacheRequest[$cacheRequestKey] = $permitido;

    return $permitido;
}

function require_admin(): void
{
    require_login();
    if (!usuario_actual_es_admin()) {
        mostrar_pagina_error(403);
    }
}

function require_module_approved(string $slug): void
{
    require_login();
    ensure_rbac_runtime();

    if (usuario_actual_es_admin()) {
        return;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No hay conexion a base de datos para validar publicacion de modulos.');
    }

    $slug = modulos_publicacion_normalizar_slug($slug);
    if ($slug === '') {
        mostrar_pagina_error(403);
    }

    $estado = modulos_publicacion_estado_por_slug($pdo, $slug);
    if (!modulos_publicacion_esta_aprobado($estado)) {
        mostrar_pagina_error(403);
    }
}

function bloquear_si_modulo_no_aprobado_para_usuario(string $codigoModulo): void
{
    if (usuario_actual_es_admin()) {
        return;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No hay conexion a base de datos para validar publicacion de modulos.');
    }

    $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuarioId <= 0) {
        mostrar_pagina_error(403);
    }

    $slugActual = modulos_publicacion_slug_actual();
    if ($slugActual !== null) {
        if (!usuario_puede_acceder_modulo($usuarioId, $slugActual)) {
            mostrar_pagina_error(403);
        }
        return;
    }

    $slugsPorPermiso = modulos_publicacion_slugs_por_permiso($codigoModulo);
    if (!empty($slugsPorPermiso)) {
        foreach ($slugsPorPermiso as $slugPermiso) {
            if (usuario_puede_acceder_modulo($usuarioId, (string) $slugPermiso)) {
                return;
            }
        }
        mostrar_pagina_error(403);
    }

    $aprobacionPermiso = modulos_publicacion_permiso_aprobado($pdo, $codigoModulo);
    if ($aprobacionPermiso === false) {
        mostrar_pagina_error(403);
    }
}

function require_modulo(string $codigoModulo, bool $soloAdmin = false): void
{
    require_login();
    ensure_rbac_runtime();

    if ($soloAdmin && !usuario_actual_es_admin()) {
        mostrar_pagina_error(403);
    }

    if (!usuario_actual_es_admin()) {
        $slugActual = modulos_publicacion_slug_actual();
        $slugsPorPermiso = [];
        if ($slugActual === null) {
            $slugsPorPermiso = modulos_publicacion_slugs_por_permiso($codigoModulo);
        }

        if ($slugActual === null && empty($slugsPorPermiso) && !tiene_permiso($codigoModulo)) {
            mostrar_pagina_error(403);
        }
    }

    bloquear_si_modulo_no_aprobado_para_usuario($codigoModulo);
}

function require_permiso(string $codigoPermiso): void
{
    require_modulo($codigoPermiso);
}

function cerrar_sesion(): void
{
    iniciar_sesion_segura();
    invalidar_cache_permisos_usuario();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
