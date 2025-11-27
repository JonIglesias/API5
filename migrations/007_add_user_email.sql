-- Añadir columna user_email a la tabla api_licenses
ALTER TABLE `api_licenses` 
ADD COLUMN `user_email` VARCHAR(255) NULL AFTER `woo_user_id`,
ADD KEY `user_email` (`user_email`);
