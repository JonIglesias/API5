-- ========================================
-- AGREGAR CAMPO max_posts_per_campaign
-- Limita el número máximo de posts que se pueden configurar en una campaña
-- ========================================

ALTER TABLE `api_plans`
ADD COLUMN IF NOT EXISTS `max_posts_per_campaign` INT DEFAULT -1 COMMENT '-1 = ilimitado, cualquier otro valor = límite máximo de posts por campaña' AFTER `max_campaigns`;

-- Actualizar planes existentes con valores según su tier
UPDATE `api_plans` SET
    max_posts_per_campaign = CASE
        WHEN id = 'free' THEN 10
        WHEN id = 'basic' THEN 50
        WHEN id = 'pro' THEN 100
        WHEN id = 'enterprise' THEN -1
        ELSE 50
    END
WHERE id IN ('free', 'basic', 'pro', 'enterprise');

-- Verificar resultado
SELECT
    id,
    name,
    max_campaigns,
    max_posts_per_campaign,
    post_generation_delay AS delay_seg
FROM api_plans
WHERE active = 1
ORDER BY id;
