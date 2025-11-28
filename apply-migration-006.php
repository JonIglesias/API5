<?php
/**
 * Aplicar migración 006: Add license_key_sync_tracking
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "=== Aplicando Migración 006 ===" . PHP_EOL;
echo "Add license_key sync tracking fields" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getInstance();
    $migration = file_get_contents(__DIR__ . '/migrations/006_add_license_key_sync_tracking.sql');

    // Dividir por statements
    $statements = array_filter(array_map('trim', explode(';', $migration)));

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        echo "Ejecutando: " . substr($statement, 0, 60) . "..." . PHP_EOL;
        $db->query($statement);
    }

    echo PHP_EOL;
    echo "✓ Migración 006 aplicada correctamente" . PHP_EOL;
    echo PHP_EOL;

    // Mostrar estructura de la tabla
    echo "Nuevos campos añadidos:" . PHP_EOL;
    $fields = $db->query("SHOW COLUMNS FROM " . DB_PREFIX . "licenses LIKE 'license_key_%'");

    foreach ($fields as $field) {
        echo "  - " . $field['Field'] . " (" . $field['Type'] . ")" . PHP_EOL;
    }

    echo PHP_EOL;
    echo "=== Migración completada ===" . PHP_EOL;

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
