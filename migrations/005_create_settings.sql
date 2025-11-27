-- Migration: Create settings table
-- Version: 4.0

CREATE TABLE IF NOT EXISTS `api_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(50) DEFAULT 'string',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `api_settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('openai_api_key', '', 'string'),
('api_enabled', '1', 'boolean'),
('maintenance_mode', '0', 'boolean')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
