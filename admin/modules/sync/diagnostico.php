<?php
/**
 * DIAGNÓSTICO - Detectar cómo acceder a suscripciones en WooCommerce
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

session_start();

$baseDir = dirname(dirname(dirname(dirname(__FILE__))));

require_once $baseDir . '/config.php';
require_once $baseDir . '/core/Database.php';
require_once $baseDir . '/core/Auth.php';
require_once $baseDir . '/core/WooCommerceClient.php';

if (!Auth::check()) {
    die('No autenticado');
}

$wc = new WooCommerceClient();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico WooCommerce</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .result { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .info { border-left: 4px solid #17a2b8; }
        h2 { margin-top: 30px; }
        pre { background: #f8f9fa; padding: 10px; overflow: auto; max-height: 300px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Suscripciones WooCommerce</h1>
    <p>Este script va a probar diferentes formas de acceder a tus suscripciones.</p>

    <?php
    echo "<h2>1. Configuración Actual</h2>";
    echo "<div class='result info'>";
    echo "<strong>URL API:</strong> " . WC_API_URL . "<br>";
    echo "<strong>Consumer Key:</strong> " . substr(WC_CONSUMER_KEY, 0, 10) . "...<br>";
    echo "</div>";

    // Test 1: Conexión básica
    echo "<h2>2. Test de Conexión Básica</h2>";
    try {
        $system = $wc->get('');
        echo "<div class='result success'>";
        echo "✅ Conexión OK - WooCommerce API responde correctamente";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='result error'>";
        echo "❌ Error de conexión: " . $e->getMessage();
        echo "</div>";
        die();
    }

    // Test 2: Listar productos
    echo "<h2>3. Test de Productos</h2>";
    try {
        $products = $wc->get('products', ['per_page' => 5]);
        echo "<div class='result success'>";
        echo "✅ Productos encontrados: " . count($products) . "<br><br>";
        echo "<strong>Tus productos:</strong><br>";
        foreach ($products as $p) {
            $type = $p['type'] ?? 'simple';
            echo "- ID: {$p['id']} | Nombre: {$p['name']} | Tipo: {$type}<br>";
        }
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='result error'>❌ " . $e->getMessage() . "</div>";
    }

    // Test 3: Endpoint /subscriptions (WooCommerce Subscriptions oficial)
    echo "<h2>4. Test Endpoint: /subscriptions</h2>";
    try {
        $subs = $wc->get('subscriptions', ['per_page' => 5]);
        echo "<div class='result success'>";
        echo "✅ ENCONTRADO - Endpoint /subscriptions funciona<br>";
        echo "Suscripciones: " . count($subs) . "<br>";
        if (!empty($subs)) {
            echo "<pre>" . print_r($subs[0], true) . "</pre>";
        }
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='result error'>";
        echo "❌ No disponible: " . $e->getMessage();
        echo "</div>";
    }

    // Test 4: Buscar en órdenes
    echo "<h2>5. Test: Órdenes Recientes</h2>";
    try {
        $orders = $wc->get('orders', ['per_page' => 10, 'orderby' => 'date', 'order' => 'desc']);
        echo "<div class='result info'>";
        echo "📦 Órdenes encontradas: " . count($orders) . "<br><br>";
        
        $subscriptionOrders = [];
        foreach ($orders as $order) {
            // Buscar indicios de suscripción
            $hasSub = false;
            $subInfo = [];
            
            // Revisar meta_data
            if (isset($order['meta_data'])) {
                foreach ($order['meta_data'] as $meta) {
                    if (isset($meta['key']) && (
                        strpos(strtolower($meta['key']), 'subscription') !== false ||
                        strpos(strtolower($meta['key']), 'recurring') !== false
                    )) {
                        $hasSub = true;
                        $subInfo[] = $meta['key'] . ' = ' . json_encode($meta['value']);
                    }
                }
            }
            
            // Revisar line_items
            if (isset($order['line_items'])) {
                foreach ($order['line_items'] as $item) {
                    if (isset($item['meta_data'])) {
                        foreach ($item['meta_data'] as $meta) {
                            if (isset($meta['key']) && (
                                strpos(strtolower($meta['key']), 'subscription') !== false ||
                                strpos(strtolower($meta['key']), 'recurring') !== false
                            )) {
                                $hasSub = true;
                                $subInfo[] = $meta['key'] . ' = ' . json_encode($meta['value']);
                            }
                        }
                    }
                }
            }
            
            if ($hasSub) {
                $subscriptionOrders[] = [
                    'order' => $order,
                    'info' => $subInfo
                ];
            }
        }
        
        if (!empty($subscriptionOrders)) {
            echo "✅ <strong>Órdenes con indicios de suscripción: " . count($subscriptionOrders) . "</strong><br><br>";
            foreach ($subscriptionOrders as $so) {
                $o = $so['order'];
                echo "<strong>Orden #{$o['id']}</strong> - {$o['status']} - Cliente: {$o['billing']['email']}<br>";
                echo "Productos: ";
                foreach ($o['line_items'] as $item) {
                    echo "{$item['name']} (Producto ID: {$item['product_id']}) ";
                }
                echo "<br>";
                echo "Info suscripción:<br>";
                foreach ($so['info'] as $info) {
                    echo "  - {$info}<br>";
                }
                echo "<hr>";
            }
        } else {
            echo "⚠️ No se encontraron órdenes con metadatos de suscripción";
        }
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='result error'>❌ " . $e->getMessage() . "</div>";
    }

    // Test 5: Productos del tipo suscripción
    echo "<h2>6. Test: Productos de Suscripción</h2>";
    try {
        $allProducts = $wc->get('products', ['per_page' => 50]);
        echo "<div class='result info'>";
        
        $subProducts = [];
        foreach ($allProducts as $p) {
            $type = $p['type'] ?? '';
            // Buscar productos con tipo relacionado a suscripción
            if (strpos($type, 'subscription') !== false || 
                strpos($type, 'variable-subscription') !== false ||
                (isset($p['meta_data']) && !empty($p['meta_data']))) {
                
                // Revisar meta_data del producto
                $subMeta = [];
                if (isset($p['meta_data'])) {
                    foreach ($p['meta_data'] as $meta) {
                        if (isset($meta['key']) && strpos(strtolower($meta['key']), 'sub') !== false) {
                            $subMeta[] = $meta;
                        }
                    }
                }
                
                if ($type !== 'simple' || !empty($subMeta)) {
                    $subProducts[] = [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'type' => $type,
                        'meta' => $subMeta
                    ];
                }
            }
        }
        
        if (!empty($subProducts)) {
            echo "✅ <strong>Productos de suscripción encontrados: " . count($subProducts) . "</strong><br><br>";
            foreach ($subProducts as $sp) {
                echo "<strong>#{$sp['id']}</strong> - {$sp['name']} (Tipo: {$sp['type']})<br>";
                if (!empty($sp['meta'])) {
                    echo "Meta:<br>";
                    foreach ($sp['meta'] as $m) {
                        echo "  - {$m['key']}<br>";
                    }
                }
                echo "<hr>";
            }
        } else {
            echo "⚠️ No se encontraron productos con tipo 'subscription'";
        }
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='result error'>❌ " . $e->getMessage() . "</div>";
    }

    echo "<h2>📋 Conclusión</h2>";
    echo "<div class='result info'>";
    echo "<strong>Para sincronizar tus suscripciones, necesito que me digas:</strong><br><br>";
    echo "1. ¿Qué test dio resultado positivo arriba?<br>";
    echo "2. ¿Cuál es el ID del producto que tiene suscripción? (deberías verlo en el Test 3 o Test 6)<br>";
    echo "3. ¿Viste alguna orden con datos de suscripción en el Test 5?<br><br>";
    echo "Con esa info te creo el sync correcto.";
    echo "</div>";
    ?>

    <br>
    <a href="?module=sync" class="btn">Volver al Sync</a>
</body>
</html>
