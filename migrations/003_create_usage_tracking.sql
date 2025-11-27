-- Tabla de tracking de uso
CREATE TABLE IF NOT EXISTS `api_usage_tracking` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id` INT UNSIGNED NOT NULL,
  `operation_type` VARCHAR(50) NOT NULL COMMENT 'content, title, keywords, meta, etc',
  `tokens_total` INT UNSIGNED NOT NULL,
  `sync_status_at_time` VARCHAR(20) NULL COMMENT 'Estado del caché cuando se usó',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `license_id` (`license_id`),
  KEY `operation_type` (`operation_type`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`license_id`) REFERENCES `api_licenses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
