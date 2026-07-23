<?php
declare(strict_types=1);

function ejecutar_sql_instalacion(PDO $pdo, string $sqlPath): void
{
    if (!file_exists($sqlPath)) {
        throw new RuntimeException('No se encontro el archivo SQL en: ' . $sqlPath);
    }

    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer el archivo SQL.');
    }

    // Elimina BOM UTF-8 si el archivo fue guardado con esa marca.
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sentencias = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($sentencias as $sentencia) {
        if ($sentencia === '') {
            continue;
        }

        // En runtime no debemos crear/cambiar base de datos; solo usar la ya configurada.
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $sentencia) === 1) {
            continue;
        }

        $pdo->exec($sentencia);
    }
}

function instalar_esquema_base(PDO $pdo): void
{
    $sqlPath = __DIR__ . '/../sql/sietelsa_auth.sql';
    ejecutar_sql_instalacion($pdo, $sqlPath);
}

function asegurar_tabla_servicios(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS servicios (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(120) NOT NULL,
        descripcion VARCHAR(1000) NULL,
        icono VARCHAR(80) NOT NULL DEFAULT \'fa-circle-info\',
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');
}

function asegurar_tabla_portafolio(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS portafolio (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(120) NOT NULL,
        descripcion VARCHAR(1200) NULL,
        imagen_path VARCHAR(255) NOT NULL,
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');
}

function asegurar_tabla_portafolio_imagenes(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS portafolio_imagenes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        portafolio_id INT UNSIGNED NOT NULL,
        imagen_path VARCHAR(255) NOT NULL,
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_portafolio_imagenes_portafolio
            FOREIGN KEY (portafolio_id) REFERENCES portafolio(id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB');
}

function asegurar_tabla_proyectos(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS proyectos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(120) NOT NULL,
        portafolio_id INT UNSIGNED NULL,
        imagen_path VARCHAR(255) NOT NULL,
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_proyectos_portafolio
            FOREIGN KEY (portafolio_id) REFERENCES portafolio(id)
            ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB');

    if (!columna_existe($pdo, 'proyectos', 'portafolio_id')) {
        $pdo->exec('ALTER TABLE proyectos ADD COLUMN portafolio_id INT UNSIGNED NULL AFTER titulo');
    }

    if (columna_existe($pdo, 'proyectos', 'categoria') && tabla_existe($pdo, 'portafolio')) {
        $pdo->exec('UPDATE proyectos pr
                    JOIN portafolio pf ON pf.titulo = pr.categoria
                    SET pr.portafolio_id = pf.id
                    WHERE pr.portafolio_id IS NULL
                      AND pr.categoria IS NOT NULL
                      AND TRIM(pr.categoria) <> \'\'');
        $pdo->exec('ALTER TABLE proyectos DROP COLUMN categoria');
    }

    if (columna_existe($pdo, 'proyectos', 'descripcion')) {
        $pdo->exec('ALTER TABLE proyectos DROP COLUMN descripcion');
    }

    if (tabla_existe($pdo, 'portafolio') && !restriccion_existe($pdo, 'proyectos', 'fk_proyectos_portafolio')) {
        $pdo->exec('ALTER TABLE proyectos
                    ADD CONSTRAINT fk_proyectos_portafolio
                    FOREIGN KEY (portafolio_id) REFERENCES portafolio(id)
                    ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

function asegurar_tabla_nosotros(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS nosotros (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(120) NOT NULL,
        descripcion_1 TEXT NULL,
        descripcion_2 TEXT NULL,
        descripcion_3 TEXT NULL,
        imagen_path VARCHAR(255) NULL,
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');
}

function asegurar_tabla_contacto_mensajes(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS contacto_mensajes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        email VARCHAR(120) NOT NULL,
        telefono VARCHAR(40) NULL,
        asunto VARCHAR(180) NULL,
        mensaje TEXT NOT NULL,
        estado VARCHAR(20) NOT NULL DEFAULT \'nuevo\',
        respuesta_admin TEXT NULL,
        respondido_por_usuario_id INT UNSIGNED NULL,
        respondido_en DATETIME NULL,
        notificado TINYINT(1) NOT NULL DEFAULT 0,
        error_notificacion VARCHAR(255) NULL,
        ip_origen VARCHAR(45) NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');

    if (!columna_existe($pdo, 'contacto_mensajes', 'asunto')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN asunto VARCHAR(180) NULL AFTER telefono');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'estado')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT \'nuevo\' AFTER mensaje');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'respuesta_admin')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN respuesta_admin TEXT NULL AFTER estado');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'respondido_por_usuario_id')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN respondido_por_usuario_id INT UNSIGNED NULL AFTER respuesta_admin');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'respondido_en')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN respondido_en DATETIME NULL AFTER respondido_por_usuario_id');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'notificado')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN notificado TINYINT(1) NOT NULL DEFAULT 0 AFTER respondido_en');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'error_notificacion')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN error_notificacion VARCHAR(255) NULL AFTER notificado');
    }

    if (!columna_existe($pdo, 'contacto_mensajes', 'ip_origen')) {
        $pdo->exec('ALTER TABLE contacto_mensajes ADD COLUMN ip_origen VARCHAR(45) NULL AFTER error_notificacion');
    }

    if (!indice_existe($pdo, 'contacto_mensajes', 'idx_contacto_estado_creado')) {
        $pdo->exec('CREATE INDEX idx_contacto_estado_creado ON contacto_mensajes (estado, creado_en)');
    }

    if (tabla_existe($pdo, 'usuarios') && !restriccion_existe($pdo, 'contacto_mensajes', 'fk_contacto_respondido_por_usuario')) {
        $pdo->exec('ALTER TABLE contacto_mensajes
                    ADD CONSTRAINT fk_contacto_respondido_por_usuario
                    FOREIGN KEY (respondido_por_usuario_id) REFERENCES usuarios(id)
                    ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

function asegurar_tabla_visitas_sitio(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS visitas_sitio (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pagina VARCHAR(120) NOT NULL DEFAULT \'inicio\',
        ip_origen VARCHAR(45) NULL,
        pais_codigo CHAR(2) NULL,
        pais_nombre VARCHAR(120) NULL,
        user_agent VARCHAR(255) NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_visitas_creado (creado_en),
        INDEX idx_visitas_pagina_creado (pagina, creado_en),
        INDEX idx_visitas_pais_creado (pais_codigo, creado_en)
    ) ENGINE=InnoDB');
}

function asegurar_tabla_mantenimiento_eventos(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS mantenimiento_eventos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NULL,
        usuario_nombre VARCHAR(120) NULL,
        usuario_username VARCHAR(60) NULL,
        accion VARCHAR(60) NOT NULL,
        detalle VARCHAR(255) NULL,
        ip_origen VARCHAR(45) NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');

    if (!indice_existe($pdo, 'mantenimiento_eventos', 'idx_mantenimiento_eventos_creado')) {
        $pdo->exec('CREATE INDEX idx_mantenimiento_eventos_creado ON mantenimiento_eventos (creado_en)');
    }

    if (!indice_existe($pdo, 'mantenimiento_eventos', 'idx_mantenimiento_eventos_usuario')) {
        $pdo->exec('CREATE INDEX idx_mantenimiento_eventos_usuario ON mantenimiento_eventos (usuario_id)');
    }

    if (!indice_existe($pdo, 'mantenimiento_eventos', 'idx_mantenimiento_eventos_accion')) {
        $pdo->exec('CREATE INDEX idx_mantenimiento_eventos_accion ON mantenimiento_eventos (accion)');
    }

    if (tabla_existe($pdo, 'usuarios') && !restriccion_existe($pdo, 'mantenimiento_eventos', 'fk_mantenimiento_eventos_usuario')) {
        $pdo->exec('ALTER TABLE mantenimiento_eventos
                    ADD CONSTRAINT fk_mantenimiento_eventos_usuario
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                    ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

function asegurar_tabla_configuracion_sistema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS configuracion_sistema (
        clave VARCHAR(80) NOT NULL PRIMARY KEY,
        valor VARCHAR(255) NOT NULL,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');

    $stmt = $pdo->prepare(
        'INSERT INTO configuracion_sistema (clave, valor)
         VALUES (:clave, :valor)
         ON DUPLICATE KEY UPDATE clave = VALUES(clave)'
    );
    $stmt->execute([
        ':clave' => 'mantenimiento_activo',
        ':valor' => '0',
    ]);
}

function asegurar_esquema_publico_cacheado(PDO $pdo): void
{
    static $ejecutadoEnRequest = false;
    if ($ejecutadoEnRequest) {
        return;
    }

    $cacheKey = md5((string) getenv('DB_HOST') . '|' . (string) getenv('DB_PORT') . '|' . (string) getenv('DB_NAME'));
    $cachePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sietelsa_public_schema_v1_' . $cacheKey . '.ok';

    if (is_file($cachePath)) {
        $ejecutadoEnRequest = true;
        return;
    }

    asegurar_tabla_servicios($pdo);
    asegurar_tabla_portafolio($pdo);
    asegurar_tabla_portafolio_imagenes($pdo);
    asegurar_tabla_proyectos($pdo);
    asegurar_tabla_nosotros($pdo);
    asegurar_tabla_visitas_sitio($pdo);
    asegurar_tabla_mantenimiento_eventos($pdo);
    asegurar_tabla_configuracion_sistema($pdo);

    @file_put_contents($cachePath, (new DateTimeImmutable())->format(DATE_ATOM));
    $ejecutadoEnRequest = true;
}

function columna_existe(PDO $pdo, string $tabla, string $columna): bool
{
    $sql = 'SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabla
              AND COLUMN_NAME = :columna
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tabla' => $tabla,
        ':columna' => $columna,
    ]);

    return (bool) $stmt->fetchColumn();
}

function tabla_existe(PDO $pdo, string $tabla): bool
{
    $sql = 'SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabla
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tabla' => $tabla]);

    return (bool) $stmt->fetchColumn();
}

function indice_existe(PDO $pdo, string $tabla, string $indice): bool
{
    $sql = 'SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabla
              AND INDEX_NAME = :indice
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tabla' => $tabla,
        ':indice' => $indice,
    ]);

    return (bool) $stmt->fetchColumn();
}

function restriccion_existe(PDO $pdo, string $tabla, string $restriccion): bool
{
    $sql = 'SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabla
              AND CONSTRAINT_NAME = :restriccion
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tabla' => $tabla,
        ':restriccion' => $restriccion,
    ]);

    return (bool) $stmt->fetchColumn();
}

function asegurar_perfiles_base(PDO $pdo): void
{
    $perfiles = [
        ['nombre' => 'admin', 'descripcion' => 'Control total del sistema'],
        ['nombre' => 'contadro', 'descripcion' => 'Perfil contable con acceso operativo por usuario'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO perfiles (nombre, descripcion, activo)
         VALUES (:nombre, :descripcion, 1)
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), activo = 1'
    );

    foreach ($perfiles as $perfil) {
        $stmt->execute([
            ':nombre' => $perfil['nombre'],
            ':descripcion' => $perfil['descripcion'],
        ]);
    }
}

function asegurar_permisos_base(PDO $pdo): void
{
    $permisos = [
        ['codigo' => 'ver_dashboard', 'descripcion' => 'Puede ingresar al dashboard'],
        ['codigo' => 'ver_bitacora', 'descripcion' => 'Puede consultar la bitacora de actividad'],
        ['codigo' => 'gestionar_usuarios', 'descripcion' => 'Puede crear o editar usuarios'],
        ['codigo' => 'ver_reportes', 'descripcion' => 'Puede ver reportes'],
        ['codigo' => 'gestionar_contenido', 'descripcion' => 'Permiso general heredado para gestionar contenido'],
        ['codigo' => 'gestionar_servicios', 'descripcion' => 'Puede administrar el modulo de servicios'],
        ['codigo' => 'gestionar_portafolio', 'descripcion' => 'Puede administrar el modulo de portafolio'],
        ['codigo' => 'gestionar_proyectos', 'descripcion' => 'Puede administrar el modulo de proyectos'],
        ['codigo' => 'gestionar_nosotros', 'descripcion' => 'Puede administrar el modulo de nosotros'],
        ['codigo' => 'gestionar_contacto', 'descripcion' => 'Puede gestionar los mensajes de contacto'],
        ['codigo' => 'gestionar_backup', 'descripcion' => 'Puede generar y descargar respaldos SQL'],
        ['codigo' => 'gestionar_perfiles', 'descripcion' => 'Puede crear, editar o eliminar perfiles'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO permisos (codigo, descripcion)
         VALUES (:codigo, :descripcion)
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)'
    );

    foreach ($permisos as $permiso) {
        $stmt->execute([
            ':codigo' => $permiso['codigo'],
            ':descripcion' => $permiso['descripcion'],
        ]);
    }
}

function asegurar_tabla_modulos(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS modulos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre_modulo VARCHAR(80) NOT NULL UNIQUE,
        descripcion VARCHAR(150) NULL,
        estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
        fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
}

function asegurar_tabla_perfil_modulo(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS perfil_modulo (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        perfil_id INT UNSIGNED NOT NULL,
        modulo_id INT UNSIGNED NOT NULL,
        aprobado TINYINT(1) NOT NULL DEFAULT 1,
        fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_perfil_modulo (perfil_id, modulo_id),
        CONSTRAINT fk_perfil_modulo_perfil
            FOREIGN KEY (perfil_id) REFERENCES perfiles(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_perfil_modulo_modulo
            FOREIGN KEY (modulo_id) REFERENCES modulos(id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB');

    if (!columna_existe($pdo, 'perfil_modulo', 'fecha_asignacion')) {
        $pdo->exec('ALTER TABLE perfil_modulo
                    ADD COLUMN fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER aprobado');
    }
}

function registrar_modulo(PDO $pdo, string $nombreModulo, ?string $descripcion = null, string $estado = 'activo'): void
{
    $nombreModulo = trim($nombreModulo);
    if ($nombreModulo === '') {
        return;
    }

    if ($estado !== 'activo' && $estado !== 'inactivo') {
        $estado = 'activo';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO modulos (nombre_modulo, descripcion, estado)
         VALUES (:nombre_modulo, :descripcion, :estado)
         ON DUPLICATE KEY UPDATE
            descripcion = COALESCE(NULLIF(VALUES(descripcion), \'\'), modulos.descripcion),
            estado = VALUES(estado)'
    );
    $stmt->execute([
        ':nombre_modulo' => $nombreModulo,
        ':descripcion' => $descripcion,
        ':estado' => $estado,
    ]);
}

function migrar_modulos_desde_permisos(PDO $pdo): void
{
    $pdo->exec("INSERT INTO modulos (nombre_modulo, descripcion, estado)
                SELECT p.codigo, p.descripcion, 'activo'
                FROM permisos p
                LEFT JOIN modulos m ON BINARY m.nombre_modulo = BINARY p.codigo
                WHERE m.id IS NULL");
}

function migrar_perfil_modulo_desde_perfil_permiso(PDO $pdo): void
{
    $pdo->exec('INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
                SELECT pp.perfil_id, m.id, 1
                FROM perfil_permiso pp
                INNER JOIN permisos p ON p.id = pp.permiso_id
                INNER JOIN modulos m ON BINARY m.nombre_modulo = BINARY p.codigo
                LEFT JOIN perfil_modulo pm
                    ON pm.perfil_id = pp.perfil_id
                   AND pm.modulo_id = m.id
                WHERE pm.id IS NULL');
}

function asegurar_admin_modulos_universales(PDO $pdo): void
{
    $stmtAdmin = $pdo->prepare('SELECT id FROM perfiles WHERE LOWER(TRIM(nombre)) = :nombre LIMIT 1');
    $stmtAdmin->execute([':nombre' => 'admin']);
    $adminId = (int) ($stmtAdmin->fetchColumn() ?: 0);
    if ($adminId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
         SELECT :perfil_id, m.id, 1
         FROM modulos m
         WHERE m.estado = \'activo\'
         ON DUPLICATE KEY UPDATE aprobado = VALUES(aprobado)'
    );
    $stmt->execute([':perfil_id' => $adminId]);
}

function asegurar_permiso_perfil(PDO $pdo, string $perfilNombre, array $codigosPermiso): void
{
    if (empty($codigosPermiso)) {
        return;
    }

    $stmtPerfil = $pdo->prepare('SELECT id FROM perfiles WHERE nombre = :nombre LIMIT 1');
    $stmtPerfil->execute([':nombre' => $perfilNombre]);
    $perfilId = (int) ($stmtPerfil->fetchColumn() ?: 0);
    if ($perfilId <= 0) {
        return;
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO perfil_permiso (perfil_id, permiso_id)
         SELECT :perfil_id, p.id
         FROM permisos p
         WHERE p.codigo = :codigo
         ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id)'
    );

    foreach ($codigosPermiso as $codigo) {
        $stmtInsert->execute([
            ':perfil_id' => $perfilId,
            ':codigo' => $codigo,
        ]);
    }
}

function asegurar_permisos_por_perfil(PDO $pdo): void
{
    $mapa = [
        'admin' => [
            'ver_dashboard',
            'ver_bitacora',
            'gestionar_usuarios',
            'ver_reportes',
            'gestionar_contenido',
            'gestionar_servicios',
            'gestionar_portafolio',
            'gestionar_proyectos',
            'gestionar_nosotros',
            'gestionar_contacto',
            'gestionar_backup',
            'gestionar_perfiles',
        ],
        'gerente' => [
            'ver_dashboard',
            'ver_bitacora',
            'ver_reportes',
            'gestionar_contenido',
            'gestionar_servicios',
            'gestionar_portafolio',
            'gestionar_proyectos',
            'gestionar_nosotros',
            'gestionar_contacto',
        ],
        'developer' => [
            'ver_dashboard',
            'ver_bitacora',
            'gestionar_usuarios',
            'ver_reportes',
            'gestionar_contenido',
            'gestionar_servicios',
            'gestionar_portafolio',
            'gestionar_proyectos',
            'gestionar_nosotros',
            'gestionar_contacto',
            'gestionar_backup',
            'gestionar_perfiles',
        ],
        'tecnico' => [
            'ver_dashboard',
            'ver_bitacora',
        ],
        'cliente' => [
            'ver_dashboard',
            'ver_bitacora',
        ],
        'contadro' => [
            'ver_dashboard',
            'ver_bitacora',
            'acceso_dte',
            'dte_ver',
            'dte_emitir',
            'dte_reportes',
        ],
    ];

    foreach ($mapa as $perfilNombre => $codigosPermiso) {
        asegurar_permiso_perfil($pdo, $perfilNombre, $codigosPermiso);
    }

    // No asignar permisos universales automaticamente a perfiles no admin.
    // Los nuevos modulos deben quedar visibles por defecto solo para ADMIN.
}

function migrar_permisos_usuario_legacy(PDO $pdo): void
{
    $migraciones = [
        'gestionar_contenido' => [
            'gestionar_servicios',
            'gestionar_portafolio',
            'gestionar_proyectos',
            'gestionar_nosotros',
            'gestionar_contacto',
        ],
        'gestionar_usuarios' => [
            'gestionar_backup',
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO usuario_permiso (usuario_id, permiso_id, permitido)
         SELECT up.usuario_id, p_nuevo.id, up.permitido
         FROM usuario_permiso up
         INNER JOIN permisos p_origen ON p_origen.id = up.permiso_id
         INNER JOIN permisos p_nuevo ON p_nuevo.codigo = :codigo_nuevo
         LEFT JOIN usuario_permiso up_existente
            ON up_existente.usuario_id = up.usuario_id
           AND up_existente.permiso_id = p_nuevo.id
         WHERE p_origen.codigo = :codigo_origen
           AND up_existente.usuario_id IS NULL'
    );

    foreach ($migraciones as $codigoOrigen => $codigosNuevos) {
        foreach ($codigosNuevos as $codigoNuevo) {
            $stmt->execute([
                ':codigo_origen' => $codigoOrigen,
                ':codigo_nuevo' => $codigoNuevo,
            ]);
        }
    }
}

function asegurar_migracion_aprobacion_modulos(PDO $pdo): void
{
    $tablaPrincipalOk = tabla_existe($pdo, 'system_modules')
        && columna_existe($pdo, 'system_modules', 'slug')
        && columna_existe($pdo, 'system_modules', 'approval_status');
    $tablaAuditoriaOk = tabla_existe($pdo, 'system_module_approval_audit')
        && columna_existe($pdo, 'system_module_approval_audit', 'module_id')
        && columna_existe($pdo, 'system_module_approval_audit', 'to_status');

    if ($tablaPrincipalOk && $tablaAuditoriaOk) {
        return;
    }

    $sqlPath = __DIR__ . '/../sql/2026_02_27_modules_approval.sql';
    ejecutar_sql_instalacion($pdo, $sqlPath);
}

function asegurar_migracion_rbac_modulos(PDO $pdo): void
{
    $tablaPerfilModuloOk = tabla_existe($pdo, 'profile_module_access')
        && columna_existe($pdo, 'profile_module_access', 'profile_id')
        && columna_existe($pdo, 'profile_module_access', 'module_id')
        && columna_existe($pdo, 'profile_module_access', 'allowed');
    $tablaOverrideUsuarioOk = tabla_existe($pdo, 'user_module_override')
        && columna_existe($pdo, 'user_module_override', 'user_id')
        && columna_existe($pdo, 'user_module_override', 'module_id')
        && columna_existe($pdo, 'user_module_override', 'effect');

    if ($tablaPerfilModuloOk && $tablaOverrideUsuarioOk) {
        return;
    }

    $sqlPath = __DIR__ . '/../sql/2026_02_27_rbac_modulos.sql';
    ejecutar_sql_instalacion($pdo, $sqlPath);
}

function asegurar_compatibilidad_auth(PDO $pdo): void
{
    $tablaUsuariosExiste = tabla_existe($pdo, 'usuarios');

    if ($tablaUsuariosExiste) {
        if (!columna_existe($pdo, 'usuarios', 'username')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN username VARCHAR(60) NULL AFTER nombre');
        }

        if (!columna_existe($pdo, 'usuarios', 'perfil_id')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN perfil_id INT UNSIGNED NULL');
        }

        if (!columna_existe($pdo, 'usuarios', 'activo')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1');
        }

        if (!columna_existe($pdo, 'usuarios', 'telefono')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(30) NULL AFTER email');
        }

        if (!columna_existe($pdo, 'usuarios', 'direccion')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN direccion VARCHAR(200) NULL AFTER telefono');
        }

        if (!columna_existe($pdo, 'usuarios', 'foto_path')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN foto_path VARCHAR(255) NULL AFTER direccion');
        }

        if (!columna_existe($pdo, 'usuarios', 'must_change_password')) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
        }
    }

    if (!$tablaUsuariosExiste) {
        instalar_esquema_base($pdo);
    }

    if (!columna_existe($pdo, 'usuarios', 'username')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN username VARCHAR(60) NULL AFTER nombre');
    }

    if (!columna_existe($pdo, 'usuarios', 'perfil_id')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN perfil_id INT UNSIGNED NULL');
    }

    if (!columna_existe($pdo, 'usuarios', 'activo')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1');
    }

    if (!columna_existe($pdo, 'usuarios', 'telefono')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(30) NULL AFTER email');
    }

    if (!columna_existe($pdo, 'usuarios', 'direccion')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN direccion VARCHAR(200) NULL AFTER telefono');
    }

    if (!columna_existe($pdo, 'usuarios', 'foto_path')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN foto_path VARCHAR(255) NULL AFTER direccion');
    }

    if (!columna_existe($pdo, 'usuarios', 'must_change_password')) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }

    asegurar_tabla_servicios($pdo);
    asegurar_tabla_portafolio($pdo);
    asegurar_tabla_portafolio_imagenes($pdo);
    asegurar_tabla_proyectos($pdo);
    asegurar_tabla_nosotros($pdo);
    asegurar_tabla_contacto_mensajes($pdo);
    asegurar_tabla_visitas_sitio($pdo);
    asegurar_tabla_mantenimiento_eventos($pdo);
    asegurar_tabla_configuracion_sistema($pdo);
    if (columna_existe($pdo, 'portafolio', 'subtitulo')) {
        $pdo->exec('ALTER TABLE portafolio DROP COLUMN subtitulo');
    }

    $pdo->exec('INSERT INTO portafolio_imagenes (portafolio_id, imagen_path, orden)
                SELECT p.id, p.imagen_path, 1
                FROM portafolio p
                WHERE TRIM(p.imagen_path) <> \'\'
                  AND NOT EXISTS (
                    SELECT 1
                    FROM portafolio_imagenes pi
                    WHERE pi.portafolio_id = p.id
                  )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS usuario_permiso (
        usuario_id INT UNSIGNED NOT NULL,
        permiso_id INT UNSIGNED NOT NULL,
        permitido TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (usuario_id, permiso_id),
        CONSTRAINT fk_usuario_permiso_usuario
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_usuario_permiso_permiso
            FOREIGN KEY (permiso_id) REFERENCES permisos(id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB');

    asegurar_perfiles_base($pdo);
    asegurar_permisos_base($pdo);
    asegurar_permisos_por_perfil($pdo);
    migrar_permisos_usuario_legacy($pdo);
    asegurar_tabla_modulos($pdo);
    asegurar_tabla_perfil_modulo($pdo);
    migrar_modulos_desde_permisos($pdo);
    migrar_perfil_modulo_desde_perfil_permiso($pdo);
    asegurar_admin_modulos_universales($pdo);
    asegurar_migracion_aprobacion_modulos($pdo);
    asegurar_migracion_rbac_modulos($pdo);

    $stmt = $pdo->query("SELECT id FROM perfiles WHERE nombre = 'admin' LIMIT 1");
    $adminId = (int) ($stmt->fetchColumn() ?: 0);

    if ($adminId <= 0) {
        $pdo->exec("INSERT INTO perfiles (nombre, descripcion, activo) VALUES ('admin', 'Control total del sistema', 1)");
        $adminId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET perfil_id = :perfil_id WHERE perfil_id IS NULL OR perfil_id = 0');
    $stmt->execute([':perfil_id' => $adminId]);

    $pdo->exec("UPDATE usuarios
                SET username = CONCAT('user_', id)
                WHERE username IS NULL OR TRIM(username) = ''");
    $pdo->exec('ALTER TABLE usuarios MODIFY COLUMN username VARCHAR(60) NOT NULL');
    if (!indice_existe($pdo, 'usuarios', 'uq_usuarios_username')) {
        $pdo->exec('ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_username (username)');
    }

    $pdo->exec('ALTER TABLE usuarios MODIFY COLUMN perfil_id INT UNSIGNED NOT NULL');
}
