-- ========================================
-- ACTUALIZAR TABLA PLANES CON CAMPOS V3
-- Compatible con MySQL 5.7+
-- ========================================

-- Agregar columnas (ignora si ya existen)
ALTER TABLE `api_plans`
ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `name`;

ALTER TABLE `api_plans`
ADD COLUMN `currency` VARCHAR(3) DEFAULT 'EUR' AFTER `price`;

-- LIMITS
ALTER TABLE `api_plans`
ADD COLUMN `requests_per_day` INT DEFAULT -1 COMMENT '-1 = ilimitado' AFTER `tokens_per_month`;

ALTER TABLE `api_plans`
ADD COLUMN `requests_per_month` INT DEFAULT -1 COMMENT '-1 = ilimitado' AFTER `requests_per_day`;

ALTER TABLE `api_plans`
ADD COLUMN `max_words_per_request` INT UNSIGNED DEFAULT 2000 AFTER `requests_per_month`;

ALTER TABLE `api_plans`
ADD COLUMN `max_campaigns` INT DEFAULT -1 COMMENT '-1 = ilimitado' AFTER `max_words_per_request`;

-- TIMING (CRÍTICO)
ALTER TABLE `api_plans`
ADD COLUMN `post_generation_delay` INT UNSIGNED DEFAULT 60 COMMENT 'Segundos de delay entre posts al ejecutar' AFTER `max_campaigns`;

ALTER TABLE `api_plans`
ADD COLUMN `api_timeout` INT UNSIGNED DEFAULT 120 COMMENT 'Timeout en segundos para requests OpenAI' AFTER `post_generation_delay`;

ALTER TABLE `api_plans`
ADD COLUMN `max_retries` TINYINT UNSIGNED DEFAULT 3 COMMENT 'Reintentos en caso de fallo' AFTER `api_timeout`;

-- FEATURES (JSON)
ALTER TABLE `api_plans`
ADD COLUMN `features` JSON DEFAULT NULL COMMENT 'Features habilitadas del plan' AFTER `max_retries`;

-- Actualizar datos de planes existentes con valores V3
UPDATE `api_plans` SET 
    price = CASE 
        WHEN id = 'free' THEN 0.00
        WHEN id = 'basic' THEN 9.99
        WHEN id = 'pro' THEN 29.99
        WHEN id = 'enterprise' THEN 99.99
        ELSE 0.00
    END,
    requests_per_day = CASE 
        WHEN id = 'free' THEN 10
        WHEN id = 'basic' THEN 50
        WHEN id = 'pro' THEN 200
        WHEN id = 'enterprise' THEN -1
        ELSE -1
    END,
    requests_per_month = CASE 
        WHEN id = 'free' THEN 300
        WHEN id = 'basic' THEN 1500
        WHEN id = 'pro' THEN 6000
        WHEN id = 'enterprise' THEN -1
        ELSE -1
    END,
    max_words_per_request = CASE 
        WHEN id = 'free' THEN 500
        WHEN id = 'basic' THEN 1000
        WHEN id = 'pro' THEN 2000
        WHEN id = 'enterprise' THEN 4000
        ELSE 2000
    END,
    max_campaigns = CASE 
        WHEN id = 'free' THEN 1
        WHEN id = 'basic' THEN 5
        WHEN id = 'pro' THEN 20
        WHEN id = 'enterprise' THEN -1
        ELSE -1
    END,
    post_generation_delay = CASE 
        WHEN id = 'free' THEN 120
        WHEN id = 'basic' THEN 90
        WHEN id = 'pro' THEN 60
        WHEN id = 'enterprise' THEN 10
        ELSE 60
    END,
    api_timeout = CASE 
        WHEN id = 'free' THEN 60
        WHEN id = 'basic' THEN 90
        WHEN id = 'pro' THEN 120
        WHEN id = 'enterprise' THEN 180
        ELSE 120
    END,
    max_retries = CASE 
        WHEN id = 'free' THEN 2
        WHEN id = 'basic' THEN 3
        WHEN id = 'pro' THEN 3
        WHEN id = 'enterprise' THEN 5
        ELSE 3
    END,
    features = CASE 
        WHEN id = 'free' THEN JSON_OBJECT(
            'basic_generation', true,
            'advanced_generation', false,
            'image_generation', false,
            'priority_support', false
        )
        WHEN id = 'basic' THEN JSON_OBJECT(
            'basic_generation', true,
            'advanced_generation', true,
            'image_generation', false,
            'priority_support', false
        )
        WHEN id = 'pro' THEN JSON_OBJECT(
            'basic_generation', true,
            'advanced_generation', true,
            'image_generation', true,
            'priority_support', true
        )
        WHEN id = 'enterprise' THEN JSON_OBJECT(
            'basic_generation', true,
            'advanced_generation', true,
            'image_generation', true,
            'priority_support', true,
            'custom_models', true
        )
        ELSE JSON_OBJECT('basic_generation', true)
    END
WHERE id IN ('free', 'basic', 'pro', 'enterprise');

-- Insertar plan 'free' si no existe
INSERT IGNORE INTO `api_plans` (
    `id`, `name`, `price`, `currency`, `billing_cycle`, 
    `tokens_per_month`, 
    `requests_per_day`, `requests_per_month`, `max_words_per_request`, `max_campaigns`,
    `post_generation_delay`, `api_timeout`, `max_retries`,
    `features`, `active`, `created_at`, `updated_at`
) VALUES (
    'free', 'Plan Gratuito', 0.00, 'EUR', 'monthly',
    30000,
    10, 300, 500, 1,
    120, 60, 2,
    JSON_OBJECT(
        'basic_generation', true,
        'advanced_generation', false,
        'image_generation', false,
        'priority_support', false
    ),
    1, NOW(), NOW()
);

-- Verificar resultado
SELECT 
    id, name, price, tokens_per_month,
    post_generation_delay AS delay, 
    api_timeout AS timeout,
    max_retries AS retries,
    requests_per_day AS req_day
FROM api_plans 
WHERE active = 1
ORDER BY id;
