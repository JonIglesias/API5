<?php
/**
 * Test manual de sincronización de license keys
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Logger.php';
require_once __DIR__ . '/services/LicenseKeySyncService.php';

echo "=== Test: Sincronizar License Keys Pendientes ===\n\n";

try {
    $syncService = new LicenseKeySyncService();

    // Ver estadísticas
    echo "Obteniendo estadísticas...\n";
    $stats = $syncService->getSyncStats();
    print_r($stats);

    echo "\nBuscando licencias pendientes...\n";

    // Intentar sincronizar la licencia 11 (última creada)
    echo "\nSincronizando licencia ID 11...\n";
    $result = $syncService->syncLicenseKey(11);

    echo "\nResultado:\n";
    print_r($result);

    echo "\n\nSincronizando TODAS las pendientes...\n";
    $batchResults = $syncService->syncPendingLicenseKeys(10);

    echo "\nResultados del batch:\n";
    print_r($batchResults);

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fin del test ===\n";
