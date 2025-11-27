-- Tabla de planes
CREATE TABLE IF NOT EXISTS `api_plans` (
  `id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `tokens_per_month` INT UNSIGNED NOT NULL,
  `woo_product_id` INT UNSIGNED NULL,
  `billing_cycle` ENUM('monthly', 'yearly') DEFAULT 'monthly',
  `active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `woo_product_id` (`woo_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar planes básicos
INSERT INTO `api_plans` (`id`, `name`, `tokens_per_month`, `woo_product_id`, `billing_cycle`, `active`, `created_at`, `updated_at`) VALUES
('basic', 'Plan Basic', 50000, NULL, 'monthly', 1, NOW(), NOW()),
('pro', 'Plan Pro', 200000, NULL, 'monthly', 1, NOW(), NOW()),
('enterprise', 'Plan Enterprise', 1000000, NULL, 'monthly', 1, NOW(), NOW());
