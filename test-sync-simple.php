<?php
/**
 * Test simple de sincronización
 */

// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; font-family: monospace;">';
echo "=== Test de Sincronización License Keys ===\n\n";

try {
    echo "1. Cargando config...\n";
    require_once __DIR__ . '/config.php';
    echo "   ✓ Config cargado\n\n";

    echo "2. Cargando Database...\n";
    require_once __DIR__ . '/core/Database.php';
    echo "   ✓ Database cargado\n\n";

    echo "3. Cargando Logger...\n";
    require_once __DIR__ . '/core/Logger.php';
    echo "   ✓ Logger cargado\n\n";

    echo "4. Verificando conexión BD...\n";
    $db = Database::getInstance();
    $test = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses");
    echo "   ✓ Conexión OK - Total licencias: " . $test['count'] . "\n\n";

    echo "5. Cargando LicenseKeySyncService...\n";
    require_once __DIR__ . '/core/WooCommerceClient.php';
    require_once __DIR__ . '/services/LicenseKeySyncService.php';
    echo "   ✓ LicenseKeySyncService cargado\n\n";

    echo "6. Creando instancia del servicio...\n";
    $syncService = new LicenseKeySyncService();
    echo "   ✓ Servicio creado\n\n";

    echo "7. Obteniendo estadísticas...\n";
    $stats = $syncService->getSyncStats();
    echo "   ✓ Estadísticas obtenidas:\n";
    echo "      - Total licencias: " . $stats['total_licenses'] . "\n";
    echo "      - Sincronizadas: " . $stats['synced'] . "\n";
    echo "      - Pendientes: " . $stats['pending'] . "\n";
    echo "      - Max intentos: " . $stats['max_attempts'] . "\n";
    echo "      - Sin order ID: " . $stats['no_order_id'] . "\n\n";

    echo "8. Buscando licencias pendientes...\n";
    $pending = $db->query("
        SELECT id, license_key, last_order_id, license_key_sync_attempts
        FROM " . DB_PREFIX . "licenses
        WHERE license_key_synced_to_woo = 0
          AND license_key_sync_attempts < 5
          AND (
              (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
              OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
          )
        LIMIT 5
    ");
    echo "   ✓ Encontradas: " . count($pending) . " licencias\n\n";

    if (!empty($pending)) {
        echo "   Licencias pendientes:\n";
        foreach ($pending as $lic) {
            echo "   - ID: {$lic['id']} | Key: {$lic['license_key']} | Order: {$lic['last_order_id']} | Intentos: {$lic['license_key_sync_attempts']}\n";
        }
        echo "\n";

        echo "9. Test: Sincronizar primera licencia...\n";
        $testLic = $pending[0];
        echo "   Sincronizando licencia ID {$testLic['id']}...\n";

        $result = $syncService->syncLicenseKey($testLic['id']);

        if ($result['success']) {
            echo "   ✓ ÉXITO!\n";
            echo "   Mensaje: " . $result['message'] . "\n";
            if (isset($result['attempts'])) {
                echo "   Intentos realizados: " . $result['attempts'] . "\n";
            }
        } else {
            echo "   ✗ FALLÓ\n";
            echo "   Mensaje: " . $result['message'] . "\n";
            if (isset($result['will_retry'])) {
                echo "   ¿Reintentará?: " . ($result['will_retry'] ? 'Sí' : 'No') . "\n";
            }
        }

        echo "\n   Detalles completos:\n";
        echo "   " . str_replace("\n", "\n   ", print_r($result, true)) . "\n\n";

        echo "10. Test batch: Sincronizar TODAS...\n";
        $batchResult = $syncService->syncPendingLicenseKeys(10);
        echo "   ✓ Batch completado:\n";
        echo "      - Total: " . $batchResult['total'] . "\n";
        echo "      - Sincronizadas: " . $batchResult['synced'] . "\n";
        echo "      - Fallidas: " . $batchResult['failed'] . "\n";
        echo "      - Omitidas: " . $batchResult['skipped'] . "\n";
    } else {
        echo "9. No hay licencias pendientes de sincronizar\n\n";
    }

    echo "\n✅ TEST COMPLETADO EXITOSAMENTE\n";

} catch (Exception $e) {
    echo "\n❌ ERROR:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo '</pre>';
?>
