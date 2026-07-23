CREATE TABLE IF NOT EXISTS profile_module_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_profile_module_access (profile_id, module_id),
    KEY idx_profile_module_access_profile (profile_id),
    KEY idx_profile_module_access_module (module_id),
    KEY idx_profile_module_access_allowed (allowed),
    CONSTRAINT fk_profile_module_access_profile
        FOREIGN KEY (profile_id) REFERENCES perfiles(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_profile_module_access_module
        FOREIGN KEY (module_id) REFERENCES system_modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_module_override (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    effect ENUM('inherit', 'allow', 'deny') NOT NULL DEFAULT 'inherit',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_module_override (user_id, module_id),
    KEY idx_user_module_override_user (user_id),
    KEY idx_user_module_override_module (module_id),
    KEY idx_user_module_override_effect (effect),
    CONSTRAINT fk_user_module_override_user
        FOREIGN KEY (user_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_module_override_module
        FOREIGN KEY (module_id) REFERENCES system_modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
