-- Tabla para almacenar títulos generados en colas activas
-- Permite evitar duplicados dentro de la misma campaña/cola
-- Auto-limpieza: títulos más antiguos de 24h se eliminan automáticamente

CREATE TABLE IF NOT EXISTS `api_queue_titles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` VARCHAR(100) NOT NULL COMMENT 'ID de la campaña/cola',
  `license_id` INT UNSIGNED NOT NULL COMMENT 'ID de la licencia propietaria',
  `title_text` VARCHAR(500) NOT NULL COMMENT 'Texto del título generado',
  `created_at` DATETIME NOT NULL COMMENT 'Fecha de generación',
  PRIMARY KEY (`id`),
  KEY `campaign_lookup` (`campaign_id`, `created_at`),
  KEY `cleanup` (`created_at`),
  FOREIGN KEY (`license_id`) REFERENCES `api_licenses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Almacenamiento temporal de títulos generados en colas. TTL: 24 horas';

-- Event scheduler para auto-limpieza (elimina títulos >24h cada 6 horas)
DROP EVENT IF EXISTS `cleanup_old_queue_titles`;

CREATE EVENT `cleanup_old_queue_titles`
ON SCHEDULE EVERY 6 HOUR
STARTS CURRENT_TIMESTAMP
DO
  DELETE FROM `api_queue_titles`
  WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 24 HOUR);
