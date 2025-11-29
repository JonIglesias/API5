<?php
/**
 * AJAX Sync - VERSIÓN COMPLETA CON TODA LA INFO
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // TEMPORAL - ver errores
ini_set('log_errors', 1);

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
            
            // 2. Obtener productos completos
            $products = $wc->get('products', ['include' => implode(',', $productIds), 'per_page' => 100]);
            $productNames = [];
            foreach ($products as $prod) {
                $productNames[$prod['id']] = $prod['name'];
            }
            
            // 3. Buscar SUSCRIPCIONES fsb_subscription desde WooCommerce API
            // Flexible Subscriptions usa type=fsb_subscription
            $subscriptions = $wc->get('orders', [
                'type' => 'fsb_subscription',
                'status' => 'wc-active,wc-on-hold,wc-pending-cancel',
                'per_page' => 100,
                'orderby' => 'date',
                'order' => 'desc'
            ]);
            
            if (empty($subscriptions)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No hay suscripciones fsb_subscription en WooCommerce',
                    'count' => 0
                ]);
                exit;
            }
            
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            // 4. Procesar cada suscripción
            foreach ($subscriptions as $subscription) {
                try {
                    $subscriptionId = $subscription['id'];
                    $customerId = $subscription['customer_id'];
                    $parentOrderId = $subscription['parent_id'] ?? null;
                    
                    // Extraer info del cliente
                    $billing = $subscription['billing'] ?? [];
                    $email = $billing['email'] ?? '';
                    $firstName = $billing['first_name'] ?? '';
                    $lastName = $billing['last_name'] ?? '';
                    $customerName = trim($firstName . ' ' . $lastName);
                    $country = $billing['country'] ?? '';
                    
                    if (empty($email)) {
                        $skipped++;
                        $errors[] = "Suscripción #{$subscriptionId}: Sin email";
                        continue;
                    }
                    
                    // Fechas - buscar en meta_data de Flexible Subscriptions
                    $subscriptionDate = $subscription['date_created'] ?? date('Y-m-d H:i:s');
                    
                    // Extraer meta_data específicos de Flexible Subscriptions
                    $metaData = $subscription['meta_data'] ?? [];
                    $startDate = null;
                    $endDate = null;
                    $billingFrequency = 'P1M'; // Default mensual
                    
                    foreach ($metaData as $meta) {
                        $key = $meta['key'] ?? '';
                        $value = $meta['value'] ?? '';
                        
                        if ($key === '_start_date_utc') {
                            $startDate = $value;
                        } elseif ($key === '_end_date_utc') {
                            $endDate = $value;
                        } elseif ($key === '_billing_frequency') {
                            $billingFrequency = $value;
                        }
                    }
                    
                    // Status
                    $wooStatus = str_replace('wc-', '', $subscription['status']);
                    $apiStatus = mapWooStatus($wooStatus);
                    
                    // Método de pago
                    $paymentMethod = $subscription['payment_method_title'] ?? '';
                    
                    // Moneda
                    $currency = $subscription['currency'] ?? 'EUR';
                    
                    // Buscar producto en line_items
                    $subscriptionProduct = null;
                    $productPrice = 0;
                    $productName = '';
                    
                    foreach ($subscription['line_items'] as $item) {
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
                        continue; // Suscripción sin producto mapeado
                    }
                    
                    $plan = $subscriptionProduct['plan'];
                    $productId = $subscriptionProduct['product_id'];
                    
                    // Determinar ciclo de facturación
                    $billingCycleText = 'Mensual';
                    if (stripos($billingFrequency, 'Y') !== false || stripos($productName, 'anual') !== false) {
                        $billingCycleText = 'Anual';
                    }
                    
                    // Usar fechas de Flexible Subscriptions
                    $periodStarts = $startDate ?: $subscriptionDate;
                    $periodEnds = $endDate ?: date('Y-m-d H:i:s', strtotime($periodStarts . ' +1 month'));
                    
                    // Verificar si ya existe licencia
                    $existing = $db->fetchOne(
                        "SELECT * FROM " . DB_PREFIX . "licenses WHERE woo_subscription_id = ? OR (user_email = ? AND plan_id = ?)",
                        [$subscriptionId, $email, $plan['id']]
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
                                woo_product_id = ?,
                                woo_subscription_id = ?,
                                plan_id = ?,
                                tokens_limit = ?,
                                period_starts_at = ?,
                                period_ends_at = ?,
                                status = ?,
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
                            $subscriptionDate,
                            $parentOrderId,
                            $country,
                            $paymentMethod,
                            $productName,
                            $productId,
                            $subscriptionId,
                            $plan['id'],
                            $plan['tokens_per_month'],
                            $periodStarts,
                            $periodEnds,
                            $apiStatus,
                            $customerId,
                            $existing['id']
                        ]);
                        
                        $db->insert('sync_logs', [
                            'license_id' => $existing['id'],
                            'sync_type' => 'manual',
                            'status' => 'success',
                            'changes_detected' => json_encode([
                                'action' => 'updated',
                                'subscription_id' => $subscriptionId,
                                'product_id' => $productId
                            ]),
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
                            'order_date' => $subscriptionDate,
                            'last_order_id' => $parentOrderId,
                            'customer_country' => $country,
                            'payment_method' => $paymentMethod,
                            'woo_product_name' => $productName,
                            'woo_product_id' => $productId,
                            'woo_subscription_id' => $subscriptionId,
                            'woo_user_id' => $customerId,
                            'plan_id' => $plan['id'],
                            'status' => $apiStatus,
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
                                'subscription_id' => $subscriptionId,
                                'product_id' => $productId,
                                'license_key' => $licenseKey
                            ]),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $created++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Suscripción #{$subscriptionId}: " . $e->getMessage();
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
            
            // 2. Obtener productos completos
            $products = $wc->get('products', ['include' => implode(',', $productIds), 'per_page' => 100]);
            $productNames = [];
            foreach ($products as $prod) {
                $productNames[$prod['id']] = $prod['name'];
            }
            
            // 3. Buscar ÓRDENES (Flexible Subscriptions usa Orders)
            $orders = $wc->get('orders', [
                'status' => 'completed,processing,on-hold',
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
                    
                    // Extraer info del cliente
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
                    
                    // Fechas
                    $orderDate = $order['date_created'] ?? date('Y-m-d H:i:s');
                    
                    // BUSCAR INFO DE SUSCRIPCIÓN EN META_DATA (Flexible Subscriptions)
                    $subscriptionInfo = extractFlexibleSubscriptionInfo($order);
                    
                    // Status
                    $wooStatus = $order['status'];
                    $apiStatus = mapWooStatus($wooStatus);
                    
                    // Método de pago
                    $paymentMethod = $order['payment_method_title'] ?? '';
                    
                    // Moneda
                    $currency = $order['currency'] ?? 'EUR';
                    
                    // Buscar producto en line_items
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
                        continue; // Orden sin producto mapeado
                    }
                    
                    $plan = $subscriptionProduct['plan'];
                    $productId = $subscriptionProduct['product_id'];
                    
                    // Determinar ciclo de facturación
                    $billingCycleText = 'Mensual';
                    if (stripos($productName, 'anual') !== false || stripos($productName, 'año') !== false) {
                        $billingCycleText = 'Anual';
                    }
                    
                    // Calcular período (usar info de suscripción si existe)
                    $periodStarts = $orderDate;
                    $periodEnds = $subscriptionInfo['next_payment'] ?? date('Y-m-d H:i:s', strtotime($orderDate . ' +1 month'));
                    
                    // Verificar si ya existe licencia
                    $existing = $db->fetchOne(
                        "SELECT * FROM " . DB_PREFIX . "licenses WHERE last_order_id = ? OR (user_email = ? AND plan_id = ?)",
                        [$orderId, $email, $plan['id']]
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
                                woo_product_id = ?,
                                woo_subscription_id = ?,
                                plan_id = ?,
                                tokens_limit = ?,
                                period_ends_at = ?,
                                status = ?,
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
                            $productId,
                            $subscriptionInfo['subscription_id'] ?? $orderId,
                            $plan['id'],
                            $plan['tokens_per_month'],
                            $periodEnds,
                            $apiStatus,
                            $customerId,
                            $existing['id']
                        ]);
                        
                        $db->insert('sync_logs', [
                            'license_id' => $existing['id'],
                            'sync_type' => 'manual',
                            'status' => 'success',
                            'changes_detected' => json_encode([
                                'action' => 'updated',
                                'order_id' => $orderId,
                                'product_id' => $productId
                            ]),
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
                            'woo_product_id' => $productId,
                            'woo_subscription_id' => $subscriptionInfo['subscription_id'] ?? $orderId,
                            'woo_user_id' => $customerId,
                            'plan_id' => $plan['id'],
                            'status' => $apiStatus,
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
                                'product_id' => $productId,
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
            
            // 3. Buscar SUSCRIPCIONES activas (no pedidos)
            $subscriptions = $wc->get('subscriptions', [
                'status' => 'active,on-hold,pending-cancel',
                'per_page' => 100,
                'orderby' => 'date',
                'order' => 'desc'
            ]);
            
            if (empty($subscriptions)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No hay suscripciones en WooCommerce',
                    'count' => 0
                ]);
                exit;
            }
            
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            // 4. Procesar cada suscripción
            
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
    
    // NUEVO: Inspeccionar orden específica para ver datos de Flexible Subscriptions
    if ($action === 'inspect_order') {
        try {
            $orderId = $_GET['order_id'] ?? null;
            
            if (!$orderId) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Proporciona ?order_id=71'
                ]);
                exit;
            }
            
            // Obtener orden completa
            $order = $wc->get("orders/{$orderId}");
            
            // Extraer info relevante
            $info = [
                'order_id' => $order['id'],
                'status' => $order['status'],
                'date_created' => $order['date_created'],
                'customer_email' => $order['billing']['email'] ?? '',
                
                // META DATA COMPLETA (aquí está la info de Flexible Subscriptions)
                'meta_data' => $order['meta_data'] ?? [],
                
                // LINE ITEMS CON META DATA
                'line_items' => array_map(function($item) {
                    return [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'total' => $item['total'],
                        'meta_data' => $item['meta_data'] ?? []
                    ];
                }, $order['line_items']),
                
                // ORDEN COMPLETA (para debug)
                '_raw_order' => $order
            ];
            
            echo json_encode([
                'success' => true,
                'data' => $info,
                'message' => 'Orden inspeccionada - Busca campos con "subscription" en meta_data'
            ], JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'sync_plan_prices') {
        try {
            require_once $baseDir . '/models/Plan.php';
            
            $plans = $db->query("SELECT * FROM " . DB_PREFIX . "plans WHERE woo_product_id IS NOT NULL AND woo_product_id > 0 AND is_active = 1");
            
            if (empty($plans)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No hay planes con productos WooCommerce asignados'
                ]);
                exit;
            }
            
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($plans as $plan) {
                try {
                    $product = $wc->get('products/' . $plan['woo_product_id']);
                    $newPrice = floatval($product['price'] ?: $product['regular_price']);
                    
                    if ($plan['price'] != $newPrice) {
                        $db->update('plans', 
                            ['price' => $newPrice, 'updated_at' => date('Y-m-d H:i:s')], 
                            'id = ?', 
                            [$plan['id']]
                        );
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Plan {$plan['id']}: " . $e->getMessage();
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Precios sincronizados: {$updated} actualizados, {$skipped} sin cambios",
                'stats' => [
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

function mapWooStatus($wooStatus) {
    $map = [
        'completed' => 'active',
        'processing' => 'active',
        'active' => 'active',
        'on-hold' => 'suspended',
        'pending' => 'suspended',
        'cancelled' => 'cancelled',
        'expired' => 'expired',
        'failed' => 'suspended',
        'refunded' => 'cancelled',
        'trash' => 'cancelled'
    ];
    
    return $map[$wooStatus] ?? 'suspended';
}

/**
 * Extraer información de suscripción de Flexible Subscriptions
 * Busca en meta_data campos relacionados con la suscripción
 */
function extractFlexibleSubscriptionInfo($order) {
    $info = [
        'subscription_id' => null,
        'next_payment' => null,
        'is_subscription' => false
    ];
    
    // Buscar en meta_data de la orden
    if (isset($order['meta_data']) && is_array($order['meta_data'])) {
        foreach ($order['meta_data'] as $meta) {
            $key = $meta['key'] ?? '';
            $value = $meta['value'] ?? '';
            
            // Buscar ID de suscripción (ajustar según Flexible Subscriptions)
            if (strpos($key, 'subscription') !== false || strpos($key, 'recurring') !== false) {
                $info['is_subscription'] = true;
                
                // Intentar extraer ID de suscripción
                if (is_numeric($value)) {
                    $info['subscription_id'] = intval($value);
                }
            }
            
            // Buscar fecha de próximo pago
            if (strpos($key, 'next_payment') !== false || strpos($key, 'renewal') !== false) {
                $info['next_payment'] = $value;
            }
        }
    }
    
    // Buscar en line_items meta_data
    if (isset($order['line_items']) && is_array($order['line_items'])) {
        foreach ($order['line_items'] as $item) {
            if (isset($item['meta_data']) && is_array($item['meta_data'])) {
                foreach ($item['meta_data'] as $meta) {
                    $key = $meta['key'] ?? '';
                    $value = $meta['value'] ?? '';
                    
                    if (strpos($key, 'subscription') !== false) {
                        $info['is_subscription'] = true;
                    }
                }
            }
        }
    }
    
    return $info;
}
