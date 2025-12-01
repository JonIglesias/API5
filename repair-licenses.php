<?php
/**
 * Script de reparación de licencias con order_ids incorrectos
 *
 * Repara licencias donde last_order_id y woo_subscription_id son diferentes
 * (indica que fueron actualizadas incorrectamente por el webhook antiguo)
 *
 * Ejecutar: php repair-licenses.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

echo "==========================================================\n";
echo "REPARACIÓN DE LICENCIAS CON ORDER_IDS INCORRECTOS\n";
echo "==========================================================\n\n";

// Buscar licencias donde last_order_id != woo_subscription_id
$brokenLicenses = $db->query("
    SELECT
        id,
        license_key,
        user_email,
        plan_id,
        last_order_id,
        woo_subscription_id,
        created_at
    FROM " . DB_PREFIX . "licenses
    WHERE last_order_id != woo_subscription_id
    ORDER BY created_at DESC
");

if (empty($brokenLicenses)) {
    echo "✅ No se encontraron licencias con order_ids incorrectos.\n\n";
    exit(0);
}

echo "🔍 Se encontraron " . count($brokenLicenses) . " licencia(s) con order_ids inconsistentes:\n\n";

foreach ($brokenLicenses as $lic) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "License Key: {$lic['license_key']}\n";
    echo "  Email: {$lic['user_email']}\n";
    echo "  Creada: {$lic['created_at']}\n";
    echo "  ❌ last_order_id: {$lic['last_order_id']} (INCORRECTO)\n";
    echo "  ✓ woo_subscription_id: {$lic['woo_subscription_id']} (CORRECTO - original)\n";
    echo "\n";
}

echo "==========================================================\n";
echo "¿Quieres reparar estas licencias? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 's') {
    echo "Operación cancelada.\n";
    exit(0);
}

echo "\n";
echo "🔧 Reparando licencias...\n\n";

$repaired = 0;
foreach ($brokenLicenses as $lic) {
    // Restaurar last_order_id al valor original (woo_subscription_id)
    $db->query("
        UPDATE " . DB_PREFIX . "licenses
        SET last_order_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$lic['woo_subscription_id'], $lic['id']]);

    echo "✅ {$lic['license_key']}: last_order_id restaurado de {$lic['last_order_id']} → {$lic['woo_subscription_id']}\n";
    $repaired++;
}

echo "\n";
echo "==========================================================\n";
echo "✅ REPARACIÓN COMPLETADA\n";
echo "==========================================================\n\n";
echo "Licencias reparadas: {$repaired}\n";
echo "\n";
echo "📋 PRÓXIMOS PASOS:\n";
echo "1. Ejecuta el sync completo para procesar pedidos sin licencia:\n";
echo "   php cron/auto-sync.php full\n\n";
echo "2. Verifica que Order 620 ahora tenga su licencia:\n";
echo "   php diagnose-discrepancies.php\n\n";
echo "==========================================================\n";
