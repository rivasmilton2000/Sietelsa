-- Gestión de contenido del website SIETELSA.
-- Migración idempotente: no elimina tablas ni registros existentes.

USE `db_sietelsa`;

CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `draft_seo` JSON NOT NULL,
  `published_seo` JSON NOT NULL,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`),
  KEY `idx_pages_status_visible` (`status`, `is_visible`),
  CONSTRAINT `fk_pages_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media_library` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `relative_path` VARCHAR(500) NOT NULL,
  `thumbnail_path` VARCHAR(500) DEFAULT NULL,
  `medium_path` VARCHAR(500) DEFAULT NULL,
  `large_path` VARCHAR(500) DEFAULT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `extension` VARCHAR(10) NOT NULL,
  `width` INT UNSIGNED NOT NULL,
  `height` INT UNSIGNED NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_relative_path` (`relative_path`),
  KEY `idx_media_search` (`original_name`, `alt_text`),
  KEY `idx_media_deleted` (`deleted_at`),
  CONSTRAINT `fk_media_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` INT UNSIGNED NOT NULL,
  `section_key` VARCHAR(100) NOT NULL,
  `component_type` VARCHAR(60) NOT NULL,
  `draft_data` JSON NOT NULL,
  `published_data` JSON NOT NULL,
  `draft_media_id` INT UNSIGNED DEFAULT NULL,
  `published_media_id` INT UNSIGNED DEFAULT NULL,
  `draft_sort_order` INT NOT NULL DEFAULT 0,
  `published_sort_order` INT NOT NULL DEFAULT 0,
  `draft_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `published_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_sections_key` (`page_id`, `section_key`),
  KEY `idx_page_sections_public` (`page_id`, `published_visible`, `published_sort_order`),
  CONSTRAINT `fk_sections_page`
    FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sections_draft_media`
    FOREIGN KEY (`draft_media_id`) REFERENCES `media_library` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sections_published_media`
    FOREIGN KEY (`published_media_id`) REFERENCES `media_library` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sections_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` INT UNSIGNED NOT NULL,
  `item_key` VARCHAR(120) NOT NULL,
  `item_type` VARCHAR(60) NOT NULL,
  `draft_data` JSON NOT NULL,
  `published_data` JSON NOT NULL,
  `draft_media_id` INT UNSIGNED DEFAULT NULL,
  `published_media_id` INT UNSIGNED DEFAULT NULL,
  `draft_sort_order` INT NOT NULL DEFAULT 0,
  `published_sort_order` INT NOT NULL DEFAULT 0,
  `draft_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `published_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_content_items_key` (`section_id`, `item_key`),
  KEY `idx_content_items_public` (`section_id`, `published_visible`, `published_sort_order`),
  CONSTRAINT `fk_items_section`
    FOREIGN KEY (`section_id`) REFERENCES `page_sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_draft_media`
    FOREIGN KEY (`draft_media_id`) REFERENCES `media_library` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_items_published_media`
    FOREIGN KEY (`published_media_id`) REFERENCES `media_library` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_items_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `navigation_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nav_key` VARCHAR(100) NOT NULL,
  `draft_label` VARCHAR(100) NOT NULL,
  `published_label` VARCHAR(100) NOT NULL,
  `draft_url` VARCHAR(500) NOT NULL,
  `published_url` VARCHAR(500) NOT NULL,
  `draft_target` ENUM('_self', '_blank') NOT NULL DEFAULT '_self',
  `published_target` ENUM('_self', '_blank') NOT NULL DEFAULT '_self',
  `draft_access` ENUM('public', 'guest', 'authenticated', 'admin') NOT NULL DEFAULT 'public',
  `published_access` ENUM('public', 'guest', 'authenticated', 'admin') NOT NULL DEFAULT 'public',
  `draft_sort_order` INT NOT NULL DEFAULT 0,
  `published_sort_order` INT NOT NULL DEFAULT 0,
  `draft_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `published_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_navigation_key` (`nav_key`),
  KEY `idx_navigation_public` (`published_visible`, `published_sort_order`),
  CONSTRAINT `fk_navigation_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_group` VARCHAR(80) NOT NULL DEFAULT 'general',
  `value_type` ENUM('text', 'textarea', 'url', 'email', 'phone', 'json', 'boolean') NOT NULL DEFAULT 'text',
  `draft_value` JSON NOT NULL,
  `published_value` JSON NOT NULL,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_settings_key` (`setting_key`),
  KEY `idx_site_settings_group` (`setting_group`, `is_public`),
  CONSTRAINT `fk_settings_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('page', 'section', 'item', 'navigation', 'setting', 'media') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `entity_key` VARCHAR(160) NOT NULL,
  `action` ENUM('create', 'save_draft', 'publish', 'restore', 'activate', 'deactivate', 'delete') NOT NULL,
  `old_data` JSON DEFAULT NULL,
  `new_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_versions_entity` (`entity_type`, `entity_id`, `created_at`),
  CONSTRAINT `fk_versions_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_type` ENUM('contact', 'newsletter') NOT NULL DEFAULT 'contact',
  `name` VARCHAR(150) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(200) NOT NULL DEFAULT '',
  `message` TEXT NOT NULL,
  `status` ENUM('new', 'read', 'archived') NOT NULL DEFAULT 'new',
  `ip_hash` CHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_status_created` (`status`, `created_at`),
  KEY `idx_messages_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL,
  `entity_id` VARCHAR(80) DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `ip_hash` CHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_created` (`user_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_user`
    FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
