<?php
/**
 * Migración 007 - Añadir columna user_email
 * 
 * Ejecuta este archivo UNA VEZ desde el navegador
 */

define('API_ACCESS', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

echo "<h1>Migración 007: Añadir user_email</h1>";

try {
    // Verificar si la columna ya existe
    $result = $db->query("SHOW COLUMNS FROM " . DB_PREFIX . "licenses LIKE 'user_email'");
    
    if (!empty($result)) {
        echo "<p style='color: orange;'>⚠️ La columna 'user_email' ya existe. No es necesario migrar.</p>";
        exit;
    }
    
    // Ejecutar migración
    $db->exec("
        ALTER TABLE `" . DB_PREFIX . "licenses` 
        ADD COLUMN `user_email` VARCHAR(255) NULL AFTER `woo_user_id`,
        ADD KEY `user_email` (`user_email`)
    ");
    
    echo "<p style='color: green; font-weight: bold;'>✅ Migración completada con éxito</p>";
    echo "<p>La columna 'user_email' ha sido añadida a la tabla de licencias.</p>";
    echo "<p><a href='../admin/?module=sync'>Ir al módulo de Sync</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Intenta ejecutarlo manualmente desde phpMyAdmin.</p>";
}
?>