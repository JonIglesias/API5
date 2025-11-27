-- Tabla de licencias
CREATE TABLE IF NOT EXISTS `api_licenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_key` VARCHAR(100) NOT NULL UNIQUE,
  `woo_subscription_id` INT UNSIGNED NULL,
  `woo_user_id` INT UNSIGNED NULL,
  `plan_id` VARCHAR(50) NOT NULL,
  `status` ENUM('active', 'suspended', 'expired', 'cancelled') DEFAULT 'active',
  `tokens_limit` INT UNSIGNED NOT NULL DEFAULT 0,
  `tokens_used_this_period` INT UNSIGNED NOT NULL DEFAULT 0,
  `period_starts_at` DATETIME NULL,
  `period_ends_at` DATETIME NULL,
  `authorized_domains` TEXT NULL COMMENT 'JSON array of authorized domains',
  `last_synced_at` DATETIME NULL,
  `sync_status` ENUM('fresh', 'valid', 'stale', 'expired') DEFAULT 'expired',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `license_key` (`license_key`),
  KEY `woo_subscription_id` (`woo_subscription_id`),
  KEY `status` (`status`),
  KEY `period_ends_at` (`period_ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
