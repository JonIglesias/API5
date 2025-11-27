-- Migración 009: Separar tokens de entrada y salida para cálculo preciso de costos
-- OpenAI cobra diferente por input vs output tokens

ALTER TABLE `api_usage_tracking` 
ADD COLUMN `tokens_input` INT UNSIGNED NULL COMMENT 'Tokens de entrada (prompt)' AFTER `tokens_total`,
ADD COLUMN `tokens_output` INT UNSIGNED NULL COMMENT 'Tokens de salida (completion)' AFTER `tokens_input`,
ADD COLUMN `cost_input` DECIMAL(10,6) NULL COMMENT 'Costo de tokens input en USD' AFTER `tokens_output`,
ADD COLUMN `cost_output` DECIMAL(10,6) NULL COMMENT 'Costo de tokens output en USD' AFTER `cost_input`,
ADD COLUMN `cost_total` DECIMAL(10,6) NULL COMMENT 'Costo total en USD' AFTER `cost_output`,
ADD COLUMN `model_used` VARCHAR(50) NULL COMMENT 'Modelo usado (gpt-4, gpt-3.5-turbo, etc)' AFTER `cost_total`;

-- Nota: Los registros existentes tendrán estos campos en NULL
-- Nuevos registros deben incluir estos valores

-- Índice para consultas de costos
ALTER TABLE `api_usage_tracking`
ADD INDEX `idx_costs` (`license_id`, `created_at`, `cost_total`);
