<?php
/**
 * Cron: Reset Monthly Tokens
 * 
 * Ejecutar diariamente a las 00:01
 * 1 0 * * * php /path/to/api_claude_4/cron/reset-monthly-tokens.php
 * 
 * @version 4.0
 */

define('API_ACCESS', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/License.php';
require_once __DIR__ . '/../services/TokenManager.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting monthly token reset check...\n";

try {
    $licenseModel = new License();
    $tokenManager = new TokenManager();
    
    // Obtener todas las licencias activas
    $licenses = $licenseModel->getAll(1, 10000, ['status' => 'active']);
    
    echo "Checking " . count($licenses) . " active licenses\n";
    
    $reset = 0;
    
    foreach ($licenses as $license) {
        if ($tokenManager->checkAndResetIfNeeded($license)) {
            echo "Reset tokens for license: {$license['license_key']}\n";
            $reset++;
        }
    }
    
    echo "\nCompleted!\n";
    echo "Tokens reset for {$reset} licenses\n";
    
    Logger::sync('info', 'Monthly token reset completed', [
        'reset' => $reset
    ]);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    Logger::sync('error', 'Monthly token reset failed', [
        'error' => $e->getMessage()
    ]);
    
    exit(1);
}
