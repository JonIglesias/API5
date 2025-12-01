<?php
/**
 * Script de diagnóstico para creación automática de licencias desde pedidos
 *
 * Ejecutar: php diagnose-orders.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/WooCommerceClient.php';

$db = Database::getInstance();
$wc = new WooCommerceClient();

echo "==========================================================\n";
echo "DIAGNÓSTICO DE CREACIÓN AUTOMÁTICA DE LICENCIAS\n";
echo "==========================================================\n\n";

// 1. Verificar mapeo de productos a planes
echo "1️⃣  MAPEO DE PRODUCTOS A PLANES:\n";
echo "==========================================================\n\n";

$plans = $db->query("
    SELECT id, name, woo_product_id, tokens_per_month
    FROM " . DB_PREFIX . "plans
    ORDER BY id
");

$planMap = [];
$plansWithProducts = 0;

foreach ($plans as $plan) {
    $hasProduct = !empty($plan['woo_product_id']) && $plan['woo_product_id'] > 0;

    echo "Plan ID: {$plan['id']}\n";
    echo "  Nombre: {$plan['name']}\n";
    echo "  WooCommerce Product ID: " . ($plan['woo_product_id'] ?: '❌ NO CONFIGURADO') . "\n";
    echo "  Tokens/mes: {$plan['tokens_per_month']}\n";

    if ($hasProduct) {
        $plansWithProducts++;
        $planMap[$plan['woo_product_id']] = $plan;
        echo "  ✅ Plan correctamente mapeado\n";
    } else {
        echo "  ⚠️  PROBLEMA: Este plan NO tiene woo_product_id configurado\n";
        echo "     El auto-sync NO creará licencias para este plan\n";
    }
    echo "  ---\n";
}

if ($plansWithProducts == 0) {
    echo "\n❌ PROBLEMA CRÍTICO: Ningún plan tiene woo_product_id configurado!\n";
    echo "   El auto-sync NO puede crear licencias sin este mapeo.\n";
    echo "   ACCIÓN: Configura el woo_product_id en cada plan desde el panel admin.\n\n";
    exit(1);
} else {
    echo "\n✅ {$plansWithProducts} plan(es) configurado(s) correctamente.\n\n";
}

// 2. Verificar pedidos recientes en WooCommerce
echo "==========================================================\n";
echo "2️⃣  PEDIDOS RECIENTES EN WOOCOMMERCE (últimas 2 horas):\n";
echo "==========================================================\n\n";

try {
    $since = date('c', strtotime('-2 hours'));
    echo "Buscando pedidos modificados desde: {$since}\n\n";

    $orders = $wc->getOrdersModifiedAfter($since, 1, 20);

    if (empty($orders)) {
        echo "⚠️  No hay pedidos modificados en las últimas 2 horas.\n";
        echo "   Esto es normal si no ha habido ventas recientes.\n\n";
    } else {
        echo "Encontrados " . count($orders) . " pedido(s):\n\n";

        foreach ($orders as $order) {
            $orderId = $order['id'];
            $status = $order['status'];
            $email = $order['billing']['email'] ?? 'N/A';
            $total = $order['total'] ?? '0';
            $currency = $order['currency'] ?? 'EUR';

            echo "Order ID: {$orderId}\n";
            echo "  Estado: {$status}\n";
            echo "  Email: {$email}\n";
            echo "  Total: {$total} {$currency}\n";

            // Verificar si el estado es procesable
            $isProcessable = in_array($status, ['completed', 'processing']);
            echo "  Procesable: " . ($isProcessable ? '✅ SÍ' : '❌ NO (estado: ' . $status . ')') . "\n";

            // Verificar productos del pedido
            $lineItems = $order['line_items'] ?? [];
            echo "  Productos: " . count($lineItems) . "\n";

            $hasMatchingProduct = false;
            foreach ($lineItems as $item) {
                $productId = $item['product_id'];
                $productName = $item['name'];

                echo "    - Producto ID: {$productId} ({$productName})\n";

                if (isset($planMap[$productId])) {
                    echo "      ✅ Coincide con plan: {$planMap[$productId]['name']}\n";
                    $hasMatchingProduct = true;
                } else {
                    echo "      ⚠️  NO coincide con ningún plan configurado\n";
                }
            }

            // Verificar si ya existe licencia para este pedido
            $existingLicense = $db->fetchOne("
                SELECT license_key FROM " . DB_PREFIX . "licenses
                WHERE last_order_id = ? OR woo_subscription_id = ?
            ", [$orderId, $orderId]);

            if ($existingLicense) {
                echo "  ℹ️  Ya existe licencia: {$existingLicense['license_key']}\n";
            }

            // Diagnóstico final
            echo "\n  📋 DIAGNÓSTICO:\n";
            if (!$isProcessable) {
                echo "     ❌ Este pedido NO se procesará porque el estado '{$status}' no es procesable\n";
                echo "        Estados procesables: completed, processing\n";
            } elseif (!$hasMatchingProduct) {
                echo "     ❌ Este pedido NO se procesará porque ningún producto coincide con planes configurados\n";
                echo "        Productos mapeados: " . implode(', ', array_keys($planMap)) . "\n";
            } elseif ($existingLicense) {
                echo "     ℹ️  Este pedido ya tiene licencia creada (no creará duplicado)\n";
            } else {
                echo "     ✅ Este pedido DEBERÍA crear una licencia en el próximo sync\n";
            }

            echo "  ---\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ ERROR al consultar WooCommerce: " . $e->getMessage() . "\n\n";
}

// 3. Verificar pedidos de las últimas 24 horas para más contexto
echo "==========================================================\n";
echo "3️⃣  RESUMEN PEDIDOS ÚLTIMAS 24 HORAS:\n";
echo "==========================================================\n\n";

try {
    $since24h = date('c', strtotime('-24 hours'));
    $orders24h = $wc->getOrdersModifiedAfter($since24h, 1, 100);

    $statusCount = [];
    $processableCount = 0;
    $withMatchingProducts = 0;

    foreach ($orders24h as $order) {
        $status = $order['status'];
        $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;

        $isProcessable = in_array($status, ['completed', 'processing']);
        if ($isProcessable) {
            $processableCount++;

            // Verificar si tiene productos mapeados
            foreach ($order['line_items'] ?? [] as $item) {
                if (isset($planMap[$item['product_id']])) {
                    $withMatchingProducts++;
                    break;
                }
            }
        }
    }

    echo "Total de pedidos: " . count($orders24h) . "\n\n";

    echo "Por estado:\n";
    foreach ($statusCount as $status => $count) {
        echo "  - {$status}: {$count}\n";
    }

    echo "\nPedidos procesables (completed/processing): {$processableCount}\n";
    echo "Pedidos con productos mapeados a planes: {$withMatchingProducts}\n\n";

    if ($withMatchingProducts == 0 && count($orders24h) > 0) {
        echo "⚠️  Hay pedidos pero NINGUNO tiene productos mapeados a planes.\n";
        echo "   Verifica que los woo_product_id de los planes coincidan con los productos de WooCommerce.\n\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// 4. Recomendaciones
echo "==========================================================\n";
echo "RECOMENDACIONES:\n";
echo "==========================================================\n\n";

if ($plansWithProducts == 0) {
    echo "🔧 ACCIÓN REQUERIDA:\n";
    echo "   1. Ve al panel de admin de la API\n";
    echo "   2. Sección Planes\n";
    echo "   3. Edita cada plan y configura el 'WooCommerce Product ID'\n";
    echo "   4. Usa el ID del producto/suscripción de WooCommerce\n\n";
} else {
    echo "✅ Los planes están configurados correctamente.\n\n";

    if (empty($orders)) {
        echo "ℹ️  No hay pedidos recientes (últimas 2 horas).\n";
        echo "   Esto es normal si no ha habido ventas.\n";
        echo "   El auto-sync creará licencias automáticamente cuando lleguen pedidos.\n\n";
    }
}

echo "📚 DOCUMENTACIÓN:\n";
echo "   - El auto-sync se ejecuta cada 5 minutos\n";
echo "   - Busca pedidos modificados en las últimas 2 horas\n";
echo "   - Solo procesa pedidos en estado 'completed' o 'processing'\n";
echo "   - Solo crea licencias para productos mapeados a planes\n";
echo "   - Envía automáticamente las licencias a WooCommerce\n\n";

echo "==========================================================\n";
