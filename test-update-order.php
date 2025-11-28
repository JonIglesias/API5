<?php
/**
 * Script de prueba para actualizar un pedido en WooCommerce con license_key
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/core/WooCommerceClient.php';
require_once API_BASE_DIR . '/core/Logger.php';

echo "=== Test: Actualizar pedido con license key ===\n\n";

$orderId = 355;
$licenseKey = 'DEMO-2025-031EE4CC';

echo "Pedido ID: {$orderId}\n";
echo "License Key: {$licenseKey}\n\n";

$wc = new WooCommerceClient();

try {
    echo "Intentando actualizar pedido en WooCommerce...\n";
    $result = $wc->updateOrderMeta($orderId, '_license_key', $licenseKey);

    echo "✓ SUCCESS!\n\n";
    echo "Respuesta de WooCommerce:\n";
    print_r($result);

} catch (Exception $e) {
    echo "✗ ERROR!\n\n";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}

echo "\n=== Fin del test ===\n";
