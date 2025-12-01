<?php
/**
 * AutoSyncService - Sincronización automática con WooCommerce
 *
 * Este servicio verifica periódicamente las suscripciones en WooCommerce
 * y crea/actualiza las licencias correspondientes sin depender de webhooks.
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/WooCommerceClient.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/core/Logger.php';
require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/models/Plan.php';
require_once API_BASE_DIR . '/services/TokenManager.php';
require_once API_BASE_DIR . '/services/LicenseKeySyncService.php';
require_once API_BASE_DIR . '/services/AlertService.php';

class AutoSyncService {
    private $wc;
    private $licenseModel;
    private $planModel;
    private $tokenManager;
    private $db;
    private $licenseSyncService;

    public function __construct() {
        $this->wc = new WooCommerceClient();
        $this->licenseModel = new License();
        $this->planModel = new Plan();
        $this->tokenManager = new TokenManager();
        $this->db = Database::getInstance();
        $this->licenseSyncService = new LicenseKeySyncService();
    }

    /**
     * Ejecutar sincronización completa
     * Obtiene todos los pedidos procesables de WooCommerce y sincroniza licencias
     */
    public function syncAll() {
        $results = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => []
        ];

        try {
            Logger::sync('info', 'Starting auto-sync of all orders');

            // Obtener mapa de productos a planes
            $planMap = $this->getPlanMap();

            if (empty($planMap)) {
                $results['errors']++;
                $results['details'][] = 'No plans configured with WooCommerce product IDs';
                return $results;
            }

            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                // Obtener pedidos procesables (completed/processing)
                $orders = $this->wc->getProcessableOrders($page, 100);

                if (empty($orders)) {
                    $hasMore = false;
                    break;
                }

                foreach ($orders as $order) {
                    $result = $this->processOrder($order, $planMap);

                    $results[$result['action']]++;

                    if ($result['action'] === 'error') {
                        $results['details'][] = $result['message'];
                    }
                }

                // Si recibimos menos de 100, no hay más páginas
                if (count($orders) < 100) {
                    $hasMore = false;
                } else {
                    $page++;
                }
            }

            Logger::sync('info', 'Auto-sync completed', $results);

            // Sincronizar license_keys pendientes a WooCommerce
            $this->syncPendingLicenseKeys($results);

            // Verificar y alertar sobre licencias con problemas
            AlertService::checkAndAlertPendingLicenses();

        } catch (Exception $e) {
            Logger::sync('error', 'Auto-sync failed', ['error' => $e->getMessage()]);
            $results['errors']++;
            $results['details'][] = $e->getMessage();

            // Enviar alerta de error crítico
            AlertService::autoSyncFailed([
                'type' => 'full_sync',
                'message' => $e->getMessage(),
                'context' => [
                    'trace' => $e->getTraceAsString()
                ]
            ]);
        }

        return $results;
    }

    /**
     * Sincronizar solo pedidos recientes (últimas X horas)
     * Ideal para cron jobs frecuentes
     */
    public function syncRecent($hours = 2) {
        $results = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => []
        ];

        try {
            $since = date('c', strtotime("-{$hours} hours"));
            Logger::sync('info', "Starting auto-sync of orders modified since {$since}");

            // Obtener mapa de productos a planes
            $planMap = $this->getPlanMap();

            Logger::sync('info', 'Plan map loaded', [
                'total_plans' => count($planMap),
                'product_ids' => array_keys($planMap)
            ]);

            if (empty($planMap)) {
                $results['errors']++;
                $results['details'][] = 'No plans configured with WooCommerce product IDs';
                Logger::sync('error', 'No plans configured with WooCommerce product IDs - auto-sync aborted');
                return $results;
            }

            $page = 1;
            $hasMore = true;
            $totalOrdersFetched = 0;

            while ($hasMore) {
                Logger::sync('info', "Fetching orders from WooCommerce", [
                    'page' => $page,
                    'since' => $since
                ]);

                $orders = $this->wc->getOrdersModifiedAfter($since, $page, 100);

                Logger::sync('info', "Orders fetched from WooCommerce", [
                    'page' => $page,
                    'count' => count($orders)
                ]);

                if (empty($orders)) {
                    $hasMore = false;
                    break;
                }

                $totalOrdersFetched += count($orders);

                foreach ($orders as $order) {
                    $orderId = $order['id'];
                    $orderStatus = $order['status'];
                    $orderProducts = array_column($order['line_items'] ?? [], 'product_id');

                    Logger::sync('info', "Processing order from WooCommerce", [
                        'order_id' => $orderId,
                        'status' => $orderStatus,
                        'product_ids' => $orderProducts
                    ]);

                    $result = $this->processOrder($order, $planMap);
                    $results[$result['action']]++;

                    Logger::sync('info', "Order processed", [
                        'order_id' => $orderId,
                        'action' => $result['action'],
                        'message' => $result['message'] ?? ''
                    ]);

                    if ($result['action'] === 'error') {
                        $results['details'][] = $result['message'];
                    }
                }

                if (count($orders) < 100) {
                    $hasMore = false;
                } else {
                    $page++;
                }
            }

            Logger::sync('info', 'Recent auto-sync completed', array_merge($results, [
                'total_orders_fetched' => $totalOrdersFetched
            ]));

            // Sincronizar license_keys pendientes a WooCommerce
            $this->syncPendingLicenseKeys($results);

            // Verificar y alertar sobre licencias con problemas
            AlertService::checkAndAlertPendingLicenses();

        } catch (Exception $e) {
            Logger::sync('error', 'Recent auto-sync failed', ['error' => $e->getMessage()]);
            $results['errors']++;
            $results['details'][] = $e->getMessage();

            // Enviar alerta de error crítico
            AlertService::autoSyncFailed([
                'type' => 'recent_sync',
                'message' => $e->getMessage(),
                'context' => [
                    'trace' => $e->getTraceAsString()
                ]
            ]);
        }

        return $results;
    }

    /**
     * Procesar un pedido individual y crear/actualizar licencia
     */
    private function processOrder($order, $planMap) {
        $orderId = $order['id'];

        try {
            // Obtener info del cliente
            $billing = $order['billing'] ?? [];
            $email = $billing['email'] ?? '';

            if (empty($email)) {
                return [
                    'action' => 'skipped',
                    'message' => "Order {$orderId} has no email"
                ];
            }

            $customerId = $order['customer_id'] ?? 0;
            $firstName = $billing['first_name'] ?? '';
            $lastName = $billing['last_name'] ?? '';
            $customerName = trim($firstName . ' ' . $lastName);
            $country = $billing['country'] ?? '';

            // Buscar productos que coincidan con planes
            $lineItems = $order['line_items'] ?? [];
            $processed = false;

            foreach ($lineItems as $item) {
                $productId = $item['product_id'];

                if (!isset($planMap[$productId])) {
                    continue; // Este producto no es un plan
                }

                $plan = $planMap[$productId];
                $productPrice = floatval($item['total'] ?? 0);
                $productName = $item['name'] ?? '';

                // Determinar ciclo de facturación
                $billingCycleText = 'Mensual';
                if (stripos($productName, 'anual') !== false || stripos($productName, 'año') !== false || stripos($productName, 'yearly') !== false) {
                    $billingCycleText = 'Anual';
                }

                // Calcular período
                $orderDate = $order['date_created'] ?? date(DATE_FORMAT);
                $periodEnds = date(DATE_FORMAT, strtotime($orderDate . ' +1 month'));

                // Verificar si ya existe licencia para ESTE PEDIDO ESPECÍFICO
                // Importante: NO buscar por email+plan porque un usuario puede tener múltiples pedidos
                $existing = $this->db->fetchOne(
                    "SELECT * FROM " . DB_PREFIX . "licenses
                     WHERE (last_order_id = ? OR woo_subscription_id = ?)",
                    [$orderId, $orderId]
                );

                Logger::sync('info', 'Checking for existing license', [
                    'order_id' => $orderId,
                    'email' => $email,
                    'plan_id' => $plan['id'],
                    'existing_found' => $existing ? 'yes' : 'no',
                    'existing_license_key' => $existing ? $existing['license_key'] : null
                ]);

                if ($existing) {
                    // Ya existe licencia para este pedido - actualizar solo si hay cambios
                    $hasChanges = false;

                    if ($existing['tokens_limit'] != $plan['tokens_per_month']) {
                        $hasChanges = true;
                    }
                    if ($existing['status'] !== 'active') {
                        $hasChanges = true;
                    }
                    if ($existing['subscription_price'] != $productPrice) {
                        $hasChanges = true;
                    }

                    if (!$hasChanges) {
                        // Solo actualizar last_synced_at
                        $this->db->query("
                            UPDATE " . DB_PREFIX . "licenses
                            SET last_synced_at = NOW()
                            WHERE id = ?
                        ", [$existing['id']]);

                        $processed = true;
                        return [
                            'action' => 'unchanged',
                            'message' => "No changes for license {$existing['license_key']} (order {$orderId})"
                        ];
                    }

                    // Actualizar licencia existente
                    $this->db->query("
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
                            status = 'active',
                            last_synced_at = NOW(),
                            sync_status = 'fresh',
                            updated_at = NOW()
                        WHERE id = ?
                    ", [
                        $customerName,
                        $productPrice,
                        $order['currency'] ?? 'EUR',
                        $billingCycleText,
                        $orderDate,
                        $orderId,
                        $country,
                        $order['payment_method_title'] ?? '',
                        $productName,
                        $plan['tokens_per_month'],
                        $periodEnds,
                        $customerId,
                        $existing['id']
                    ]);

                    Logger::sync('info', 'License updated via auto-sync', [
                        'license_id' => $existing['id'],
                        'license_key' => $existing['license_key'],
                        'order_id' => $orderId
                    ]);

                    // Registrar en sync_logs
                    $this->logSyncEvent($existing['id'], 'auto_sync', 'success', 'Licencia actualizada desde orden ' . $orderId);

                    $processed = true;
                    return [
                        'action' => 'updated',
                        'message' => "Updated license {$existing['license_key']} from order {$orderId}"
                    ];

                } else {
                    // Crear nueva licencia para este pedido
                    // Nota: Un usuario puede tener múltiples licencias (renovaciones, múltiples pedidos, etc.)
                    $licenseKey = $this->generateLicenseKey($plan['id']);

                    Logger::sync('info', 'Creating new license for order', [
                        'order_id' => $orderId,
                        'email' => $email,
                        'plan_id' => $plan['id'],
                        'new_license_key' => $licenseKey
                    ]);

                    $this->db->insert('licenses', [
                        'license_key' => $licenseKey,
                        'user_email' => $email,
                        'customer_name' => $customerName,
                        'subscription_price' => $productPrice,
                        'currency' => $order['currency'] ?? 'EUR',
                        'billing_cycle_text' => $billingCycleText,
                        'order_date' => $orderDate,
                        'last_order_id' => $orderId,
                        'customer_country' => $country,
                        'payment_method' => $order['payment_method_title'] ?? '',
                        'woo_product_name' => $productName,
                        'woo_subscription_id' => $orderId,
                        'woo_user_id' => $customerId,
                        'plan_id' => $plan['id'],
                        'status' => 'active',
                        'domain' => '',
                        'tokens_limit' => $plan['tokens_per_month'],
                        'tokens_used_this_period' => 0,
                        'period_starts_at' => $orderDate,
                        'period_ends_at' => $periodEnds,
                        'last_synced_at' => date(DATE_FORMAT),
                        'sync_status' => 'fresh',
                        'created_at' => date(DATE_FORMAT),
                        'updated_at' => date(DATE_FORMAT)
                    ]);

                    Logger::sync('info', 'License created via auto-sync', [
                        'license_key' => $licenseKey,
                        'order_id' => $orderId,
                        'email' => $email,
                        'plan_id' => $plan['id'],
                        'customer_name' => $customerName
                    ]);

                    // Obtener el ID de la licencia recién creada y registrar en sync_logs
                    $newLicenseId = $this->db->lastInsertId();
                    $this->logSyncEvent($newLicenseId, 'auto_sync', 'success', 'Licencia creada desde orden ' . $orderId);

                    $processed = true;
                    return [
                        'action' => 'created',
                        'message' => "Created license {$licenseKey} for order {$orderId}"
                    ];
                }
            }

            if (!$processed) {
                return [
                    'action' => 'skipped',
                    'message' => "Order {$orderId} has no products matching plans"
                ];
            }

        } catch (Exception $e) {
            return [
                'action' => 'error',
                'message' => "Error processing order {$orderId}: " . $e->getMessage()
            ];
        }

        return [
            'action' => 'skipped',
            'message' => "Order {$orderId} not processed"
        ];
    }

    /**
     * Obtener mapa de productos a planes
     */
    private function getPlanMap() {
        $plans = $this->db->query("
            SELECT id, woo_product_id, tokens_per_month
            FROM " . DB_PREFIX . "plans
            WHERE woo_product_id IS NOT NULL AND woo_product_id > 0
        ");

        $planMap = [];
        foreach ($plans as $plan) {
            $planMap[$plan['woo_product_id']] = $plan;
        }

        return $planMap;
    }

    /**
     * Generar license key
     */
    private function generateLicenseKey($planId) {
        $prefix = strtoupper(substr($planId, 0, 4));
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * Registrar evento de sincronización en la tabla sync_logs
     */
    private function logSyncEvent($licenseId, $syncType, $status, $details = null) {
        try {
            $this->db->insert('sync_logs', [
                'license_id' => $licenseId,
                'sync_type' => $syncType,
                'status' => $status,
                'details' => $details,
                'created_at' => date(DATE_FORMAT)
            ]);
        } catch (Exception $e) {
            // No fallar si el log falla
            Logger::sync('warning', 'Failed to log sync event', [
                'license_id' => $licenseId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Limpiar logs antiguos de la base de datos y archivos
     * Se ejecuta automáticamente con el cron
     */
    public function cleanOldLogs($dbDays = 30, $fileDays = 7, $maxFileSizeMB = 10) {
        $cleaned = [
            'db_deleted' => 0,
            'files_cleaned' => [],
            'files_truncated' => [],
            'backups_deleted' => 0
        ];

        try {
            // 1. Limpiar sync_logs de la base de datos (mantener últimos X días)
            $this->db->query("
                DELETE FROM " . DB_PREFIX . "sync_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$dbDays]);

            // Contar registros eliminados usando una consulta separada
            $result = $this->db->fetchOne("SELECT ROW_COUNT() as deleted");
            $cleaned['db_deleted'] = $result['deleted'] ?? 0;

            // 2. Limpiar TODOS los archivos de log
            $logsDir = API_BASE_DIR . '/logs';
            if (is_dir($logsDir)) {
                // Incluir todos los tipos de logs: .log y .bak
                $logFiles = array_merge(
                    glob($logsDir . '/*.log') ?: [],
                    glob($logsDir . '/*.bak') ?: []
                );

                $maxSizeBytes = $maxFileSizeMB * 1024 * 1024;
                $cutoffTime = strtotime("-{$fileDays} days");

                foreach ($logFiles as $logFile) {
                    $fileName = basename($logFile);
                    $fileSize = filesize($logFile);
                    $fileTime = filemtime($logFile);

                    // Eliminar archivos .bak antiguos
                    if (preg_match('/\.bak$/', $fileName)) {
                        if ($fileTime < $cutoffTime) {
                            unlink($logFile);
                            $cleaned['backups_deleted']++;
                        }
                        continue;
                    }

                    // Procesar archivos .log
                    if ($fileSize > $maxSizeBytes) {
                        // Si es muy grande, truncar a las últimas 1000 líneas
                        $lines = file($logFile);
                        if ($lines !== false && count($lines) > 1000) {
                            $lastLines = array_slice($lines, -1000);
                            file_put_contents($logFile, implode('', $lastLines));
                            $cleaned['files_truncated'][] = $fileName . ' (' . round($fileSize / 1024 / 1024, 2) . 'MB → truncado)';
                        }
                    } elseif ($fileTime < $cutoffTime) {
                        // Si es antiguo (más de X días sin modificar), eliminar
                        unlink($logFile);
                        $cleaned['files_cleaned'][] = $fileName;
                    }
                }
            }

            if ($cleaned['db_deleted'] > 0 || !empty($cleaned['files_cleaned']) || !empty($cleaned['files_truncated'])) {
                Logger::sync('info', 'Logs cleaned', $cleaned);
            }

        } catch (Exception $e) {
            Logger::sync('error', 'Failed to clean old logs', [
                'error' => $e->getMessage()
            ]);
        }

        return $cleaned;
    }

    /**
     * Limpiar TODOS los logs inmediatamente (para limpieza manual)
     */
    public function cleanAllLogsNow() {
        $cleaned = [
            'db_deleted' => 0,
            'files_deleted' => [],
            'files_truncated' => []
        ];

        try {
            // 1. Vaciar tabla sync_logs completamente
            $this->db->query("DELETE FROM " . DB_PREFIX . "sync_logs");
            $result = $this->db->fetchOne("SELECT ROW_COUNT() as deleted");
            $cleaned['db_deleted'] = $result['deleted'] ?? 0;

            // 2. Limpiar/eliminar TODOS los archivos de log
            $logsDir = API_BASE_DIR . '/logs';
            if (is_dir($logsDir)) {
                $allFiles = array_merge(
                    glob($logsDir . '/*.log') ?: [],
                    glob($logsDir . '/*.bak') ?: [],
                    glob($logsDir . '/*.json') ?: []
                );

                foreach ($allFiles as $file) {
                    $fileName = basename($file);

                    // No eliminar cron_status.json, solo truncar logs
                    if ($fileName === 'cron_status.json') {
                        continue;
                    }

                    if (preg_match('/\.(bak|json)$/', $fileName)) {
                        // Eliminar backups y otros archivos json
                        unlink($file);
                        $cleaned['files_deleted'][] = $fileName;
                    } else {
                        // Truncar archivos .log (vaciar pero mantener el archivo)
                        file_put_contents($file, '');
                        $cleaned['files_truncated'][] = $fileName;
                    }
                }
            }

            Logger::sync('info', 'All logs cleaned manually', $cleaned);

        } catch (Exception $e) {
            Logger::sync('error', 'Failed to clean all logs', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $cleaned;
    }

    /**
     * Obtener estadísticas de sincronización
     */
    public function getStats() {
        $stats = [];

        // Total licencias
        $result = $this->db->fetchOne("SELECT COUNT(*) as total FROM " . DB_PREFIX . "licenses");
        $stats['total_licenses'] = $result['total'] ?? 0;

        // Licencias sincronizadas en las últimas 24h
        $result = $this->db->fetchOne("
            SELECT COUNT(*) as total
            FROM " . DB_PREFIX . "licenses
            WHERE last_synced_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['synced_last_24h'] = $result['total'] ?? 0;

        // Licencias sin sincronizar (más de 48h)
        $result = $this->db->fetchOne("
            SELECT COUNT(*) as total
            FROM " . DB_PREFIX . "licenses
            WHERE last_synced_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
            OR last_synced_at IS NULL
        ");
        $stats['stale_licenses'] = $result['total'] ?? 0;

        // Última sincronización
        $result = $this->db->fetchOne("
            SELECT MAX(last_synced_at) as last_sync
            FROM " . DB_PREFIX . "licenses
        ");
        $stats['last_sync'] = $result['last_sync'] ?? 'Never';

        return $stats;
    }

    /**
     * Guardar estado de ejecución del cron
     */
    public function saveCronStatus($results, $syncType = 'recent', $source = 'cron') {
        $statusFile = API_BASE_DIR . '/logs/cron_status.json';

        $status = [
            'last_run' => date(DATE_FORMAT),
            'sync_type' => $syncType,
            'source' => $source,
            'success' => ($results['errors'] == 0),
            'results' => [
                'created' => $results['created'],
                'updated' => $results['updated'],
                'unchanged' => $results['unchanged'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors']
            ],
            'details' => $results['details'] ?? []
        ];

        // Crear directorio logs si no existe
        $logsDir = dirname($statusFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));

        // Registrar resumen en sync_logs para las tarjetas del panel
        $this->logCronSummary($results, $syncType, $source);

        return $status;
    }

    /**
     * Registrar resumen del cron en sync_logs
     */
    private function logCronSummary($results, $syncType, $source = 'cron') {
        try {
            // Determinar etiqueta según origen
            $sourceLabel = ($source === 'manual') ? 'Manual' : 'Cron';
            $typeLabel = ($syncType === 'full') ? 'completo' : '2h';

            $details = sprintf(
                '%s %s: %d creadas, %d actualizadas, %d sin cambios, %d errores',
                $sourceLabel,
                $typeLabel,
                $results['created'],
                $results['updated'],
                $results['unchanged'],
                $results['errors']
            );

            $status = ($results['errors'] == 0) ? 'success' : 'failed';

            // Construir sync_type: cron_recent, cron_full, manual_recent, manual_full
            $logSyncType = $source . '_' . $syncType;

            Logger::sync('info', 'Inserting sync summary to sync_logs', [
                'sync_type_value' => $logSyncType,
                'source' => $source,
                'syncType' => $syncType,
                'status' => $status,
                'details' => $details
            ]);

            // Usar insert() de Database con todas las columnas
            $this->db->insert('sync_logs', [
                'license_id' => null,
                'sync_type' => $logSyncType,
                'status' => $status,
                'changes_detected' => json_encode([
                    'source' => $source,
                    'type' => $syncType,
                    'created' => $results['created'],
                    'updated' => $results['updated'],
                    'unchanged' => $results['unchanged'],
                    'errors' => $results['errors']
                ]),
                'details' => $details,
                'duration_ms' => 0,
                'created_at' => date(DATE_FORMAT)
            ]);

            Logger::sync('info', 'Cron summary inserted successfully with sync_type: ' . $logSyncType);

        } catch (Exception $e) {
            Logger::sync('error', 'Failed to log cron summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Sincronizar license_keys pendientes a WooCommerce
     * Se ejecuta automáticamente después de cada sync de licencias
     */
    private function syncPendingLicenseKeys(&$results) {
        try {
            $syncResults = $this->licenseSyncService->syncPendingLicenseKeys(50);

            // Añadir a los resultados generales
            if ($syncResults['synced'] > 0) {
                $results['details'][] = "License keys synced to WooCommerce: {$syncResults['synced']}";
            }

            if ($syncResults['failed'] > 0) {
                $results['details'][] = "License keys sync failed: {$syncResults['failed']} (will retry)";
            }

            Logger::sync('info', 'License keys sync completed', $syncResults);

        } catch (Exception $e) {
            Logger::sync('error', 'License keys sync failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener estado de la última ejecución del cron
     */
    public static function getCronStatus() {
        $statusFile = API_BASE_DIR . '/logs/cron_status.json';

        if (!file_exists($statusFile)) {
            return [
                'last_run' => null,
                'status' => 'never',
                'message' => 'El cron nunca se ha ejecutado'
            ];
        }

        $status = json_decode(file_get_contents($statusFile), true);

        if (!$status || !isset($status['last_run'])) {
            return [
                'last_run' => null,
                'status' => 'error',
                'message' => 'Error leyendo estado del cron'
            ];
        }

        // Calcular tiempo desde última ejecución
        $lastRun = strtotime($status['last_run']);
        $now = time();
        $diffMinutes = round(($now - $lastRun) / 60);

        // Determinar estado visual
        if ($diffMinutes <= 10) {
            $visualStatus = 'ok';
            $statusMessage = "Funcionando correctamente";
        } elseif ($diffMinutes <= 30) {
            $visualStatus = 'warning';
            $statusMessage = "Última ejecución hace {$diffMinutes} min";
        } else {
            $visualStatus = 'error';
            $statusMessage = "Sin ejecutar hace {$diffMinutes} min - Verificar cron";
        }

        return [
            'last_run' => $status['last_run'],
            'last_run_relative' => $diffMinutes . ' min',
            'sync_type' => $status['sync_type'] ?? 'unknown',
            'success' => $status['success'] ?? false,
            'results' => $status['results'] ?? [],
            'details' => $status['details'] ?? [],
            'status' => $visualStatus,
            'message' => $statusMessage
        ];
    }
}
