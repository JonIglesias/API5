<?php
/**
 * API Claude V4 - Configuración
 * 
 * @version 4.0
 */

// Prevenir acceso directo
defined('API_ACCESS') or die('Direct access not permitted');

// ============================================================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'bocetosm_api_claude4');
define('DB_USER', 'bocetosm_APAPI');
define('DB_PASSWORD', 'Mafalda2000_AP_API');
define('DB_PREFIX', 'api_');
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// CONFIGURACIÓN DE WOOCOMMERCE API
// ============================================================================

define('WC_API_URL', 'https://bocetosmarketing.com/wp-json/wc/v3/');
define('WC_CONSUMER_KEY', 'ck_a87f6a0696f2082261869141414f92f275b1bbe8');
define('WC_CONSUMER_SECRET', 'cs_4f37ae3fc0acfe9ec749dae9c621eabd1c84afcb');

// ============================================================================
// CONFIGURACIÓN DE SINCRONIZACIÓN
// ============================================================================

// Intervalo para licencias críticas (segundos)
define('SYNC_CRITICAL_INTERVAL', 1800); // 30 minutos

// Intervalo para licencias normales (segundos)
define('SYNC_REGULAR_INTERVAL', 21600); // 6 horas

// Intervalo para licencias inactivas (segundos)
define('SYNC_INACTIVE_INTERVAL', 86400); // 24 horas

// Definir cuando una licencia es crítica (días antes de expiración)
define('CRITICAL_DAYS_BEFORE_EXPIRY', 7);

// Edad máxima para considerar una licencia como "nueva" (horas)
define('NEW_LICENSE_AGE_HOURS', 48);

// ============================================================================
// CONFIGURACIÓN DE MONEDA
// ============================================================================

// OpenAI cobra en USD, convertir a EUR para mostrar
define('USD_TO_EUR_RATE', 0.92); // Actualizar manualmente según tipo de cambio
define('DISPLAY_CURRENCY', 'EUR');
define('DISPLAY_CURRENCY_SYMBOL', '€');

// ============================================================================
// ESTADOS DEL CACHÉ
// ============================================================================

// Tiempo en segundos para cada estado
define('CACHE_FRESH_SECONDS', 21600);    // 6 horas
define('CACHE_VALID_SECONDS', 86400);    // 24 horas
define('CACHE_STALE_SECONDS', 259200);   // 72 horas

// ============================================================================
// CONFIGURACIÓN DE WEBHOOKS
// ============================================================================

// IPs permitidas para webhooks (whitelist)
// NOTA: Deshabilitado porque el cron auto-sync actúa como backup
// Si quieres restringir, descomenta y añade las IPs de tu servidor WooCommerce
// define('WEBHOOK_ALLOWED_IPS', [
//     '127.0.0.1',
//     '::1',
// ]);

// Secret para validar webhooks (configurado en WooCommerce)
define('WEBHOOK_SECRET', 'MMSFDNFJLFSDMMNWIOEURI');

// ============================================================================
// CONFIGURACIÓN DE LOGS
// ============================================================================

define('LOG_LEVEL', 'info'); // debug, info, warning, error
define('LOG_MAX_FILES', 30); // Días de logs a mantener
define('ENABLE_API_LOG', true);
define('ENABLE_SYNC_LOG', true);
define('ENABLE_WEBHOOK_LOG', true);

// ============================================================================
// CONFIGURACIÓN DE ADMIN
// ============================================================================

define('ADMIN_SESSION_TIMEOUT', 7200); // 2 horas

// ============================================================================
// CONFIGURACIÓN DE OPENAI
// ============================================================================

define('OPENAI_API_KEY', ''); // Se configura en admin o directamente aquí
define('OPENAI_MODEL', 'gpt-4o-mini');
define('OPENAI_MAX_TOKENS', 4000);
define('OPENAI_TEMPERATURE', 0.7);

// ============================================================================
// CONFIGURACIÓN GENERAL
// ============================================================================

define('TIMEZONE', 'Europe/Madrid');
define('DATE_FORMAT', 'Y-m-d H:i:s');

// API Version
define('API_VERSION', '5.5');

// Directorio base
define('API_BASE_DIR', __DIR__);

// URL base (ajustar según tu instalación)
define('API_BASE_URL', 'https://bocetosmarketing.com/api_claude_5/');

// ============================================================================
// TIMEZONE
// ============================================================================
date_default_timezone_set(TIMEZONE);
