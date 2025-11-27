-- Tabla de logs de sincronización
CREATE TABLE IF NOT EXISTS `api_sync_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id` INT UNSIGNED NOT NULL,
  `sync_type` ENUM('webhook', 'cron_critical', 'cron_regular', 'manual') NOT NULL,
  `status` ENUM('success', 'failed', 'no_changes') NOT NULL,
  `changes_detected` TEXT NULL COMMENT 'JSON con cambios detectados',
  `woo_response` TEXT NULL COMMENT 'Respuesta de WooCommerce API',
  `error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `license_id` (`license_id`),
  KEY `sync_type` (`sync_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`license_id`) REFERENCES `api_licenses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
