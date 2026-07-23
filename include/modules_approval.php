<?php
declare(strict_types=1);

function modulos_publicacion_estados_validos(): array
{
    return ['BORRADOR', 'PENDIENTE', 'APROBADO', 'RECHAZADO'];
}

function modulos_publicacion_normalizar_estado(string $estado): string
{
    $estado = strtoupper(trim($estado));
    return in_array($estado, modulos_publicacion_estados_validos(), true) ? $estado : 'BORRADOR';
}

function modulos_publicacion_normalizar_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return '';
    }

    $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-_');

    if ($slug === '' || preg_match('/^[a-z0-9][a-z0-9_-]{1,119}$/', $slug) !== 1) {
        return '';
    }

    return $slug;
}

function modulos_publicacion_normalizar_ruta(string $ruta): string
{
    $path = (string) (parse_url(trim($ruta), PHP_URL_PATH) ?: '');
    if ($path === '') {
        return '/';
    }

    $path = '/' . ltrim($path, '/');
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $path;
}

function modulos_publicacion_catalogo(): array
{
    return [
        [
            'slug' => 'dashboard',
            'seccion' => 'principal',
            'categoria' => 'principal',
            'permiso' => 'ver_dashboard',
            'ruta' => '/admin/dashboard',
            'alias_rutas' => ['/admin'],
            'label' => 'Dashboard',
            'icono' => 'fa-tachometer-alt',
            'sidebar_order' => 10,
            'solo_admin' => false,
        ],
        [
            'slug' => 'dte_dashboard',
            'seccion' => 'principal',
            'categoria' => 'facturacion_dte',
            'permiso' => 'acceso_dte',
            'ruta' => '/admin/dte/',
            'label' => 'Panel DTE',
            'icono' => 'fa-file-invoice-dollar',
            'sidebar_order' => 15,
            'solo_admin' => false,
        ],
        [
            'slug' => 'bitacora',
            'seccion' => 'principal',
            'categoria' => 'principal',
            'permiso' => 'ver_bitacora',
            'ruta' => '/admin/bitacora',
            'label' => 'Bitacora',
            'icono' => 'fa-book-open',
            'sidebar_order' => 14,
            'solo_admin' => false,
        ],
        [
            'slug' => 'dte_compras',
            'seccion' => 'administracion',
            'categoria' => 'facturacion_dte',
            'permiso' => 'dte_emitir',
            'ruta' => '/admin/dte/src/pages/compras.php',
            'label' => 'DTE Compras',
            'icono' => 'fa-cart-shopping',
            'sidebar_order' => 16,
            'solo_admin' => false,
        ],
        [
            'slug' => 'dte_ventas_consumidor',
            'seccion' => 'administracion',
            'categoria' => 'facturacion_dte',
            'permiso' => 'dte_emitir',
            'ruta' => '/admin/dte/src/pages/ventas_consumidor.php',
            'label' => 'DTE Ventas CF',
            'icono' => 'fa-receipt',
            'sidebar_order' => 17,
            'solo_admin' => false,
        ],
        [
            'slug' => 'dte_ventas_contribuyente',
            'seccion' => 'administracion',
            'categoria' => 'facturacion_dte',
            'permiso' => 'dte_emitir',
            'ruta' => '/admin/dte/src/pages/ventas_contribuyente.php',
            'label' => 'DTE Ventas CCF',
            'icono' => 'fa-file-signature',
            'sidebar_order' => 18,
            'solo_admin' => false,
        ],
        [
            'slug' => 'dte_retencion_iva',
            'seccion' => 'administracion',
            'categoria' => 'facturacion_dte',
            'permiso' => 'dte_reportes',
            'ruta' => '/admin/dte/src/pages/retencion_iva.php',
            'label' => 'DTE Retencion IVA',
            'icono' => 'fa-percent',
            'sidebar_order' => 19,
            'solo_admin' => false,
        ],
        [
            'slug' => 'usuarios_registro',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_usuarios',
            'ruta' => '/admin/usuarios/registro',
            'label' => 'Registrar usuario',
            'icono' => 'fa-user-plus',
            'sidebar_order' => 20,
            'solo_admin' => false,
        ],
        [
            'slug' => 'usuarios_editar',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_usuarios',
            'ruta' => '/admin/usuarios/editar',
            'label' => 'Editar usuarios',
            'icono' => 'fa-user-gear',
            'sidebar_order' => 30,
            'solo_admin' => false,
        ],
        [
            'slug' => 'usuarios_eliminar',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_usuarios',
            'ruta' => '/admin/usuarios/eliminar',
            'alias_rutas' => ['/admin/acciones/usuarios/eliminar'],
            'label' => 'Eliminar usuarios',
            'icono' => 'fa-user-minus',
            'sidebar_order' => 40,
            'solo_admin' => false,
        ],
        [
            'slug' => 'perfiles',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_perfiles',
            'ruta' => '/admin/perfiles',
            'alias_rutas' => ['/admin/acciones/perfiles'],
            'label' => 'Tipos de perfil',
            'icono' => 'fa-id-badge',
            'sidebar_order' => 50,
            'solo_admin' => true,
        ],
        [
            'slug' => 'servicios',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_servicios',
            'ruta' => '/admin/servicios',
            'alias_rutas' => ['/admin/acciones/servicios'],
            'label' => 'Gestionar servicios',
            'icono' => 'fa-screwdriver-wrench',
            'sidebar_order' => 60,
            'solo_admin' => false,
        ],
        [
            'slug' => 'portafolio',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_portafolio',
            'ruta' => '/admin/portafolio',
            'alias_rutas' => ['/admin/acciones/portafolio'],
            'label' => 'Gestionar portafolio',
            'icono' => 'fa-images',
            'sidebar_order' => 70,
            'solo_admin' => false,
        ],
        [
            'slug' => 'proyectos',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_proyectos',
            'ruta' => '/admin/proyectos',
            'alias_rutas' => ['/admin/acciones/proyectos'],
            'label' => 'Gestionar proyectos',
            'icono' => 'fa-diagram-project',
            'sidebar_order' => 80,
            'solo_admin' => false,
        ],
        [
            'slug' => 'nosotros',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_nosotros',
            'ruta' => '/admin/nosotros',
            'alias_rutas' => ['/admin/acciones/nosotros'],
            'label' => 'Gestionar nosotros',
            'icono' => 'fa-building',
            'sidebar_order' => 90,
            'solo_admin' => false,
        ],
        [
            'slug' => 'contacto',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_contacto',
            'ruta' => '/admin/contacto',
            'alias_rutas' => ['/admin/acciones/contacto-responder'],
            'label' => 'Mensajes contacto',
            'icono' => 'fa-envelope-open-text',
            'sidebar_order' => 100,
            'solo_admin' => false,
        ],
        [
            'slug' => 'backup',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => 'gestionar_backup',
            'ruta' => '/admin/backup',
            'alias_rutas' => ['/admin/acciones/backup'],
            'label' => 'Backup BD',
            'icono' => 'fa-database',
            'sidebar_order' => 110,
            'solo_admin' => false,
        ],
        [
            'slug' => 'mantenimiento',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => '',
            'ruta' => '/admin/mantenimiento',
            'alias_rutas' => ['/admin/acciones/mantenimiento/password', '/admin/acciones/mantenimiento/reporte'],
            'label' => 'Mantenimiento',
            'icono' => 'fa-shield-halved',
            'sidebar_order' => 115,
            'solo_admin' => true,
        ],
        [
            'slug' => 'modules_approval',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => '',
            'ruta' => '/admin/modulos/aprobacion',
            'alias_rutas' => ['/admin/acciones/modulos/aprobacion'],
            'label' => 'Aprobacion de modulos',
            'icono' => 'fa-check-double',
            'sidebar_order' => 120,
            'solo_admin' => true,
        ],
        [
            'slug' => 'modules_permissions',
            'seccion' => 'administracion',
            'categoria' => 'administracion',
            'permiso' => '',
            'ruta' => '/admin/modulos/permisos',
            'alias_rutas' => ['/admin/acciones/modulos/permisos'],
            'label' => 'Permisos de modulos',
            'icono' => 'fa-user-lock',
            'sidebar_order' => 130,
            'solo_admin' => true,
        ],
    ];
}

function modulos_publicacion_slug_desde_ruta(string $ruta): ?string
{
    $rutaNormalizada = modulos_publicacion_normalizar_ruta($ruta);
    $catalogo = modulos_publicacion_catalogo();

    foreach ($catalogo as $item) {
        $slug = modulos_publicacion_normalizar_slug((string) ($item['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $rutas = [
            modulos_publicacion_normalizar_ruta((string) ($item['ruta'] ?? '')),
        ];

        $aliasRutas = $item['alias_rutas'] ?? [];
        if (is_array($aliasRutas)) {
            foreach ($aliasRutas as $alias) {
                $rutas[] = modulos_publicacion_normalizar_ruta((string) $alias);
            }
        }

        foreach ($rutas as $rutaItem) {
            if ($rutaItem === $rutaNormalizada) {
                return $slug;
            }
        }
    }

    return null;
}

function modulos_publicacion_slug_actual(): ?string
{
    $rutaActual = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    return modulos_publicacion_slug_desde_ruta($rutaActual);
}

function modulos_publicacion_slug_por_permiso(string $permiso): ?string
{
    $slugs = modulos_publicacion_slugs_por_permiso($permiso);
    return $slugs[0] ?? null;
}

function modulos_publicacion_slugs_por_permiso(string $permiso): array
{
    $permiso = trim($permiso);
    if ($permiso === '') {
        return [];
    }

    $catalogo = modulos_publicacion_catalogo();
    $slugs = [];
    foreach ($catalogo as $item) {
        $permisoItem = trim((string) ($item['permiso'] ?? ''));
        if ($permisoItem !== $permiso) {
            continue;
        }

        $slug = modulos_publicacion_normalizar_slug((string) ($item['slug'] ?? ''));
        if ($slug !== '') {
            $slugs[$slug] = true;
        }
    }

    return array_keys($slugs);
}

function modulos_publicacion_mapa(PDO $pdo, bool $forzarRecarga = false): array
{
    static $cache = null;

    if (!$forzarRecarga && is_array($cache)) {
        return $cache;
    }

    if (!function_exists('tabla_existe') || !tabla_existe($pdo, 'system_modules')) {
        $cache = [];
        return $cache;
    }

    $stmt = $pdo->query(
        'SELECT id,
                slug,
                display_name,
                route_path,
                category,
                permission_code,
                icon_class,
                sidebar_order,
                is_admin_only,
                approval_status,
                rejection_reason,
                reviewed_by_user_id,
                reviewed_at,
                created_at,
                updated_at
         FROM system_modules'
    );

    $mapa = [];
    if ($stmt) {
        foreach ($stmt->fetchAll() as $fila) {
            $slug = modulos_publicacion_normalizar_slug((string) ($fila['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $mapa[$slug] = $fila;
        }
    }

    $cache = $mapa;
    return $cache;
}

function modulos_publicacion_estado_por_slug(PDO $pdo, string $slug): ?string
{
    $slug = modulos_publicacion_normalizar_slug($slug);
    if ($slug === '') {
        return null;
    }

    $mapa = modulos_publicacion_mapa($pdo);
    if (!isset($mapa[$slug])) {
        return null;
    }

    return modulos_publicacion_normalizar_estado((string) ($mapa[$slug]['approval_status'] ?? ''));
}

function modulos_publicacion_esta_aprobado(?string $estado): bool
{
    return modulos_publicacion_normalizar_estado((string) $estado) === 'APROBADO';
}

function modulos_publicacion_permiso_aprobado(PDO $pdo, string $permiso): ?bool
{
    $permiso = trim($permiso);
    if ($permiso === '') {
        return null;
    }

    $mapa = modulos_publicacion_mapa($pdo);
    $hayAsignados = false;
    foreach ($mapa as $fila) {
        $permisoFila = trim((string) ($fila['permission_code'] ?? ''));
        if ($permisoFila !== $permiso) {
            continue;
        }

        $hayAsignados = true;
        $estado = modulos_publicacion_normalizar_estado((string) ($fila['approval_status'] ?? ''));
        if ($estado === 'APROBADO') {
            return true;
        }
    }

    if (!$hayAsignados) {
        return null;
    }

    return false;
}

function modulos_rbac_tablas_disponibles(PDO $pdo): bool
{
    if (!function_exists('tabla_existe')) {
        return false;
    }

    return tabla_existe($pdo, 'system_modules')
        && tabla_existe($pdo, 'profile_module_access')
        && tabla_existe($pdo, 'user_module_override');
}

function modulos_rbac_es_perfil_admin(string $perfilNombre): bool
{
    $perfil = strtolower(trim($perfilNombre));
    return $perfil === 'admin' || $perfil === 'administrador';
}

function modulos_rbac_contexto_usuario(PDO $pdo, int $userId): ?array
{
    static $cache = [];

    if ($userId <= 0) {
        return null;
    }

    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    $stmt = $pdo->prepare(
        'SELECT u.id,
                u.perfil_id,
                LOWER(TRIM(COALESCE(p.nombre, \'\'))) AS perfil_nombre
         FROM usuarios u
         LEFT JOIN perfiles p ON p.id = u.perfil_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $fila = $stmt->fetch();

    if (!is_array($fila)) {
        $cache[$userId] = null;
        return null;
    }

    $perfilId = (int) ($fila['perfil_id'] ?? 0);
    $perfilNombre = trim((string) ($fila['perfil_nombre'] ?? ''));

    if ($perfilNombre === '') {
        try {
            $perfilDte = dte_buscar_perfil($pdo, 'dte', true);
            if ($perfilDte === null) {
                $perfilDte = dte_buscar_perfil($pdo, 'dte', false);
            }
            if (is_array($perfilDte) && (int) ($perfilDte['id'] ?? 0) > 0) {
                $perfilId = (int) $perfilDte['id'];
                $perfilNombre = strtolower(trim((string) ($perfilDte['nombre'] ?? 'dte')));

                $stmtFixPerfil = $pdo->prepare(
                    'UPDATE usuarios
                     SET perfil_id = :perfil_id
                     WHERE id = :usuario_id
                     LIMIT 1'
                );
                $stmtFixPerfil->execute([
                    ':perfil_id' => $perfilId,
                    ':usuario_id' => $userId,
                ]);
            }
        } catch (Throwable $e) {
            // Ignora errores de autorreparacion de perfil para no romper navegacion.
        }
    }

    $cache[$userId] = [
        'id' => (int) ($fila['id'] ?? 0),
        'perfil_id' => $perfilId,
        'perfil_nombre' => $perfilNombre,
    ];

    return $cache[$userId];
}

function modulos_rbac_permisos_perfil(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    if (!function_exists('tabla_existe') || !tabla_existe($pdo, 'perfil_permiso') || !tabla_existe($pdo, 'permisos')) {
        return $cache;
    }

    $stmt = $pdo->query(
        'SELECT pp.perfil_id, p.codigo
         FROM perfil_permiso pp
         INNER JOIN permisos p ON p.id = pp.permiso_id'
    );
    if (!$stmt) {
        return $cache;
    }

    foreach ($stmt->fetchAll() as $fila) {
        $perfilId = (int) ($fila['perfil_id'] ?? 0);
        $codigo = trim((string) ($fila['codigo'] ?? ''));
        if ($perfilId <= 0 || $codigo === '') {
            continue;
        }

        if (!isset($cache[$perfilId])) {
            $cache[$perfilId] = [];
        }
        $cache[$perfilId][$codigo] = true;
    }

    return $cache;
}

function modulos_rbac_perfil_base_legacy(PDO $pdo, int $perfilId, string $permiso): bool
{
    if ($perfilId <= 0) {
        return false;
    }

    $permiso = trim($permiso);
    if ($permiso === '') {
        return false;
    }

    $permisosPerfil = modulos_rbac_permisos_perfil($pdo);
    return isset($permisosPerfil[$perfilId][$permiso]);
}

function modulos_rbac_profile_module_allowed(PDO $pdo, int $perfilId, int $moduleId, string $permiso): bool
{
    static $cache = [];

    $cacheKey = $perfilId . '|' . $moduleId;
    if (array_key_exists($cacheKey, $cache)) {
        return (bool) $cache[$cacheKey];
    }

    if (!function_exists('tabla_existe') || !tabla_existe($pdo, 'profile_module_access')) {
        $cache[$cacheKey] = modulos_rbac_perfil_base_legacy($pdo, $perfilId, $permiso);
        return (bool) $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        'SELECT allowed
         FROM profile_module_access
         WHERE profile_id = :profile_id
           AND module_id = :module_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $perfilId,
        ':module_id' => $moduleId,
    ]);

    $valor = $stmt->fetchColumn();
    if ($valor === false) {
        $cache[$cacheKey] = modulos_rbac_perfil_base_legacy($pdo, $perfilId, $permiso);
        return (bool) $cache[$cacheKey];
    }

    $cache[$cacheKey] = ((int) $valor) === 1;
    return (bool) $cache[$cacheKey];
}

function modulos_rbac_override_usuario(PDO $pdo, int $userId, int $moduleId): string
{
    static $cache = [];
    $cacheKey = $userId . '|' . $moduleId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if (!function_exists('tabla_existe') || !tabla_existe($pdo, 'user_module_override')) {
        $cache[$cacheKey] = 'inherit';
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        'SELECT effect
         FROM user_module_override
         WHERE user_id = :user_id
           AND module_id = :module_id
         LIMIT 1'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':module_id' => $moduleId,
    ]);

    $effect = strtolower(trim((string) ($stmt->fetchColumn() ?: 'inherit')));
    if (!in_array($effect, ['inherit', 'allow', 'deny'], true)) {
        $effect = 'inherit';
    }

    $cache[$cacheKey] = $effect;
    return $cache[$cacheKey];
}

function usuario_puede_acceder_modulo(int $userId, string $moduleSlug): bool
{
    global $pdo;

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No hay conexion a base de datos para validar acceso a modulos.');
    }

    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $moduleSlug = modulos_publicacion_normalizar_slug($moduleSlug);
    if ($moduleSlug === '') {
        return false;
    }

    $contextoUsuario = modulos_rbac_contexto_usuario($pdo, $userId);
    if (!is_array($contextoUsuario)) {
        return false;
    }

    $esAdmin = modulos_rbac_es_perfil_admin((string) ($contextoUsuario['perfil_nombre'] ?? ''));
    if ($esAdmin) {
        return true;
    }

    $modulos = modulos_publicacion_mapa($pdo);
    $modulo = $modulos[$moduleSlug] ?? null;
    if (!is_array($modulo)) {
        return false;
    }

    $estado = modulos_publicacion_normalizar_estado((string) ($modulo['approval_status'] ?? 'BORRADOR'));
    if ($estado !== 'APROBADO') {
        return false;
    }

    if ((int) ($modulo['is_admin_only'] ?? 0) === 1) {
        return false;
    }

    $moduleId = (int) ($modulo['id'] ?? 0);
    $perfilId = (int) ($contextoUsuario['perfil_id'] ?? 0);
    $permiso = trim((string) ($modulo['permission_code'] ?? ''));

    if ($moduleId <= 0 || $perfilId <= 0) {
        return false;
    }

    $permitidoPorPerfil = modulos_rbac_profile_module_allowed($pdo, $perfilId, $moduleId, $permiso);
    $override = modulos_rbac_override_usuario($pdo, $userId, $moduleId);

    if ($override === 'allow') {
        return true;
    }
    if ($override === 'deny') {
        return false;
    }

    return $permitidoPorPerfil;
}

function sync_module_access_matrix(PDO $pdo): void
{
    static $sincronizadoEnRequest = false;
    if ($sincronizadoEnRequest) {
        return;
    }

    if (!function_exists('tabla_existe') || !tabla_existe($pdo, 'system_modules') || !tabla_existe($pdo, 'perfiles')) {
        return;
    }

    if (!tabla_existe($pdo, 'profile_module_access') || !tabla_existe($pdo, 'user_module_override')) {
        return;
    }

    $perfiles = $pdo->query('SELECT id, nombre FROM perfiles')->fetchAll();
    $modulos = $pdo->query(
        'SELECT id, permission_code, is_admin_only
         FROM system_modules'
    )->fetchAll();

    if (!is_array($perfiles) || !is_array($modulos) || empty($perfiles) || empty($modulos)) {
        $sincronizadoEnRequest = true;
        return;
    }

    $permisosPorPerfil = modulos_rbac_permisos_perfil($pdo);
    $stmtInsertProfile = $pdo->prepare(
        'INSERT INTO profile_module_access (profile_id, module_id, allowed)
         VALUES (:profile_id, :module_id, :allowed)
         ON DUPLICATE KEY UPDATE profile_id = VALUES(profile_id)'
    );

    foreach ($perfiles as $perfil) {
        $perfilId = (int) ($perfil['id'] ?? 0);
        $perfilNombre = (string) ($perfil['nombre'] ?? '');
        if ($perfilId <= 0) {
            continue;
        }

        $perfilEsAdmin = modulos_rbac_es_perfil_admin($perfilNombre);
        foreach ($modulos as $modulo) {
            $moduleId = (int) ($modulo['id'] ?? 0);
            if ($moduleId <= 0) {
                continue;
            }

            $permiso = trim((string) ($modulo['permission_code'] ?? ''));
            $esSoloAdmin = ((int) ($modulo['is_admin_only'] ?? 0) === 1);

            $allowed = 0;
            if ($perfilEsAdmin) {
                $allowed = 1;
            } elseif (!$esSoloAdmin && $permiso !== '' && isset($permisosPorPerfil[$perfilId][$permiso])) {
                $allowed = 1;
            }

            $stmtInsertProfile->execute([
                ':profile_id' => $perfilId,
                ':module_id' => $moduleId,
                ':allowed' => $allowed,
            ]);
        }
    }

    if (tabla_existe($pdo, 'usuario_permiso') && tabla_existe($pdo, 'permisos')) {
        $stmtOverridesLegacy = $pdo->query(
            'SELECT up.usuario_id, p.codigo, up.permitido
             FROM usuario_permiso up
             INNER JOIN permisos p ON p.id = up.permiso_id'
        );
        $rowsOverridesLegacy = $stmtOverridesLegacy ? $stmtOverridesLegacy->fetchAll() : [];
        if (is_array($rowsOverridesLegacy) && !empty($rowsOverridesLegacy)) {
            $modulesByPermission = [];
            foreach ($modulos as $modulo) {
                $permiso = trim((string) ($modulo['permission_code'] ?? ''));
                if ($permiso === '') {
                    continue;
                }
                if (!isset($modulesByPermission[$permiso])) {
                    $modulesByPermission[$permiso] = [];
                }
                $modulesByPermission[$permiso][] = (int) ($modulo['id'] ?? 0);
            }

            $stmtInsertOverride = $pdo->prepare(
                'INSERT INTO user_module_override (user_id, module_id, effect)
                 VALUES (:user_id, :module_id, :effect)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
            );

            foreach ($rowsOverridesLegacy as $rowLegacy) {
                $userId = (int) ($rowLegacy['usuario_id'] ?? 0);
                $permiso = trim((string) ($rowLegacy['codigo'] ?? ''));
                $effect = ((int) ($rowLegacy['permitido'] ?? 0) === 1) ? 'allow' : 'deny';
                if ($userId <= 0 || $permiso === '' || !isset($modulesByPermission[$permiso])) {
                    continue;
                }

                foreach ($modulesByPermission[$permiso] as $moduleId) {
                    if ($moduleId <= 0) {
                        continue;
                    }
                    $stmtInsertOverride->execute([
                        ':user_id' => $userId,
                        ':module_id' => $moduleId,
                        ':effect' => $effect,
                    ]);
                }
            }
        }
    }

    $sincronizadoEnRequest = true;
}

function sync_modules(PDO $pdo): void
{
    static $sincronizadoEnRequest = false;
    if ($sincronizadoEnRequest) {
        return;
    }

    if (!function_exists('tabla_existe') || !tabla_existe($pdo, 'system_modules')) {
        return;
    }

    $catalogo = modulos_publicacion_catalogo();
    $existentes = modulos_publicacion_mapa($pdo, true);

    $stmtInsert = $pdo->prepare(
        'INSERT INTO system_modules (
            slug,
            display_name,
            route_path,
            category,
            permission_code,
            icon_class,
            sidebar_order,
            is_admin_only,
            approval_status,
            rejection_reason,
            reviewed_by_user_id,
            reviewed_at
         ) VALUES (
            :slug,
            :display_name,
            :route_path,
            :category,
            :permission_code,
            :icon_class,
            :sidebar_order,
            :is_admin_only,
            :approval_status,
            NULL,
            NULL,
            NULL
         )'
    );

    $stmtUpdate = $pdo->prepare(
        'UPDATE system_modules
         SET display_name = :display_name,
             route_path = :route_path,
             category = :category,
             permission_code = :permission_code,
             icon_class = :icon_class,
             sidebar_order = :sidebar_order,
             is_admin_only = :is_admin_only
         WHERE slug = :slug
         LIMIT 1'
    );
    $stmtAprobarForzado = $pdo->prepare(
        'UPDATE system_modules
         SET approval_status = :approval_status,
             rejection_reason = NULL,
             reviewed_by_user_id = NULL,
             reviewed_at = NULL
         WHERE slug = :slug
         LIMIT 1'
    );

    $ordenFallback = 0;
    foreach ($catalogo as $item) {
        $slug = modulos_publicacion_normalizar_slug((string) ($item['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $ruta = modulos_publicacion_normalizar_ruta((string) ($item['ruta'] ?? ''));
        $label = trim((string) ($item['label'] ?? $slug));
        $categoria = trim((string) ($item['categoria'] ?? $item['seccion'] ?? 'general'));
        $permiso = trim((string) ($item['permiso'] ?? ''));
        $icono = trim((string) ($item['icono'] ?? ''));
        $orden = isset($item['sidebar_order']) ? (int) $item['sidebar_order'] : (++$ordenFallback);
        $soloAdmin = !empty($item['solo_admin']) ? 1 : 0;
        $estadoInicial = ($slug === 'bitacora') ? 'APROBADO' : 'BORRADOR';

        if (!isset($existentes[$slug])) {
            $stmtInsert->execute([
                ':slug' => $slug,
                ':display_name' => ($label !== '' ? $label : $slug),
                ':route_path' => $ruta,
                ':category' => ($categoria !== '' ? $categoria : null),
                ':permission_code' => ($permiso !== '' ? $permiso : null),
                ':icon_class' => ($icono !== '' ? $icono : null),
                ':sidebar_order' => max(0, $orden),
                ':is_admin_only' => $soloAdmin,
                ':approval_status' => $estadoInicial,
            ]);
            continue;
        }

        $stmtUpdate->execute([
            ':slug' => $slug,
            ':display_name' => ($label !== '' ? $label : $slug),
            ':route_path' => $ruta,
            ':category' => ($categoria !== '' ? $categoria : null),
            ':permission_code' => ($permiso !== '' ? $permiso : null),
            ':icon_class' => ($icono !== '' ? $icono : null),
            ':sidebar_order' => max(0, $orden),
            ':is_admin_only' => $soloAdmin,
        ]);

        if ($slug === 'bitacora') {
            $stmtAprobarForzado->execute([
                ':approval_status' => 'APROBADO',
                ':slug' => $slug,
            ]);
        }
    }

    modulos_publicacion_mapa($pdo, true);
    $sincronizadoEnRequest = true;
}
