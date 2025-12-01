<?php
/**
 * Script de diagnóstico de discrepancias entre WooCommerce y la API
 *
 * Compara los pedidos de WooCommerce con las licencias en la base de datos
 * e identifica pedidos que deberían tener licencia pero no la tienen.
 *
 * Ejecutar: php diagnose-discrepancies.php
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
echo "DIAGNÓSTICO DE DISCREPANCIAS WooCommerce ↔ API\n";
echo "==========================================================\n\n";

// 1. Obtener mapeo de planes
echo "1️⃣  MAPEO DE PLANES:\n";
echo "==========================================================\n\n";

$plans = $db->query("
    SELECT id, name, woo_product_id, tokens_per_month
    FROM " . DB_PREFIX . "plans
    WHERE woo_product_id IS NOT NULL AND woo_product_id > 0
");

if (empty($plans)) {
    echo "❌ NO HAY PLANES CONFIGURADOS CON woo_product_id\n";
    echo "   El auto-sync NO puede funcionar sin mapeo de productos.\n\n";
    exit(1);
}

$planMap = [];
foreach ($plans as $plan) {
    $planMap[$plan['woo_product_id']] = $plan;
    echo "Plan: {$plan['name']}\n";
    echo "  → Product ID: {$plan['woo_product_id']}\n";
    echo "  → Tokens: {$plan['tokens_per_month']}\n\n";
}

echo "✅ " . count($plans) . " plan(es) configurado(s)\n";
echo "Productos mapeados: " . implode(', ', array_keys($planMap)) . "\n\n";

// 2. Obtener pedidos recientes de WooCommerce (últimas 24 horas)
echo "==========================================================\n";
echo "2️⃣  PEDIDOS EN WOOCOMMERCE (últimas 24 horas):\n";
echo "==========================================================\n\n";

try {
    // Obtener pedidos por fecha de creación (no modificación)
    $orders = $wc->get('orders', [
        'after' => date('c', strtotime('-24 hours')),
        'per_page' => 100,
        'orderby' => 'date',
        'order' => 'desc'
    ]);

    echo "Total pedidos encontrados: " . count($orders) . "\n\n";

    if (empty($orders)) {
        echo "⚠️  No hay pedidos en las últimas 24 horas.\n\n";
    } else {
        $discrepancies = [];

        foreach ($orders as $order) {
            $orderId = $order['id'];
            $status = $order['status'];
            $email = $order['billing']['email'] ?? 'N/A';
            $dateCreated = $order['date_created'] ?? 'N/A';
            $dateModified = $order['date_modified'] ?? 'N/A';

            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Order ID: {$orderId}\n";
            echo "  Estado: {$status}\n";
            echo "  Email: {$email}\n";
            echo "  Creado: {$dateCreated}\n";
            echo "  Modificado: {$dateModified}\n";

            // Calcular tiempo desde creación
            $createdTime = strtotime($dateCreated);
            $minutesAgo = round((time() - $createdTime) / 60);
            echo "  Hace: {$minutesAgo} minutos\n";

            // Verificar productos
            $lineItems = $order['line_items'] ?? [];
            echo "  Productos:\n";

            $hasMatchingProduct = false;
            $matchingProductIds = [];

            foreach ($lineItems as $item) {
                $productId = $item['product_id'];
                $productName = $item['name'];

                echo "    - ID {$productId}: {$productName}\n";

                if (isset($planMap[$productId])) {
                    echo "      ✅ Coincide con plan: {$planMap[$productId]['name']}\n";
                    $hasMatchingProduct = true;
                    $matchingProductIds[] = $productId;
                } else {
                    echo "      ❌ NO coincide con ningún plan configurado\n";
                }
            }

            // Verificar si existe licencia en la base de datos
            $existingLicense = $db->fetchOne("
                SELECT id, license_key, created_at, license_key_synced_to_woo,
                       last_order_id, woo_subscription_id, user_email, plan_id
                FROM " . DB_PREFIX . "licenses
                WHERE last_order_id = ? OR woo_subscription_id = ?
            ", [$orderId, $orderId]);

            if ($existingLicense) {
                echo "\n  ✅ LICENCIA EXISTE:\n";
                echo "     License Key: {$existingLicense['license_key']}\n";
                echo "     Creada: {$existingLicense['created_at']}\n";
                echo "     Sincronizada a WC: " . ($existingLicense['license_key_synced_to_woo'] ? 'Sí' : 'No') . "\n";
                echo "     🔍 DEBUG:\n";
                echo "        last_order_id: " . ($existingLicense['last_order_id'] ?? 'NULL') . "\n";
                echo "        woo_subscription_id: " . ($existingLicense['woo_subscription_id'] ?? 'NULL') . "\n";
                echo "        user_email: " . ($existingLicense['user_email'] ?? 'NULL') . "\n";
                echo "        plan_id: " . ($existingLicense['plan_id'] ?? 'NULL') . "\n";
            } else {
                echo "\n  ❌ NO HAY LICENCIA EN LA BASE DE DATOS\n";

                // Analizar por qué no hay licencia
                echo "\n  📋 ANÁLISIS:\n";

                if (!in_array($status, ['completed', 'processing'])) {
                    echo "     ⚠️  El pedido está en estado '{$status}'\n";
                    echo "        El auto-sync solo procesa pedidos 'completed' o 'processing'\n";
                    echo "        SOLUCIÓN: Cambia el estado del pedido en WooCommerce\n";
                } elseif (!$hasMatchingProduct) {
                    echo "     ⚠️  Ningún producto del pedido coincide con planes configurados\n";
                    echo "        Productos del pedido: " . implode(', ', array_column($lineItems, 'product_id')) . "\n";
                    echo "        Productos mapeados: " . implode(', ', array_keys($planMap)) . "\n";
                    echo "        SOLUCIÓN: Configura el woo_product_id en los planes\n";
                } else {
                    echo "     ❓ El pedido DEBERÍA tener licencia pero no la tiene\n";
                    echo "        - Estado: {$status} ✅\n";
                    echo "        - Tiene productos mapeados: Sí ✅\n";
                    echo "        - Productos coincidentes: " . implode(', ', $matchingProductIds) . "\n";
                    echo "        POSIBLES CAUSAS:\n";
                    echo "          1. El cron no se ha ejecutado desde que se creó el pedido\n";
                    echo "          2. Hay un error en el auto-sync que no se está logueando\n";
                    echo "          3. El pedido se modificó hace más de 7 días y no se capturó\n";

                    // Verificar si el pedido se modificó recientemente
                    $modifiedTime = strtotime($dateModified);
                    $hoursSinceModified = round((time() - $modifiedTime) / 3600, 1);
                    echo "          4. Última modificación hace {$hoursSinceModified} horas\n";

                    if ($hoursSinceModified > 168) {
                        echo "             ⚠️  ¡El pedido se modificó hace más de 7 días!\n";
                        echo "             El auto-sync (SYNC_HOURS_LOOKBACK=168) no lo capturará\n";
                    }
                }

                // Añadir a lista de discrepancias
                $discrepancies[] = [
                    'order_id' => $orderId,
                    'status' => $status,
                    'email' => $email,
                    'has_matching_product' => $hasMatchingProduct,
                    'should_have_license' => $hasMatchingProduct && in_array($status, ['completed', 'processing']),
                    'hours_since_modified' => $hoursSinceModified ?? null
                ];
            }

            echo "\n";
        }

        // 3. Resumen de discrepancias
        echo "==========================================================\n";
        echo "3️⃣  RESUMEN DE DISCREPANCIAS:\n";
        echo "==========================================================\n\n";

        if (empty($discrepancies)) {
            echo "✅ No hay discrepancias. Todos los pedidos tienen su licencia.\n\n";
        } else {
            echo "❌ Se encontraron " . count($discrepancies) . " pedido(s) sin licencia:\n\n";

            $shouldHaveLicense = array_filter($discrepancies, function($d) {
                return $d['should_have_license'];
            });

            if (!empty($shouldHaveLicense)) {
                echo "🚨 PEDIDOS QUE DEBERÍAN TENER LICENCIA:\n";
                foreach ($shouldHaveLicense as $d) {
                    echo "   - Order {$d['order_id']} ({$d['status']}) - {$d['email']}\n";
                    if (isset($d['hours_since_modified']) && $d['hours_since_modified'] > 168) {
                        echo "     ⚠️  Modificado hace más de 7 días - fuera del rango del auto-sync\n";
                    }
                }
                echo "\n";
            }

            echo "📊 ACCIONES RECOMENDADAS:\n";
            echo "==========================================================\n\n";

            if (!empty($shouldHaveLicense)) {
                echo "1. Ejecuta sync completo para procesar pedidos antiguos:\n";
                echo "   php cron/auto-sync.php full\n\n";

                echo "2. Verifica los logs del auto-sync:\n";
                echo "   tail -50 logs/sync.log\n\n";

                echo "3. Si el cron no se está ejecutando, verifica crontab:\n";
                echo "   crontab -l\n\n";
            }
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR al consultar WooCommerce: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n\n";
}

// 4. Verificar última ejecución del cron
echo "==========================================================\n";
echo "4️⃣  ESTADO DEL CRON:\n";
echo "==========================================================\n\n";

$cronStatusFile = __DIR__ . '/logs/cron_status.json';
if (file_exists($cronStatusFile)) {
    $cronStatus = json_decode(file_get_contents($cronStatusFile), true);

    echo "Última ejecución: {$cronStatus['last_run']}\n";
    $lastRun = strtotime($cronStatus['last_run']);
    $minutesSinceLastRun = round((time() - $lastRun) / 60);
    echo "Hace: {$minutesSinceLastRun} minutos\n";
    echo "Tipo: {$cronStatus['sync_type']}\n";
    echo "Éxito: " . ($cronStatus['success'] ? 'Sí' : 'No') . "\n\n";

    echo "Resultados:\n";
    echo "  Creadas: {$cronStatus['results']['created']}\n";
    echo "  Actualizadas: {$cronStatus['results']['updated']}\n";
    echo "  Sin cambios: {$cronStatus['results']['unchanged']}\n";
    echo "  Errores: {$cronStatus['results']['errors']}\n\n";

    if ($minutesSinceLastRun > 10) {
        echo "⚠️  El cron no se ha ejecutado en los últimos 10 minutos.\n";
        echo "   Verifica que el cron esté configurado correctamente.\n\n";
    }
} else {
    echo "❌ No se encontró archivo de estado del cron.\n\n";
}

echo "==========================================================\n";
