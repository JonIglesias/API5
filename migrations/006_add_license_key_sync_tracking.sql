-- Añadir campo para trackear si la license_key fue enviada a WooCommerce
-- Migración: 006_add_license_key_sync_tracking.sql

ALTER TABLE `api_licenses`
ADD COLUMN `license_key_synced_to_woo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Si la license_key ya fue enviada a WooCommerce (0=no, 1=si)' AFTER `sync_status`,
ADD COLUMN `license_key_sync_attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de intentos de enviar la license_key a WooCommerce' AFTER `license_key_synced_to_woo`,
ADD COLUMN `license_key_last_sync_attempt` DATETIME NULL COMMENT 'Última vez que se intentó enviar la license_key' AFTER `license_key_sync_attempts`;

-- Índice para encontrar rápido las licencias que necesitan sincronización
ALTER TABLE `api_licenses`
ADD KEY `license_key_synced` (`license_key_synced_to_woo`, `license_key_sync_attempts`);
