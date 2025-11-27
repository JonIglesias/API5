<?php
/**
 * Installation Script
 * 
 * @version 4.0
 * 
 * Este script ejecuta todas las migraciones SQL
 * Ejecutar una sola vez después de configurar config.php
 */

define('API_ACCESS', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';

echo "==============================================\n";
echo "API Claude V4 - Installation\n";
echo "==============================================\n\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Obtener todos los archivos SQL
    $migrations = glob(__DIR__ . '/*.sql');
    sort($migrations);
    
    echo "Found " . count($migrations) . " migration files\n\n";
    
    foreach ($migrations as $migration) {
        $filename = basename($migration);
        echo "Running: {$filename}...\n";
        
        $sql = file_get_contents($migration);
        
        // Ejecutar cada statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        echo "✓ {$filename} completed\n\n";
    }
    
    echo "==============================================\n";
    echo "Installation completed successfully!\n";
    echo "==============================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Update config.php with your database credentials\n";
    echo "2. Update config.php with your WooCommerce API keys\n";
    echo "3. Setup cron jobs (see /cron/README.md)\n";
    echo "4. Access admin panel: " . API_BASE_URL . "/admin/\n";
    echo "5. Default login: admin / admin123 (CHANGE THIS!)\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Installation failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
