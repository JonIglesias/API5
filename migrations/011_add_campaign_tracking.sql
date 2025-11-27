-- Añadir campos campaign_id y campaign_name para tracking de campañas
-- Esto permite identificar qué campañas generaron qué operaciones

ALTER TABLE `api_usage_tracking` 
ADD COLUMN `campaign_id` VARCHAR(64) NULL COMMENT 'ID de campaña del plugin (ej: campaign_123)' AFTER `batch_type`,
ADD COLUMN `campaign_name` VARCHAR(255) NULL COMMENT 'Nombre de la campaña' AFTER `campaign_id`,
ADD INDEX `campaign_id` (`campaign_id`);
