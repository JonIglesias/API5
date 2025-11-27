-- Tabla para gestionar precios de modelos de IA
-- Permite mantener histórico y actualizar precios sin tocar código

CREATE TABLE IF NOT EXISTS `api_model_prices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `model_name` VARCHAR(50) NOT NULL COMMENT 'Nombre del modelo (gpt-4o-mini, etc)',
  `price_input_per_1k` DECIMAL(10,6) NOT NULL COMMENT 'Precio por 1K tokens input en USD',
  `price_output_per_1k` DECIMAL(10,6) NOT NULL COMMENT 'Precio por 1K tokens output en USD',
  `source` VARCHAR(50) NULL COMMENT 'Origen: manual, openai_api, external',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = precio actual, 0 = histórico',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL COMMENT 'Notas sobre el cambio de precio',
  PRIMARY KEY (`id`),
  KEY `model_name` (`model_name`),
  KEY `is_active` (`is_active`, `model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar precios actuales (Nov 2024)
INSERT INTO `api_model_prices` 
  (`model_name`, `price_input_per_1k`, `price_output_per_1k`, `source`, `notes`) 
VALUES
  ('gpt-4o-mini', 0.00015, 0.0006, 'openai_pricing_nov2024', 'Modelo más barato y recomendado'),
  ('gpt-4o', 0.005, 0.015, 'openai_pricing_nov2024', 'Modelo equilibrado'),
  ('gpt-4-turbo', 0.01, 0.03, 'openai_pricing_nov2024', 'Modelo rápido'),
  ('gpt-4', 0.03, 0.06, 'openai_pricing_nov2024', 'Modelo premium'),
  ('gpt-3.5-turbo', 0.0005, 0.0015, 'openai_pricing_nov2024', 'Modelo legacy'),
  ('claude-3-5-sonnet', 0.003, 0.015, 'anthropic_pricing_nov2024', 'Claude Sonnet 3.5'),
  ('claude-3-opus', 0.015, 0.075, 'anthropic_pricing_nov2024', 'Claude Opus'),
  ('claude-3-sonnet', 0.003, 0.015, 'anthropic_pricing_nov2024', 'Claude Sonnet'),
  ('claude-3-haiku', 0.00025, 0.00125, 'anthropic_pricing_nov2024', 'Claude Haiku');
