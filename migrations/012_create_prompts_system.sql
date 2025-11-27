-- =====================================================
-- API V4.2 - Sistema de Gestión de Prompts
-- Fecha: 2025-11-09
-- =====================================================

CREATE TABLE IF NOT EXISTS `api_prompts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL COMMENT 'Identificador único: title_generate, content_generate, etc',
  `name` VARCHAR(200) NOT NULL COMMENT 'Nombre legible: "Generación de Títulos"',
  `description` TEXT NULL COMMENT 'Descripción de qué hace este prompt',
  `category` VARCHAR(50) NOT NULL DEFAULT 'generation' COMMENT 'Categoría: generation, meta, keywords, etc',
  
  -- Contenido del prompt
  `template` TEXT NOT NULL COMMENT 'Template del prompt con variables {{variable}}',
  `plugin_context` TEXT NULL COMMENT 'Documentación de qué añade el plugin (solo lectura)',
  
  -- Metadata
  `variables` JSON NULL COMMENT 'Array de variables disponibles con su metadata',
  `estimated_tokens_input` INT(11) DEFAULT 0 COMMENT 'Tokens estimados de entrada',
  `estimated_tokens_output` INT(11) DEFAULT 0 COMMENT 'Tokens estimados de salida',
  
  -- Control de versiones
  `version` INT(11) DEFAULT 1 COMMENT 'Versión actual del prompt',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = desactivado',
  
  -- Auditoría
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(100) NULL COMMENT 'Usuario que hizo el último cambio',
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category` (`category`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla de historial de versiones (opcional pero útil)
-- =====================================================

CREATE TABLE IF NOT EXISTS `api_prompts_history` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prompt_id` INT(11) UNSIGNED NOT NULL,
  `version` INT(11) NOT NULL,
  `template` TEXT NOT NULL,
  `variables` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) NULL,
  `change_note` TEXT NULL COMMENT 'Nota sobre qué cambió',
  
  PRIMARY KEY (`id`),
  KEY `prompt_id` (`prompt_id`),
  KEY `version` (`version`),
  CONSTRAINT `fk_prompt_history` FOREIGN KEY (`prompt_id`) REFERENCES `api_prompts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Índices adicionales para optimización
-- =====================================================

ALTER TABLE `api_prompts` ADD INDEX `search_idx` (`name`, `slug`, `category`);
