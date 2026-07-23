CREATE TABLE IF NOT EXISTS system_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    route_path VARCHAR(190) NOT NULL,
    category VARCHAR(80) NULL,
    permission_code VARCHAR(80) NULL,
    icon_class VARCHAR(80) NULL,
    sidebar_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_admin_only TINYINT(1) NOT NULL DEFAULT 0,
    approval_status ENUM('BORRADOR', 'PENDIENTE', 'APROBADO', 'RECHAZADO') NOT NULL DEFAULT 'BORRADOR',
    rejection_reason VARCHAR(500) NULL,
    reviewed_by_user_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_system_modules_slug (slug),
    KEY idx_system_modules_route (route_path),
    KEY idx_system_modules_status (approval_status),
    KEY idx_system_modules_permission (permission_code),
    KEY idx_system_modules_order (sidebar_order),
    CONSTRAINT fk_system_modules_reviewed_user
        FOREIGN KEY (reviewed_by_user_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_module_approval_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    from_status ENUM('BORRADOR', 'PENDIENTE', 'APROBADO', 'RECHAZADO') NOT NULL,
    to_status ENUM('BORRADOR', 'PENDIENTE', 'APROBADO', 'RECHAZADO') NOT NULL,
    reason VARCHAR(500) NULL,
    changed_by_user_id INT UNSIGNED NULL,
    changed_by_name VARCHAR(120) NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_system_module_audit_module_changed (module_id, changed_at),
    KEY idx_system_module_audit_status (to_status),
    CONSTRAINT fk_system_module_audit_module
        FOREIGN KEY (module_id) REFERENCES system_modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_system_module_audit_user
        FOREIGN KEY (changed_by_user_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
