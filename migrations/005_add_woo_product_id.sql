-- Migración: Añadir campo woo_product_id a licenses
-- Fecha: 2025-11-06

ALTER TABLE api_licenses 
ADD COLUMN IF NOT EXISTS woo_product_id INT NULL 
AFTER woo_subscription_id,
ADD INDEX idx_woo_product_id (woo_product_id);

-- Comentario sobre los campos
-- woo_subscription_id: ID de la suscripción en WooCommerce (ej: 72)
-- woo_product_id: ID del producto asociado a la suscripción (ej: 69)
-- plan_id: ID del plan en nuestra API (ej: 'demo')
