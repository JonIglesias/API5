<?php
// Test simple para ver qué está fallando
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Iniciando test...\n";

// Definir API_ACCESS
if (!defined('API_ACCESS')) {
    define('API_ACCESS', true);
}
echo "2. API_ACCESS definido\n";

// Intentar cargar config
try {
    require_once __DIR__ . '/../../../config.php';
    echo "3. Config cargado OK\n";
    echo "4. DB_PREFIX = " . (defined('DB_PREFIX') ? DB_PREFIX : 'NO DEFINIDO') . "\n";
} catch (Exception $e) {
    echo "ERROR config: " . $e->getMessage() . "\n";
    die();
}

// Intentar cargar Database
try {
    require_once __DIR__ . '/../../../core/Database.php';
    echo "5. Database class cargada OK\n";
} catch (Exception $e) {
    echo "ERROR Database: " . $e->getMessage() . "\n";
    die();
}

// Intentar conectar
try {
    $db = Database::getInstance();
    echo "6. Database conectada OK\n";
} catch (Exception $e) {
    echo "ERROR conexión: " . $e->getMessage() . "\n";
    die();
}

// Intentar query simple
try {
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "licenses");
    echo "7. Query OK - Total licencias: " . $result[0]['total'] . "\n";
} catch (Exception $e) {
    echo "ERROR query: " . $e->getMessage() . "\n";
    die();
}

echo "✅ Todo OK!\n";
