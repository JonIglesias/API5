<?php
/**
 * Script de diagnóstico para sincronización de license_keys
 *
 * Ejecutar: php diagnose-sync.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

echo "==========================================================\n";
echo "DIAGNÓSTICO DE SINCRONIZACIÓN DE LICENSE_KEYS\n";
echo "==========================================================\n\n";

// 1. Total de licencias
$total = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses")['count'];
echo "📊 TOTAL DE LICENCIAS: {$total}\n\n";

// 2. Licencias ya sincronizadas
$synced = $db->fetchOne("
    SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
    WHERE license_key_synced_to_woo = 1
")['count'];
echo "✅ YA SINCRONIZADAS: {$synced}\n\n";

// 3. Licencias pendientes de sincronización (condición completa del cron)
$pending = $db->fetchOne("
    SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
    WHERE license_key_synced_to_woo = 0
      AND license_key_sync_attempts < 5
      AND (
          license_key_last_sync_attempt IS NULL
          OR license_key_last_sync_attempt < DATE_SUB(NOW(), INTERVAL 300 SECOND)
      )
      AND (
          (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
          OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
      )
")['count'];
echo "⏳ PENDIENTES DE SINCRONIZAR (que el cron debería procesar): {$pending}\n\n";

// 4. Licencias bloqueadas por diferentes razones
echo "==========================================================\n";
echo "MOTIVOS POR LOS QUE NO SE SINCRONIZAN:\n";
echo "==========================================================\n\n";

// 4a. Sin order ID válido
$noOrderId = $db->fetchOne("
    SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
    WHERE license_key_synced_to_woo = 0
      AND (
          (woo_subscription_id IS NULL OR woo_subscription_id = '' OR woo_subscription_id = 0)
          AND (last_order_id IS NULL OR last_order_id = '' OR last_order_id = 0)
      )
")['count'];
echo "❌ Sin order_id válido (woo_subscription_id y last_order_id vacíos): {$noOrderId}\n";

// 4b. Máximo de intentos alcanzado
$maxAttempts = $db->fetchOne("
    SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
    WHERE license_key_synced_to_woo = 0
      AND license_key_sync_attempts >= 5
")['count'];
echo "❌ Máximo de intentos alcanzado (>=5): {$maxAttempts}\n";

// 4c. Esperando retry (último intento hace menos de 5 min)
$waitingRetry = $db->fetchOne("
    SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
    WHERE license_key_synced_to_woo = 0
      AND license_key_sync_attempts < 5
      AND license_key_last_sync_attempt IS NOT NULL
      AND license_key_last_sync_attempt >= DATE_SUB(NOW(), INTERVAL 300 SECOND)
      AND (
          (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
          OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
      )
")['count'];
echo "⏱️  Esperando retry (último intento hace <5 min): {$waitingRetry}\n\n";

// 5. Detalles de licencias pendientes
if ($pending > 0) {
    echo "==========================================================\n";
    echo "DETALLES DE LICENCIAS PENDIENTES (primeras 10):\n";
    echo "==========================================================\n\n";

    $pendingDetails = $db->query("
        SELECT
            id,
            license_key,
            user_email,
            woo_subscription_id,
            last_order_id,
            license_key_sync_attempts,
            license_key_last_sync_attempt,
            created_at
        FROM " . DB_PREFIX . "licenses
        WHERE license_key_synced_to_woo = 0
          AND license_key_sync_attempts < 5
          AND (
              license_key_last_sync_attempt IS NULL
              OR license_key_last_sync_attempt < DATE_SUB(NOW(), INTERVAL 300 SECOND)
          )
          AND (
              (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
              OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
          )
        ORDER BY created_at DESC
        LIMIT 10
    ");

    foreach ($pendingDetails as $license) {
        echo "ID: {$license['id']}\n";
        echo "  License Key: {$license['license_key']}\n";
        echo "  Email: {$license['user_email']}\n";
        echo "  WooCommerce Subscription ID: " . ($license['woo_subscription_id'] ?: 'NULL') . "\n";
        echo "  Last Order ID: " . ($license['last_order_id'] ?: 'NULL') . "\n";
        echo "  Intentos de sync: {$license['license_key_sync_attempts']}\n";
        echo "  Último intento: " . ($license['license_key_last_sync_attempt'] ?: 'Nunca') . "\n";
        echo "  Creada: {$license['created_at']}\n";
        echo "  ---\n";
    }
}

// 6. Detalles de licencias sin order ID
if ($noOrderId > 0) {
    echo "\n==========================================================\n";
    echo "LICENCIAS SIN ORDER_ID (primeras 5):\n";
    echo "==========================================================\n\n";

    $noOrderIdDetails = $db->query("
        SELECT
            id,
            license_key,
            user_email,
            woo_subscription_id,
            last_order_id,
            created_at
        FROM " . DB_PREFIX . "licenses
        WHERE license_key_synced_to_woo = 0
          AND (
              (woo_subscription_id IS NULL OR woo_subscription_id = '' OR woo_subscription_id = 0)
              AND (last_order_id IS NULL OR last_order_id = '' OR last_order_id = 0)
          )
        ORDER BY created_at DESC
        LIMIT 5
    ");

    foreach ($noOrderIdDetails as $license) {
        echo "ID: {$license['id']}\n";
        echo "  License Key: {$license['license_key']}\n";
        echo "  Email: {$license['user_email']}\n";
        echo "  WooCommerce Subscription ID: " . var_export($license['woo_subscription_id'], true) . "\n";
        echo "  Last Order ID: " . var_export($license['last_order_id'], true) . "\n";
        echo "  Creada: {$license['created_at']}\n";
        echo "  ⚠️  Esta licencia NO puede sincronizarse porque no tiene order_id\n";
        echo "  ---\n";
    }
}

// 7. Detalles de licencias con max attempts
if ($maxAttempts > 0) {
    echo "\n==========================================================\n";
    echo "LICENCIAS CON MÁXIMO DE INTENTOS (primeras 5):\n";
    echo "==========================================================\n\n";

    $maxAttemptsDetails = $db->query("
        SELECT
            id,
            license_key,
            user_email,
            woo_subscription_id,
            last_order_id,
            license_key_sync_attempts,
            license_key_last_sync_attempt
        FROM " . DB_PREFIX . "licenses
        WHERE license_key_synced_to_woo = 0
          AND license_key_sync_attempts >= 5
        ORDER BY license_key_last_sync_attempt DESC
        LIMIT 5
    ");

    foreach ($maxAttemptsDetails as $license) {
        echo "ID: {$license['id']}\n";
        echo "  License Key: {$license['license_key']}\n";
        echo "  Email: {$license['user_email']}\n";
        echo "  Order ID: " . ($license['woo_subscription_id'] ?: $license['last_order_id']) . "\n";
        echo "  Intentos: {$license['license_key_sync_attempts']}\n";
        echo "  Último intento: {$license['license_key_last_sync_attempt']}\n";
        echo "  ⚠️  Esta licencia alcanzó el máximo de intentos (5)\n";
        echo "  💡 Revisa los logs para ver qué error causó los fallos\n";
        echo "  ---\n";
    }
}

echo "\n==========================================================\n";
echo "RECOMENDACIONES:\n";
echo "==========================================================\n\n";

if ($pending > 0) {
    echo "✅ Hay {$pending} licencias listas para sincronizar.\n";
    echo "   El cron debería procesarlas en la próxima ejecución.\n";
    echo "   Revisa los logs en: logs/sync.log\n\n";
}

if ($noOrderId > 0) {
    echo "⚠️  Hay {$noOrderId} licencias sin order_id válido.\n";
    echo "   Estas licencias NO pueden sincronizarse a WooCommerce.\n";
    echo "   ACCIÓN: Verifica cómo se están creando estas licencias.\n";
    echo "   Deben tener woo_subscription_id o last_order_id válido.\n\n";
}

if ($maxAttempts > 0) {
    echo "⚠️  Hay {$maxAttempts} licencias que alcanzaron el máximo de intentos.\n";
    echo "   ACCIÓN: Revisa logs/sync.log para ver los errores.\n";
    echo "   Posibles causas:\n";
    echo "   - Order ID inválido en WooCommerce\n";
    echo "   - Problemas de conexión con WooCommerce API\n";
    echo "   - Credenciales API incorrectas\n\n";
    echo "   Para forzar reintento:\n";
    echo "   UPDATE api_licenses SET license_key_sync_attempts = 0, license_key_synced_to_woo = 0 WHERE id = <ID>;\n\n";
}

if ($pending == 0 && $noOrderId == 0 && $maxAttempts == 0 && $synced == $total) {
    echo "🎉 ¡Perfecto! Todas las licencias están sincronizadas.\n\n";
}

echo "==========================================================\n";
