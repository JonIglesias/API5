-- Renombrar columna 'active' a 'is_active' en tabla api_plans
-- Migración: 007_rename_active_to_is_active.sql

ALTER TABLE `api_plans`
CHANGE COLUMN `active` `is_active` TINYINT(1) DEFAULT 1;
