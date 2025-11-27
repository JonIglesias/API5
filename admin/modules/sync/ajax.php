<?php
/**
 * AJAX Sync - VERSIÓN COMPLETA CON TODA LA INFO
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

define('API_ACCESS', true);

try {
    session_start();
    
    $baseDir = dirname(dirname(dirname(dirname(__FILE__))));
    
    require_once $baseDir . '/config.php';
    require_once $baseDir . '/core/Database.php';
    require_once $baseDir . '/core/Auth.php';
    require_once $baseDir . '/core/WooCommerceClient.php';
    
    if (!Auth::check()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        echo json_encode(['success' => false, 'error' => 'No se especificó acción']);
        exit;
    }
    
    $db = Database::getInstance();
    $wc = new WooCommerceClient();
    
    if ($action === 'sync_all') {
        try {
            // 1. Obtener planes mapeados
            $plans = $db->query("SELECT id, woo_product_id, tokens_per_month FROM " . DB_PREFIX . "plans WHERE woo_product_id IS NOT NULL AND woo_product_id > 0");
            
            if (empty($plans)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No hay planes mapeados. Ve a Planes y mapea al menos un plan.'
                ]);
                exit;
            }
            
            $planMap = [];
            $productIds = [];
            foreach ($plans as $plan) {
                $planMap[$plan['woo_product_id']] = $plan;
                $productIds[] = $plan['woo_product_id'];
            }
            
            // 2. Obtener productos completos para tener nombres
            $products = $wc->get('products', ['include' => implode(',', $productIds), 'per_page' => 100]);
            $productNames = [];
            foreach ($products as $prod) {
                $productNames[$prod['id']] = $prod['name'];
            }
            
            // 3. Buscar órdenes completadas/procesadas
            $orders = $wc->get('orders', [
                'status' => 'completed,processing',
                'per_page' => 100,
                'orderby' => 'date',
                'order' => 'desc'
            ]);
            
            if (empty($orders)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No hay órdenes en WooCommerce',
                    'count' => 0
                ]);
                exit;
            }
            
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            // 4. Procesar cada orden
            foreach ($orders as $order) {
                try {
                    $orderId = $order['id'];
                    $customerId = $order['customer_id'];
                    
                    // Extraer TODA la info del cliente
                    $billing = $order['billing'] ?? [];
                    $email = $billing['email'] ?? '';
                    $firstName = $billing['first_name'] ?? '';
                    $lastName = $billing['last_name'] ?? '';
                    $customerName = trim($firstName . ' ' . $lastName);
                    $country = $billing['country'] ?? '';
                    
                    if (empty($email)) {
                        $skipped++;
                        $errors[] = "Orden #{$orderId}: Sin email";
                        continue;
                    }
                    
                    // Fecha de la orden
                    $orderDate = $order['date_created'] ?? date('Y-m-d H:i:s');
                    
                    // Método de pago
                    $paymentMethod = $order['payment_method_title'] ?? '';
                    
                    // Moneda y total
                    $currency = $order['currency'] ?? 'EUR';
                    
                    // Buscar producto de suscripción en line_items
                    $subscriptionProduct = null;
                    $productPrice = 0;
                    $productName = '';
                    
                    foreach ($order['line_items'] as $item) {
                        $productId = $item['product_id'];
                        
                        if (isset($planMap[$productId])) {
                            $subscriptionProduct = [
                                'product_id' => $productId,
                                'plan' => $planMap[$productId]
                            ];
                            $productPrice = floatval($item['total']);
                            $productName = $productNames[$productId] ?? $item['name'];
                            break;
                        }
                    }
                    
                    if (!$subscriptionProduct) {
                        continue;
                    }
                    
                    $plan = $subscriptionProduct['plan'];
                    $productId = $subscriptionProduct['product_id'];
                    
                    // Determinar ciclo de facturación (monthly/yearly)
                    $billingCycleText = 'Mensual'; // Por defecto
                    if (stripos($productName, 'anual') !== false || stripos($productName, 'año') !== false) {
                        $billingCycleText = 'Anual';
                    }
                    
                    // Calcular período
                    $periodStarts = $orderDate;
                    $periodEnds = date('Y-m-d H:i:s', strtotime($orderDate . ' +1 month'));
                    
                    // Verificar si ya existe licencia
                    $existing = $db->fetchOne(
                        "SELECT * FROM " . DB_PREFIX . "licenses WHERE user_email = ? AND plan_id = ?",
                        [$email, $plan['id']]
                    );
                    
                    if ($existing) {
                        // ACTUALIZAR
                        $stmt = $db->prepare("
                            UPDATE " . DB_PREFIX . "licenses 
                            SET customer_name = ?,
                                subscription_price = ?,
                                currency = ?,
                                billing_cycle_text = ?,
                                order_date = ?,
                                last_order_id = ?,
                                customer_country = ?,
                                payment_method = ?,
                                woo_product_name = ?,
                                tokens_limit = ?,
                                period_ends_at = ?,
                                woo_user_id = ?,
                                last_synced_at = NOW(),
                                sync_status = 'fresh',
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $customerName,
                            $productPrice,
                            $currency,
                            $billingCycleText,
                            $orderDate,
                            $orderId,
                            $country,
                            $paymentMethod,
                            $productName,
                            $plan['tokens_per_month'],
                            $periodEnds,
                            $customerId,
                            $existing['id']
                        ]);
                        
                        $db->insert('sync_logs', [
                            'license_id' => $existing['id'],
                            'sync_type' => 'manual',
                            'status' => 'success',
                            'changes_detected' => json_encode(['action' => 'updated', 'order_id' => $orderId]),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $updated++;
                    } else {
                        // CREAR
                        $licenseKey = generateLicenseKey($plan['id']);
                        
                        $licenseId = $db->insert('licenses', [
                            'license_key' => $licenseKey,
                            'user_email' => $email,
                            'customer_name' => $customerName,
                            'subscription_price' => $productPrice,
                            'currency' => $currency,
                            'billing_cycle_text' => $billingCycleText,
                            'order_date' => $orderDate,
                            'last_order_id' => $orderId,
                            'customer_country' => $country,
                            'payment_method' => $paymentMethod,
                            'woo_product_name' => $productName,
                            'woo_subscription_id' => $orderId,
                            'woo_user_id' => $customerId,
                            'plan_id' => $plan['id'],
                            'status' => 'active',
                            'domain' => '',
                            'tokens_limit' => $plan['tokens_per_month'],
                            'tokens_used_this_period' => 0,
                            'period_starts_at' => $periodStarts,
                            'period_ends_at' => $periodEnds,
                            'last_synced_at' => date('Y-m-d H:i:s'),
                            'sync_status' => 'fresh',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $db->insert('sync_logs', [
                            'license_id' => $licenseId,
                            'sync_type' => 'manual',
                            'status' => 'success',
                            'changes_detected' => json_encode([
                                'action' => 'created',
                                'order_id' => $orderId,
                                'license_key' => $licenseKey
                            ]),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $created++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Orden #{$orderId}: " . $e->getMessage();
                    $skipped++;
                }
            }
            
            $message = "✅ Sync completo: {$created} creadas, {$updated} actualizadas";
            if ($skipped > 0) {
                $message .= ", {$skipped} omitidas";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'stats' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped
                ],
                'errors' => $errors
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    if ($action === 'test_connection') {
        try {
            $wc = new WooCommerceClient();
            $products = $wc->get('products', ['per_page' => 5]);

            echo json_encode([
                'success' => true,
                'message' => '✅ Conexión OK - ' . count($products) . ' productos'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    // ⭐ AUTO-SYNC: Sincronizar suscripciones de WooCommerce (nuevo sistema)
    if ($action === 'auto_sync') {
        try {
            require_once $baseDir . '/services/AutoSyncService.php';

            $syncType = $_POST['sync_type'] ?? 'recent';
            $autoSync = new AutoSyncService();

            if ($syncType === 'full') {
                $results = $autoSync->syncAll();
            } else {
                $results = $autoSync->syncRecent(2);
            }

            // Guardar estado (manual desde panel admin)
            $autoSync->saveCronStatus($results, $syncType, 'manual');

            $message = "✅ Auto-sync completado: {$results['created']} creadas, {$results['updated']} actualizadas, {$results['unchanged']} sin cambios";

            if ($results['skipped'] > 0) {
                $message .= ", {$results['skipped']} omitidas";
            }
            if ($results['errors'] > 0) {
                $message .= ", {$results['errors']} errores";
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'stats' => $results,
                'errors' => $results['details']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error en auto-sync: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // ⭐ GET SYNC STATS: Obtener estadísticas de sincronización
    if ($action === 'get_sync_stats') {
        try {
            require_once $baseDir . '/services/AutoSyncService.php';

            $autoSync = new AutoSyncService();
            $stats = $autoSync->getStats();

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    // ⭐ CLEAN LOGS: Limpiar todos los logs manualmente
    if ($action === 'clean_logs') {
        try {
            require_once $baseDir . '/services/AutoSyncService.php';

            $autoSync = new AutoSyncService();
            $cleaned = $autoSync->cleanAllLogsNow();

            $message = "✅ Logs limpiados: {$cleaned['db_deleted']} registros de BD eliminados";

            if (!empty($cleaned['files_truncated'])) {
                $message .= ", " . count($cleaned['files_truncated']) . " archivos vaciados";
            }
            if (!empty($cleaned['files_deleted'])) {
                $message .= ", " . count($cleaned['files_deleted']) . " archivos eliminados";
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'details' => $cleaned
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error limpiando logs: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
    
} catch (Throwable $e) {
    error_log('AJAX Sync Error: ' . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
    exit;
}

function generateLicenseKey($planId) {
    $prefix = strtoupper(substr($planId, 0, 4));
    $year = date('Y');
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    return "{$prefix}-{$year}-{$random}";
}
