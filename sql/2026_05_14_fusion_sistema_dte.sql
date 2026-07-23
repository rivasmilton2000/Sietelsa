-- Fusion controlada del sistema DTE dentro de la base unificada.
-- Fecha: 2026-05-14
-- Origen analizado: dte/dte_backup_2026-05-14_04-23-01.sql
--
-- Estrategia:
-- 1) Crear tablas DTE con prefijo dte_ para evitar colisiones.
-- 2) Mantener usuarios principales en `usuarios` y mapearlos a `dte_usuarios` mediante `dte_user_bridge`.
-- 3) Crear rol/permisos DTE dentro del RBAC actual.
-- 4) Cargar datos base del backup y habilitar importacion historica opcional desde `dte_import_tmp`.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1) TABLAS DTE (PREFIJO dte_)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dte_usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    nombre_completo VARCHAR(150) NULL,
    foto_perfil VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_usuarios_username (username),
    KEY idx_dte_usuarios_estado (estado),
    KEY idx_dte_usuarios_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_user_bridge (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    main_user_id INT UNSIGNED NOT NULL,
    dte_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_user_bridge_main_user (main_user_id),
    UNIQUE KEY uq_dte_user_bridge_dte_user (dte_user_id),
    KEY idx_dte_user_bridge_main_user (main_user_id),
    KEY idx_dte_user_bridge_dte_user (dte_user_id),
    CONSTRAINT fk_dte_user_bridge_main_user
        FOREIGN KEY (main_user_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dte_user_bridge_dte_user
        FOREIGN KEY (dte_user_id) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_empresas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    iniciales VARCHAR(4) NULL,
    color_emblema VARCHAR(20) DEFAULT '#f97316',
    dui VARCHAR(20) NULL,
    nit VARCHAR(20) NULL,
    nrc VARCHAR(20) NULL,
    tipo_legal ENUM('natural', 'juridica') DEFAULT 'natural',
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_vez_usada TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_dte_empresas_usuario (id_usuario),
    KEY idx_dte_empresas_estado (estado),
    CONSTRAINT fk_dte_empresas_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_libros (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_empresa INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    tipo ENUM('compras', 'ventas_consumidor', 'ventas_contribuyente', 'retencion_iva') NOT NULL,
    mes TINYINT NOT NULL,
    anio YEAR NOT NULL,
    nombre VARCHAR(100) GENERATED ALWAYS AS (concat(tipo, '-', anio, '-', mes)) STORED,
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_libro (id_empresa, tipo, mes, anio),
    KEY idx_dte_libros_usuario (id_usuario),
    KEY idx_dte_libros_estado (estado),
    CONSTRAINT fk_dte_libros_empresa
        FOREIGN KEY (id_empresa) REFERENCES dte_empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dte_libros_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_dte_libros_mes CHECK (mes BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_facturas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_libro INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    codigo_generacion VARCHAR(50) NOT NULL,
    sello_recepcion TEXT NULL,
    numero_control VARCHAR(50) NULL,
    tipo_dte VARCHAR(10) NULL,
    fecha DATE NOT NULL,
    nrc VARCHAR(20) NULL,
    nit VARCHAR(20) NULL,
    nombre_proveedor VARCHAR(200) NULL,
    ventas_internas DECIMAL(10,2) DEFAULT 0.00,
    ventas_importacion DECIMAL(10,2) DEFAULT 0.00,
    ventas_internas_exentas DECIMAL(10,2) DEFAULT 0.00,
    ventas_importacion_exentas DECIMAL(10,2) DEFAULT 0.00,
    credito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    total_compras DECIMAL(10,2) DEFAULT 0.00,
    iva_percibido DECIMAL(10,2) DEFAULT 0.00,
    iva_retenido DECIMAL(10,2) DEFAULT 0.00,
    numero_control_completo VARCHAR(100) NULL,
    raw_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nombre_cliente VARCHAR(200) NULL,
    nrc_cliente VARCHAR(20) NULL,
    numero_control_preimpreso VARCHAR(50) NULL,
    numero_control_interno VARCHAR(50) NULL,
    dia_emision DATE NULL,
    del_numero INT NULL,
    al_numero INT NULL,
    codigo_generacion_desde VARCHAR(50) NULL,
    codigo_generacion_hasta VARCHAR(50) NULL,
    ventas_exentas DECIMAL(10,2) DEFAULT 0.00,
    ventas_internas_gravadas DECIMAL(10,2) DEFAULT 0.00,
    exportaciones DECIMAL(10,2) DEFAULT 0.00,
    total_ventas_diarias_propias DECIMAL(10,2) DEFAULT 0.00,
    ventas_cuenta_terceros DECIMAL(10,2) DEFAULT 0.00,
    debito_fiscal DECIMAL(10,2) DEFAULT 0.00,
    ventas_exentas_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ventas_internas_gravadas_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    debito_fiscal_contribuyente DECIMAL(10,2) DEFAULT 0.00,
    ventas_totales DECIMAL(10,2) DEFAULT 0.00,
    nit_agente_retencion VARCHAR(20) NULL,
    fecha_emision_retencion DATE NULL,
    tipo_documento_relacionado VARCHAR(20) NULL,
    serie_documento VARCHAR(50) NULL,
    numero_documento VARCHAR(50) NULL,
    monto_sujeto_retencion DECIMAL(10,2) DEFAULT 0.00,
    retencion_iva_1 DECIMAL(10,2) DEFAULT 0.00,
    dui_agente_retencion VARCHAR(20) NULL,
    numero_anexo VARCHAR(20) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_factura_libro (id_libro, codigo_generacion),
    KEY idx_dte_facturas_usuario (id_usuario),
    KEY idx_dte_facturas_fecha (fecha),
    CONSTRAINT fk_dte_facturas_libro
        FOREIGN KEY (id_libro) REFERENCES dte_libros(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dte_facturas_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_facturas_disponibles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    total INT NOT NULL DEFAULT 50,
    consumidas INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_facturas_disponibles_usuario (id_usuario),
    CONSTRAINT fk_dte_facturas_disponibles_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_bitacora_movimientos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NOT NULL,
    username_snapshot VARCHAR(100) NOT NULL,
    rol_snapshot VARCHAR(20) NULL,
    modulo VARCHAR(80) NOT NULL,
    accion VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    entidad_tipo VARCHAR(80) NULL,
    entidad_id INT NULL,
    contexto_json LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dte_bitacora_usuario (id_usuario),
    KEY idx_dte_bitacora_fecha (created_at),
    KEY idx_dte_bitacora_modulo (modulo),
    CONSTRAINT fk_dte_bitacora_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_centro_mando_config (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_empresa INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    item_key VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_centro_mando_config_item (id_empresa, item_key),
    KEY idx_dte_centro_mando_config_usuario (id_usuario),
    CONSTRAINT fk_dte_centro_mando_config_empresa
        FOREIGN KEY (id_empresa) REFERENCES dte_empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dte_centro_mando_config_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_centro_mando_avance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_empresa INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    mes TINYINT NOT NULL,
    anio YEAR NOT NULL,
    item_key VARCHAR(80) NOT NULL,
    completado TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_centro_mando_avance_item (id_empresa, mes, anio, item_key),
    KEY idx_dte_centro_mando_avance_usuario (id_usuario),
    CONSTRAINT fk_dte_centro_mando_avance_empresa
        FOREIGN KEY (id_empresa) REFERENCES dte_empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dte_centro_mando_avance_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_centro_mando_notas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_empresa INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    mes TINYINT NOT NULL,
    anio YEAR NOT NULL,
    nota TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dte_centro_mando_notas_item (id_empresa, mes, anio),
    KEY idx_dte_centro_mando_notas_usuario (id_usuario),
    CONSTRAINT fk_dte_centro_mando_notas_empresa
        FOREIGN KEY (id_empresa) REFERENCES dte_empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dte_centro_mando_notas_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dte_centro_mando_preferencias (
    id_usuario INT UNSIGNED NOT NULL,
    ocultar_bienvenida TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    CONSTRAINT fk_dte_centro_mando_preferencias_usuario
        FOREIGN KEY (id_usuario) REFERENCES dte_usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2) ROL/PERMISOS/MODULOS DTE EN RBAC UNIFICADO
-- -----------------------------------------------------------------------------

INSERT INTO perfiles (nombre, descripcion, activo)
VALUES ('dte', 'Acceso operativo a modulos DTE unificados', 1)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), activo = VALUES(activo);

INSERT INTO permisos (codigo, descripcion) VALUES
    ('acceso_dte', 'Acceso general al panel DTE unificado'),
    ('dte_ver', 'Consultar informacion DTE'),
    ('dte_crear', 'Crear registros DTE'),
    ('dte_editar', 'Editar registros DTE'),
    ('dte_eliminar', 'Eliminar registros DTE'),
    ('dte_emitir', 'Emitir o importar documentos DTE'),
    ('dte_anular', 'Anular documentos DTE'),
    ('dte_reportes', 'Consultar reportes DTE'),
    ('dte_configurar', 'Configurar parametros DTE')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pr.id
FROM perfiles p
INNER JOIN permisos pr ON pr.codigo IN (
    'acceso_dte',
    'dte_ver',
    'dte_crear',
    'dte_editar',
    'dte_eliminar',
    'dte_emitir',
    'dte_anular',
    'dte_reportes',
    'dte_configurar'
)
WHERE p.nombre IN ('admin', 'administrador', 'developer')
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pr.id
FROM perfiles p
INNER JOIN permisos pr ON pr.codigo IN ('acceso_dte', 'dte_ver', 'dte_emitir', 'dte_reportes')
WHERE p.nombre = 'dte'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO modulos (nombre_modulo, descripcion, estado)
SELECT pr.codigo, pr.descripcion, 'activo'
FROM permisos pr
WHERE pr.codigo IN (
    'acceso_dte',
    'dte_ver',
    'dte_crear',
    'dte_editar',
    'dte_eliminar',
    'dte_emitir',
    'dte_anular',
    'dte_reportes',
    'dte_configurar'
)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), estado = VALUES(estado);

INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
SELECT pp.perfil_id, m.id, 1
FROM perfil_permiso pp
INNER JOIN permisos pr ON pr.id = pp.permiso_id
INNER JOIN modulos m ON BINARY m.nombre_modulo = BINARY pr.codigo
WHERE pr.codigo IN (
    'acceso_dte',
    'dte_ver',
    'dte_crear',
    'dte_editar',
    'dte_eliminar',
    'dte_emitir',
    'dte_anular',
    'dte_reportes',
    'dte_configurar'
)
ON DUPLICATE KEY UPDATE aprobado = VALUES(aprobado);

-- Si existe el esquema de modulos de publicacion, deja DTE aprobado por defecto.
INSERT INTO system_modules (
    slug,
    display_name,
    route_path,
    category,
    permission_code,
    icon_class,
    sidebar_order,
    is_admin_only,
    approval_status
)
SELECT 'dte_dashboard', 'Panel DTE', '/admin/dte/', 'facturacion_dte', 'acceso_dte', 'fa-file-invoice-dollar', 15, 0, 'APROBADO'
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_modules'
)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    route_path = VALUES(route_path),
    category = VALUES(category),
    permission_code = VALUES(permission_code),
    icon_class = VALUES(icon_class),
    sidebar_order = VALUES(sidebar_order),
    is_admin_only = VALUES(is_admin_only),
    approval_status = 'APROBADO';

INSERT INTO system_modules (
    slug,
    display_name,
    route_path,
    category,
    permission_code,
    icon_class,
    sidebar_order,
    is_admin_only,
    approval_status
)
SELECT 'dte_compras', 'DTE Compras', '/admin/dte/src/pages/compras.php', 'facturacion_dte', 'dte_emitir', 'fa-cart-shopping', 16, 0, 'APROBADO'
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_modules'
)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    route_path = VALUES(route_path),
    category = VALUES(category),
    permission_code = VALUES(permission_code),
    icon_class = VALUES(icon_class),
    sidebar_order = VALUES(sidebar_order),
    is_admin_only = VALUES(is_admin_only),
    approval_status = 'APROBADO';

INSERT INTO system_modules (
    slug,
    display_name,
    route_path,
    category,
    permission_code,
    icon_class,
    sidebar_order,
    is_admin_only,
    approval_status
)
SELECT 'dte_ventas_consumidor', 'DTE Ventas CF', '/admin/dte/src/pages/ventas_consumidor.php', 'facturacion_dte', 'dte_emitir', 'fa-receipt', 17, 0, 'APROBADO'
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_modules'
)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    route_path = VALUES(route_path),
    category = VALUES(category),
    permission_code = VALUES(permission_code),
    icon_class = VALUES(icon_class),
    sidebar_order = VALUES(sidebar_order),
    is_admin_only = VALUES(is_admin_only),
    approval_status = 'APROBADO';

INSERT INTO system_modules (
    slug,
    display_name,
    route_path,
    category,
    permission_code,
    icon_class,
    sidebar_order,
    is_admin_only,
    approval_status
)
SELECT 'dte_ventas_contribuyente', 'DTE Ventas CCF', '/admin/dte/src/pages/ventas_contribuyente.php', 'facturacion_dte', 'dte_emitir', 'fa-file-signature', 18, 0, 'APROBADO'
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_modules'
)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    route_path = VALUES(route_path),
    category = VALUES(category),
    permission_code = VALUES(permission_code),
    icon_class = VALUES(icon_class),
    sidebar_order = VALUES(sidebar_order),
    is_admin_only = VALUES(is_admin_only),
    approval_status = 'APROBADO';

INSERT INTO system_modules (
    slug,
    display_name,
    route_path,
    category,
    permission_code,
    icon_class,
    sidebar_order,
    is_admin_only,
    approval_status
)
SELECT 'dte_retencion_iva', 'DTE Retencion IVA', '/admin/dte/src/pages/retencion_iva.php', 'facturacion_dte', 'dte_reportes', 'fa-percent', 19, 0, 'APROBADO'
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_modules'
)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    route_path = VALUES(route_path),
    category = VALUES(category),
    permission_code = VALUES(permission_code),
    icon_class = VALUES(icon_class),
    sidebar_order = VALUES(sidebar_order),
    is_admin_only = VALUES(is_admin_only),
    approval_status = 'APROBADO';

-- -----------------------------------------------------------------------------
-- 3) DATOS BASE EXTRAIDOS DEL BACKUP ANALIZADO
-- -----------------------------------------------------------------------------

INSERT INTO dte_usuarios (id, username, nombre_completo, foto_perfil, password, rol, estado, created_at) VALUES
    (1, 'admin', NULL, NULL, '$2y$10$zMxxLgUnZrye/5zT5Ot5ze9avnYJAi1kCyDUjmZoZ.DiX/kbNabl6', 'admin', 1, '2026-04-20 16:39:46'),
    (2, 'user', NULL, NULL, '$2y$10$Blv9fgvr/a5Zu4IUT0LX2OqJhKdXWT7pw0jmGO.esHjTPGGALrRYy', 'user', 1, '2026-04-20 16:39:46')
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    nombre_completo = VALUES(nombre_completo),
    foto_perfil = VALUES(foto_perfil),
    password = VALUES(password),
    rol = VALUES(rol),
    estado = VALUES(estado),
    created_at = VALUES(created_at);

INSERT INTO dte_empresas (id, id_usuario, nombre, iniciales, color_emblema, dui, nit, nrc, tipo_legal, estado, created_at, ultima_vez_usada) VALUES
    (2, 1, 'Prueba', 'PB', '#000000', '5we45w4', 'we656', NULL, 'natural', 1, '2026-04-20 23:30:09', '2026-05-13 20:00:03')
ON DUPLICATE KEY UPDATE
    id_usuario = VALUES(id_usuario),
    nombre = VALUES(nombre),
    iniciales = VALUES(iniciales),
    color_emblema = VALUES(color_emblema),
    dui = VALUES(dui),
    nit = VALUES(nit),
    nrc = VALUES(nrc),
    tipo_legal = VALUES(tipo_legal),
    estado = VALUES(estado),
    created_at = VALUES(created_at),
    ultima_vez_usada = VALUES(ultima_vez_usada);

INSERT INTO dte_libros (id, id_empresa, id_usuario, tipo, mes, anio, estado, created_at) VALUES
    (2, 2, 1, 'compras', 1, 2026, 1, '2026-04-20 23:30:27'),
    (3, 2, 1, 'ventas_consumidor', 8, 2026, 1, '2026-04-21 18:57:38'),
    (4, 2, 1, 'ventas_consumidor', 4, 2026, 1, '2026-04-21 18:58:05'),
    (5, 2, 1, 'ventas_contribuyente', 4, 2027, 1, '2026-04-21 18:58:59'),
    (6, 2, 1, 'compras', 4, 2026, 1, '2026-04-21 21:51:22'),
    (7, 2, 1, 'retencion_iva', 4, 2026, 1, '2026-04-22 12:17:13'),
    (8, 2, 1, 'compras', 5, 2026, 1, '2026-05-13 18:55:05')
ON DUPLICATE KEY UPDATE
    id_empresa = VALUES(id_empresa),
    id_usuario = VALUES(id_usuario),
    tipo = VALUES(tipo),
    mes = VALUES(mes),
    anio = VALUES(anio),
    estado = VALUES(estado),
    created_at = VALUES(created_at);

INSERT INTO dte_facturas_disponibles (id, id_usuario, total, consumidas, updated_at) VALUES
    (1, 1, 50, 50, '2026-05-13 18:31:15'),
    (2, 2, 50, 0, '2026-04-20 23:24:19')
ON DUPLICATE KEY UPDATE
    id_usuario = VALUES(id_usuario),
    total = VALUES(total),
    consumidas = VALUES(consumidas),
    updated_at = VALUES(updated_at);

INSERT INTO dte_centro_mando_preferencias (id_usuario, ocultar_bienvenida, updated_at) VALUES
    (1, 1, '2026-04-22 12:17:42')
ON DUPLICATE KEY UPDATE
    ocultar_bienvenida = VALUES(ocultar_bienvenida),
    updated_at = VALUES(updated_at);

INSERT INTO dte_bitacora_movimientos (
    id,
    id_usuario,
    username_snapshot,
    rol_snapshot,
    modulo,
    accion,
    descripcion,
    entidad_tipo,
    entidad_id,
    contexto_json,
    ip_address,
    user_agent,
    created_at
) VALUES
    (1, 1, 'admin', 'admin', 'auth', 'logout', 'Cierre de sesion.', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 13:49:38'),
    (2, 1, 'admin', 'admin', 'auth', 'login', 'Inicio de sesion correcto.', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 13:49:42')
ON DUPLICATE KEY UPDATE
    id_usuario = VALUES(id_usuario),
    username_snapshot = VALUES(username_snapshot),
    rol_snapshot = VALUES(rol_snapshot),
    modulo = VALUES(modulo),
    accion = VALUES(accion),
    descripcion = VALUES(descripcion),
    entidad_tipo = VALUES(entidad_tipo),
    entidad_id = VALUES(entidad_id),
    contexto_json = VALUES(contexto_json),
    ip_address = VALUES(ip_address),
    user_agent = VALUES(user_agent),
    created_at = VALUES(created_at);

-- -----------------------------------------------------------------------------
-- 4) BRIDGE ENTRE USUARIOS PRINCIPALES Y USUARIOS DTE
-- -----------------------------------------------------------------------------

INSERT INTO dte_user_bridge (main_user_id, dte_user_id)
SELECT u.id AS main_user_id, du.id AS dte_user_id
FROM usuarios u
INNER JOIN dte_usuarios du ON LOWER(du.username) = LOWER(u.username)
ON DUPLICATE KEY UPDATE dte_user_id = VALUES(dte_user_id), updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- 5) IMPORTACION HISTORICA OPCIONAL DESDE STAGING dte_import_tmp
-- -----------------------------------------------------------------------------
-- Para cargar TODO el historico del backup sin importar a ciegas en produccion:
-- 1) Restaurar `dte/dte_backup_2026-05-14_04-23-01.sql` en un schema temporal llamado `dte_import_tmp`.
-- 2) Ejecutar de nuevo este script (idempotente).

SET @dte_tmp_exists := (
    SELECT COUNT(*)
    FROM information_schema.SCHEMATA
    WHERE SCHEMA_NAME = 'dte_import_tmp'
);

SET @sql_import_dte_usuarios := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_usuarios (id, username, nombre_completo, foto_perfil, password, rol, estado, created_at)
     SELECT id, username, nombre_completo, foto_perfil, password, rol, estado, created_at
     FROM dte_import_tmp.usuarios
     ON DUPLICATE KEY UPDATE
        username = VALUES(username),
        nombre_completo = VALUES(nombre_completo),
        foto_perfil = VALUES(foto_perfil),
        password = VALUES(password),
        rol = VALUES(rol),
        estado = VALUES(estado),
        created_at = VALUES(created_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_usuarios FROM @sql_import_dte_usuarios;
EXECUTE stmt_import_dte_usuarios;
DEALLOCATE PREPARE stmt_import_dte_usuarios;

SET @sql_import_dte_empresas := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_empresas (id, id_usuario, nombre, iniciales, color_emblema, dui, nit, nrc, tipo_legal, estado, created_at, ultima_vez_usada)
     SELECT id, id_usuario, nombre, iniciales, color_emblema, dui, nit, nrc, tipo_legal, estado, created_at, ultima_vez_usada
     FROM dte_import_tmp.empresas
     ON DUPLICATE KEY UPDATE
        id_usuario = VALUES(id_usuario),
        nombre = VALUES(nombre),
        iniciales = VALUES(iniciales),
        color_emblema = VALUES(color_emblema),
        dui = VALUES(dui),
        nit = VALUES(nit),
        nrc = VALUES(nrc),
        tipo_legal = VALUES(tipo_legal),
        estado = VALUES(estado),
        created_at = VALUES(created_at),
        ultima_vez_usada = VALUES(ultima_vez_usada)',
    'SELECT 1'
);
PREPARE stmt_import_dte_empresas FROM @sql_import_dte_empresas;
EXECUTE stmt_import_dte_empresas;
DEALLOCATE PREPARE stmt_import_dte_empresas;

SET @sql_import_dte_libros := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_libros (id, id_empresa, id_usuario, tipo, mes, anio, estado, created_at)
     SELECT id, id_empresa, id_usuario, tipo, mes, anio, estado, created_at
     FROM dte_import_tmp.libros
     ON DUPLICATE KEY UPDATE
        id_empresa = VALUES(id_empresa),
        id_usuario = VALUES(id_usuario),
        tipo = VALUES(tipo),
        mes = VALUES(mes),
        anio = VALUES(anio),
        estado = VALUES(estado),
        created_at = VALUES(created_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_libros FROM @sql_import_dte_libros;
EXECUTE stmt_import_dte_libros;
DEALLOCATE PREPARE stmt_import_dte_libros;

SET @sql_import_dte_facturas := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_facturas (
        id,
        id_libro,
        id_usuario,
        codigo_generacion,
        sello_recepcion,
        numero_control,
        tipo_dte,
        fecha,
        nrc,
        nit,
        nombre_proveedor,
        ventas_internas,
        ventas_importacion,
        ventas_internas_exentas,
        ventas_importacion_exentas,
        credito_fiscal,
        total_compras,
        iva_percibido,
        iva_retenido,
        numero_control_completo,
        raw_json,
        created_at,
        nombre_cliente,
        nrc_cliente,
        numero_control_preimpreso,
        numero_control_interno,
        dia_emision,
        del_numero,
        al_numero,
        codigo_generacion_desde,
        codigo_generacion_hasta,
        ventas_exentas,
        ventas_internas_gravadas,
        exportaciones,
        total_ventas_diarias_propias,
        ventas_cuenta_terceros,
        debito_fiscal,
        ventas_exentas_contribuyente,
        ventas_internas_gravadas_contribuyente,
        debito_fiscal_contribuyente,
        ventas_totales,
        nit_agente_retencion,
        fecha_emision_retencion,
        tipo_documento_relacionado,
        serie_documento,
        numero_documento,
        monto_sujeto_retencion,
        retencion_iva_1,
        dui_agente_retencion,
        numero_anexo
     )
     SELECT
        id,
        id_libro,
        id_usuario,
        codigo_generacion,
        sello_recepcion,
        numero_control,
        tipo_dte,
        fecha,
        nrc,
        nit,
        nombre_proveedor,
        ventas_internas,
        ventas_importacion,
        ventas_internas_exentas,
        ventas_importacion_exentas,
        credito_fiscal,
        total_compras,
        iva_percibido,
        iva_retenido,
        numero_control_completo,
        raw_json,
        created_at,
        nombre_cliente,
        nrc_cliente,
        numero_control_preimpreso,
        numero_control_interno,
        dia_emision,
        del_numero,
        al_numero,
        codigo_generacion_desde,
        codigo_generacion_hasta,
        ventas_exentas,
        ventas_internas_gravadas,
        exportaciones,
        total_ventas_diarias_propias,
        ventas_cuenta_terceros,
        debito_fiscal,
        ventas_exentas_contribuyente,
        ventas_internas_gravadas_contribuyente,
        debito_fiscal_contribuyente,
        ventas_totales,
        nit_agente_retencion,
        fecha_emision_retencion,
        tipo_documento_relacionado,
        serie_documento,
        numero_documento,
        monto_sujeto_retencion,
        retencion_iva_1,
        dui_agente_retencion,
        numero_anexo
     FROM dte_import_tmp.facturas
     ON DUPLICATE KEY UPDATE
        codigo_generacion = VALUES(codigo_generacion),
        sello_recepcion = VALUES(sello_recepcion),
        numero_control = VALUES(numero_control),
        tipo_dte = VALUES(tipo_dte),
        fecha = VALUES(fecha),
        nrc = VALUES(nrc),
        nit = VALUES(nit),
        nombre_proveedor = VALUES(nombre_proveedor),
        ventas_internas = VALUES(ventas_internas),
        ventas_importacion = VALUES(ventas_importacion),
        ventas_internas_exentas = VALUES(ventas_internas_exentas),
        ventas_importacion_exentas = VALUES(ventas_importacion_exentas),
        credito_fiscal = VALUES(credito_fiscal),
        total_compras = VALUES(total_compras),
        iva_percibido = VALUES(iva_percibido),
        iva_retenido = VALUES(iva_retenido),
        numero_control_completo = VALUES(numero_control_completo),
        raw_json = VALUES(raw_json),
        created_at = VALUES(created_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_facturas FROM @sql_import_dte_facturas;
EXECUTE stmt_import_dte_facturas;
DEALLOCATE PREPARE stmt_import_dte_facturas;

SET @sql_import_dte_facturas_disponibles := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_facturas_disponibles (id, id_usuario, total, consumidas, updated_at)
     SELECT id, id_usuario, total, consumidas, updated_at
     FROM dte_import_tmp.facturas_disponibles
     ON DUPLICATE KEY UPDATE
        id_usuario = VALUES(id_usuario),
        total = VALUES(total),
        consumidas = VALUES(consumidas),
        updated_at = VALUES(updated_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_facturas_disponibles FROM @sql_import_dte_facturas_disponibles;
EXECUTE stmt_import_dte_facturas_disponibles;
DEALLOCATE PREPARE stmt_import_dte_facturas_disponibles;

SET @sql_import_dte_bitacora := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_bitacora_movimientos (
        id,
        id_usuario,
        username_snapshot,
        rol_snapshot,
        modulo,
        accion,
        descripcion,
        entidad_tipo,
        entidad_id,
        contexto_json,
        ip_address,
        user_agent,
        created_at
     )
     SELECT
        id,
        id_usuario,
        username_snapshot,
        rol_snapshot,
        modulo,
        accion,
        descripcion,
        entidad_tipo,
        entidad_id,
        contexto_json,
        ip_address,
        user_agent,
        created_at
     FROM dte_import_tmp.bitacora_movimientos
     ON DUPLICATE KEY UPDATE
        id_usuario = VALUES(id_usuario),
        username_snapshot = VALUES(username_snapshot),
        rol_snapshot = VALUES(rol_snapshot),
        modulo = VALUES(modulo),
        accion = VALUES(accion),
        descripcion = VALUES(descripcion),
        entidad_tipo = VALUES(entidad_tipo),
        entidad_id = VALUES(entidad_id),
        contexto_json = VALUES(contexto_json),
        ip_address = VALUES(ip_address),
        user_agent = VALUES(user_agent),
        created_at = VALUES(created_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_bitacora FROM @sql_import_dte_bitacora;
EXECUTE stmt_import_dte_bitacora;
DEALLOCATE PREPARE stmt_import_dte_bitacora;

SET @sql_import_dte_centro_mando_pref := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_centro_mando_preferencias (id_usuario, ocultar_bienvenida, updated_at)
     SELECT id_usuario, ocultar_bienvenida, updated_at
     FROM dte_import_tmp.centro_mando_preferencias
     ON DUPLICATE KEY UPDATE
        ocultar_bienvenida = VALUES(ocultar_bienvenida),
        updated_at = VALUES(updated_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_centro_mando_pref FROM @sql_import_dte_centro_mando_pref;
EXECUTE stmt_import_dte_centro_mando_pref;
DEALLOCATE PREPARE stmt_import_dte_centro_mando_pref;

SET @sql_import_dte_centro_mando_cfg := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_centro_mando_config (id, id_empresa, id_usuario, item_key, created_at)
     SELECT id, id_empresa, id_usuario, item_key, created_at
     FROM dte_import_tmp.centro_mando_config
     ON DUPLICATE KEY UPDATE
        id_empresa = VALUES(id_empresa),
        id_usuario = VALUES(id_usuario),
        item_key = VALUES(item_key),
        created_at = VALUES(created_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_centro_mando_cfg FROM @sql_import_dte_centro_mando_cfg;
EXECUTE stmt_import_dte_centro_mando_cfg;
DEALLOCATE PREPARE stmt_import_dte_centro_mando_cfg;

SET @sql_import_dte_centro_mando_avance := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_centro_mando_avance (id, id_empresa, id_usuario, mes, anio, item_key, completado, completed_at, updated_at)
     SELECT id, id_empresa, id_usuario, mes, anio, item_key, completado, completed_at, updated_at
     FROM dte_import_tmp.centro_mando_avance
     ON DUPLICATE KEY UPDATE
        id_empresa = VALUES(id_empresa),
        id_usuario = VALUES(id_usuario),
        mes = VALUES(mes),
        anio = VALUES(anio),
        item_key = VALUES(item_key),
        completado = VALUES(completado),
        completed_at = VALUES(completed_at),
        updated_at = VALUES(updated_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_centro_mando_avance FROM @sql_import_dte_centro_mando_avance;
EXECUTE stmt_import_dte_centro_mando_avance;
DEALLOCATE PREPARE stmt_import_dte_centro_mando_avance;

SET @sql_import_dte_centro_mando_notas := IF(
    @dte_tmp_exists > 0,
    'INSERT INTO dte_centro_mando_notas (id, id_empresa, id_usuario, mes, anio, nota, updated_at)
     SELECT id, id_empresa, id_usuario, mes, anio, nota, updated_at
     FROM dte_import_tmp.centro_mando_notas
     ON DUPLICATE KEY UPDATE
        id_empresa = VALUES(id_empresa),
        id_usuario = VALUES(id_usuario),
        mes = VALUES(mes),
        anio = VALUES(anio),
        nota = VALUES(nota),
        updated_at = VALUES(updated_at)',
    'SELECT 1'
);
PREPARE stmt_import_dte_centro_mando_notas FROM @sql_import_dte_centro_mando_notas;
EXECUTE stmt_import_dte_centro_mando_notas;
DEALLOCATE PREPARE stmt_import_dte_centro_mando_notas;

-- Reconciliar bridge luego de importacion opcional.
INSERT INTO dte_user_bridge (main_user_id, dte_user_id)
SELECT u.id AS main_user_id, du.id AS dte_user_id
FROM usuarios u
INNER JOIN dte_usuarios du ON LOWER(du.username) = LOWER(u.username)
ON DUPLICATE KEY UPDATE dte_user_id = VALUES(dte_user_id), updated_at = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
