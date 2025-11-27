-- Tabla de logs de webhooks
CREATE TABLE IF NOT EXISTS `api_webhook_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL,
  `woo_subscription_id` INT UNSIGNED NULL,
  `payload` TEXT NOT NULL COMMENT 'JSON completo del webhook',
  `processed` TINYINT(1) DEFAULT 0,
  `error_message` TEXT NULL,
  `received_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_type` (`event_type`),
  KEY `woo_subscription_id` (`woo_subscription_id`),
  KEY `received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
