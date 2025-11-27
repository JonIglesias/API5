<?php
/**
 * Cron: Sync Critical Licenses
 * 
 * Ejecutar cada 30 minutos
 * */30 * * * * php /path/to/api_claude_4/cron/sync-critical.php
 * 
 * @version 4.0
 */

define('API_ACCESS', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/License.php';
require_once __DIR__ . '/../services/SyncService.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting critical licenses sync...\n";

try {
    $licenseModel = new License();
    $syncService = new SyncService();
    
    // Obtener licencias críticas
    $licenses = $licenseModel->getCriticalLicenses();
    
    echo "Found " . count($licenses) . " critical licenses\n";
    
    $synced = 0;
    $failed = 0;
    
    foreach ($licenses as $license) {
        // Verificar si necesita sync
        if (!$licenseModel->needsSync($license)) {
            continue;
        }
        
        echo "Syncing license: {$license['license_key']}...";
        
        $result = $syncService->syncLicense($license['license_key'], 'cron_critical');
        
        if ($result['success']) {
            echo " ✓\n";
            $synced++;
        } else {
            echo " ✗ ({$result['message']})\n";
            $failed++;
        }
    }
    
    echo "\nCompleted!\n";
    echo "Synced: {$synced}\n";
    echo "Failed: {$failed}\n";
    
    Logger::sync('info', 'Critical sync completed', [
        'synced' => $synced,
        'failed' => $failed
    ]);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    Logger::sync('error', 'Critical sync failed', [
        'error' => $e->getMessage()
    ]);
    
    exit(1);
}
