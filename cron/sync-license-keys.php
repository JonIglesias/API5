<?php
/**
 * Cron: Sincronizar License Keys a WooCommerce
 *
 * Este cron se ejecuta cada 5 minutos para sincronizar todas las license_keys
 * pendientes a WooCommerce, con reintentos automáticos para las que fallaron.
 *
 * Uso:
 *   php cron/sync-license-keys.php
 *
 * Crontab:
 *   (cada 5 minutos) cd /path/to/api && php cron/sync-license-keys.php >> logs/cron.log 2>&1
 *
 * @version 1.0
 */

define('API_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/core/Logger.php';
require_once API_BASE_DIR . '/services/LicenseKeySyncService.php';

echo "=== License Key Sync Cron ===" . PHP_EOL;
echo "Started at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    $syncService = new LicenseKeySyncService();

    // Obtener estadísticas antes
    echo "Getting sync statistics..." . PHP_EOL;
    $statsBefore = $syncService->getSyncStats();

    echo "Statistics:" . PHP_EOL;
    echo "  Total licenses: " . $statsBefore['total_licenses'] . PHP_EOL;
    echo "  Already synced: " . $statsBefore['synced'] . PHP_EOL;
    echo "  Pending sync: " . $statsBefore['pending'] . PHP_EOL;
    echo "  Max attempts reached: " . $statsBefore['max_attempts'] . PHP_EOL;
    echo "  No order ID: " . $statsBefore['no_order_id'] . PHP_EOL;
    echo PHP_EOL;

    if ($statsBefore['pending'] == 0) {
        echo "✓ No pending license keys to sync" . PHP_EOL;
        exit(0);
    }

    // Sincronizar licencias pendientes
    echo "Syncing pending license keys..." . PHP_EOL;
    $results = $syncService->syncPendingLicenseKeys(100);

    echo PHP_EOL;
    echo "Sync Results:" . PHP_EOL;
    echo "  Processed: " . $results['total'] . PHP_EOL;
    echo "  ✓ Synced: " . $results['synced'] . PHP_EOL;
    echo "  ✗ Failed: " . $results['failed'] . PHP_EOL;
    echo "  ⊘ Skipped: " . $results['skipped'] . PHP_EOL;
    echo PHP_EOL;

    // Estadísticas después
    $statsAfter = $syncService->getSyncStats();
    echo "Updated Statistics:" . PHP_EOL;
    echo "  Already synced: " . $statsAfter['synced'] . " (+" . ($statsAfter['synced'] - $statsBefore['synced']) . ")" . PHP_EOL;
    echo "  Pending sync: " . $statsAfter['pending'] . " (-" . ($statsBefore['pending'] - $statsAfter['pending']) . ")" . PHP_EOL;
    echo PHP_EOL;

    Logger::cron('info', 'License key sync completed', $results);

    echo "Completed at: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo "==================================" . PHP_EOL;

    exit(0);

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo PHP_EOL;

    Logger::cron('error', 'License key sync failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    exit(1);
}
