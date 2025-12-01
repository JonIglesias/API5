<?php
/**
 * WebhookHandler - Procesar webhooks de WooCommerce
 *
 * Soporta tanto eventos de suscripciones (WooCommerce Subscriptions)
 * como eventos de pedidos (Flexible Subscriptions y otros)
 *
 * @version 4.1
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/models/Plan.php';
require_once API_BASE_DIR . '/services/TokenManager.php';
require_once API_BASE_DIR . '/core/WooCommerceClient.php';
require_once API_BASE_DIR . '/services/LicenseKeySyncService.php';

class WebhookHandler {
    private $licenseModel;
    private $planModel;
    private $tokenManager;
    private $db;
    private $wc;
    private $licenseSyncService;

    public function __construct() {
        $this->licenseModel = new License();
        $this->planModel = new Plan();
        $this->tokenManager = new TokenManager();
        $this->db = Database::getInstance();
        $this->wc = new WooCommerceClient();
        $this->licenseSyncService = new LicenseKeySyncService();
    }
    
    /**
     * Manejar webhook entrante
     */
    public function handle() {
        // Obtener IP real (considerando proxies/CDN)
        $clientIp = $this->getClientIp();

        // Log detallado de la petición entrante
        Logger::webhook('info', 'Webhook request received', [
            'client_ip' => $clientIp,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'none',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
        ]);

        // Obtener payload
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        // Validar origen
        if (!$this->validateOrigin($clientIp)) {
            Logger::webhook('warning', 'Webhook from unauthorized IP', [
                'ip' => $clientIp,
                'all_ips' => $this->getAllIps()
            ]);
            Response::error('Unauthorized', 403);
        }
        
        // Obtener evento
        $event = $_SERVER['HTTP_X_WC_WEBHOOK_TOPIC'] ?? '';

        // Si no hay topic, es un ping de WooCommerce (al configurar/probar el webhook)
        if (!$event) {
            Logger::webhook('info', 'Webhook ping received (no topic)', [
                'resource' => $_SERVER['HTTP_X_WC_WEBHOOK_RESOURCE'] ?? 'none',
                'has_data' => !empty($data),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ]);
            // Responder con éxito para que WooCommerce valide el endpoint
            Response::success(['message' => 'Webhook endpoint active']);
        }
        
        // Log del webhook
        $this->logWebhook($event, $data);
        
        Logger::webhook('info', 'Webhook received', [
            'event' => $event,
            'subscription_id' => $data['id'] ?? null
        ]);
        
        // Procesar según tipo de evento
        try {
            switch ($event) {
                // ========== EVENTOS DE SUSCRIPCIONES (WooCommerce Subscriptions) ==========
                case 'subscription.created':
                    $this->handleSubscriptionCreated($data);
                    break;

                case 'subscription.renewed':
                    $this->handleSubscriptionRenewed($data);
                    break;

                case 'subscription.updated':
                    $this->handleSubscriptionUpdated($data);
                    break;

                case 'subscription.switched':
                    $this->handleSubscriptionSwitched($data);
                    break;

                case 'subscription.cancelled':
                case 'subscription.expired':
                    $this->handleSubscriptionEnded($data);
                    break;

                // ========== EVENTOS DE PEDIDOS (Flexible Subscriptions y otros) ==========
                case 'order.created':
                case 'order.updated':
                    $this->handleOrderCreated($data);
                    break;

                case 'order.completed':
                    $this->handleOrderCompleted($data);
                    break;

                default:
                    Logger::webhook('info', 'Unhandled webhook event', ['event' => $event]);
            }
            
            Response::success(['message' => 'Webhook processed']);
            
        } catch (Exception $e) {
            Logger::webhook('error', 'Webhook processing failed', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);
            
            Response::error('Processing failed', 500);
        }
    }
    
    /**
     * Suscripción creada
     */
    private function handleSubscriptionCreated($data) {
        $subscriptionId = $data['id'];
        
        // Verificar si ya existe
        $existing = $this->licenseModel->findBySubscriptionId($subscriptionId);
        if ($existing) {
            return; // Ya existe
        }
        
        // Obtener plan
        $productId = $data['line_items'][0]['product_id'] ?? null;
        $plan = $this->planModel->findByWooProductId($productId);
        
        if (!$plan) {
            throw new Exception('Plan not found for product ID: ' . $productId);
        }
        
        // Crear licencia
        $licenseData = [
            'woo_subscription_id' => $subscriptionId,
            'woo_user_id' => $data['customer_id'],
            'plan_id' => $plan['id'],
            'status' => $this->mapWooStatus($data['status']),
            'tokens_limit' => $plan['tokens_per_month'],
            'tokens_used_this_period' => 0,
            'period_starts_at' => date(DATE_FORMAT),
            'period_ends_at' => date(DATE_FORMAT, strtotime($data['next_payment_date'] ?? '+1 month')),
            'last_synced_at' => date(DATE_FORMAT)
        ];
        
        $this->licenseModel->create($licenseData);
        
        Logger::webhook('info', 'License created from webhook', [
            'subscription_id' => $subscriptionId
        ]);
    }
    
    /**
     * Suscripción renovada
     */
    private function handleSubscriptionRenewed($data) {
        $subscriptionId = $data['id'];
        $license = $this->licenseModel->findBySubscriptionId($subscriptionId);
        
        if (!$license) {
            throw new Exception('License not found for subscription: ' . $subscriptionId);
        }
        
        // Resetear tokens
        $this->tokenManager->resetPeriod($license['id']);
        
        // Actualizar fecha de renovación
        $this->licenseModel->update($license['id'], [
            'period_ends_at' => date(DATE_FORMAT, strtotime($data['next_payment_date'] ?? '+1 month')),
            'status' => 'active',
            'last_synced_at' => date(DATE_FORMAT)
        ]);
        
        Logger::webhook('info', 'License renewed', [
            'license_id' => $license['id'],
            'subscription_id' => $subscriptionId
        ]);
    }
    
    /**
     * Suscripción actualizada
     */
    private function handleSubscriptionUpdated($data) {
        $subscriptionId = $data['id'];
        $license = $this->licenseModel->findBySubscriptionId($subscriptionId);
        
        if (!$license) {
            return;
        }
        
        // Actualizar datos básicos
        $updateData = [
            'status' => $this->mapWooStatus($data['status']),
            'last_synced_at' => date(DATE_FORMAT)
        ];
        
        if (isset($data['next_payment_date'])) {
            $updateData['period_ends_at'] = date(DATE_FORMAT, strtotime($data['next_payment_date']));
        }
        
        $this->licenseModel->update($license['id'], $updateData);
    }
    
    /**
     * Cambio de plan (upgrade/downgrade)
     */
    private function handleSubscriptionSwitched($data) {
        $subscriptionId = $data['id'];
        $license = $this->licenseModel->findBySubscriptionId($subscriptionId);
        
        if (!$license) {
            return;
        }
        
        // Obtener nuevo plan
        $productId = $data['line_items'][0]['product_id'] ?? null;
        $newPlan = $this->planModel->findByWooProductId($productId);
        
        if (!$newPlan) {
            throw new Exception('New plan not found for product ID: ' . $productId);
        }
        
        // Actualizar plan y límite de tokens
        $this->licenseModel->update($license['id'], [
            'plan_id' => $newPlan['id'],
            'tokens_limit' => $newPlan['tokens_per_month'],
            'last_synced_at' => date(DATE_FORMAT)
        ]);
        
        Logger::webhook('info', 'License plan switched', [
            'license_id' => $license['id'],
            'old_plan' => $license['plan_id'],
            'new_plan' => $newPlan['id']
        ]);
    }
    
    /**
     * Suscripción cancelada o expirada
     */
    private function handleSubscriptionEnded($data) {
        $subscriptionId = $data['id'];
        $license = $this->licenseModel->findBySubscriptionId($subscriptionId);
        
        if (!$license) {
            return;
        }
        
        $newStatus = ($data['status'] === 'cancelled') ? 'cancelled' : 'expired';
        
        $this->licenseModel->update($license['id'], [
            'status' => $newStatus,
            'last_synced_at' => date(DATE_FORMAT)
        ]);
        
        Logger::webhook('info', 'License ended', [
            'license_id' => $license['id'],
            'status' => $newStatus
        ]);
    }
    
    // ========== HANDLERS DE PEDIDOS (Flexible Subscriptions) ==========

    /**
     * Pedido creado o actualizado
     * Solo procesa si el estado es processing o completed
     */
    private function handleOrderCreated($data) {
        $orderId = $data['id'];
        $status = $data['status'] ?? '';

        // Solo procesar pedidos en processing o completed
        if (!in_array($status, ['processing', 'completed'])) {
            Logger::webhook('info', 'Order skipped - status not processable', [
                'order_id' => $orderId,
                'status' => $status
            ]);
            return;
        }

        $this->processOrder($data);
    }

    /**
     * Pedido completado
     */
    private function handleOrderCompleted($data) {
        $this->processOrder($data);
    }

    /**
     * Procesar un pedido y crear/actualizar licencia
     */
    private function processOrder($data) {
        $orderId = $data['id'];
        $customerId = $data['customer_id'] ?? 0;

        // Obtener info del cliente
        $billing = $data['billing'] ?? [];
        $email = $billing['email'] ?? '';
        $firstName = $billing['first_name'] ?? '';
        $lastName = $billing['last_name'] ?? '';
        $customerName = trim($firstName . ' ' . $lastName);
        $country = $billing['country'] ?? '';

        if (empty($email)) {
            Logger::webhook('warning', 'Order skipped - no email', ['order_id' => $orderId]);
            return;
        }

        // Buscar productos que coincidan con planes
        $lineItems = $data['line_items'] ?? [];
        $planMap = $this->getPlanMap();

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
            $orderDate = $data['date_created'] ?? date(DATE_FORMAT);
            $periodEnds = date(DATE_FORMAT, strtotime($orderDate . ' +1 month'));

            // Verificar si ya existe licencia para ESTE PEDIDO ESPECÍFICO
            // Importante: NO buscar por email+plan porque un usuario puede tener múltiples pedidos
            $existing = $this->db->fetchOne(
                "SELECT * FROM " . DB_PREFIX . "licenses WHERE (last_order_id = ? OR woo_subscription_id = ?)",
                [$orderId, $orderId]
            );

            if ($existing) {
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
                    $data['currency'] ?? 'EUR',
                    $billingCycleText,
                    $orderDate,
                    $orderId,
                    $country,
                    $data['payment_method_title'] ?? '',
                    $productName,
                    $plan['tokens_per_month'],
                    $periodEnds,
                    $customerId,
                    $existing['id']
                ]);

                Logger::webhook('info', 'License updated from order', [
                    'license_id' => $existing['id'],
                    'order_id' => $orderId
                ]);

                // Intentar sincronizar license_key a WooCommerce (con reintentos automáticos)
                $syncResult = $this->licenseSyncService->syncLicenseKey($existing['id']);

                if ($syncResult['success']) {
                    Logger::webhook('info', 'License key synced to WooCommerce', [
                        'license_id' => $existing['id'],
                        'order_id' => $orderId,
                        'attempts' => $syncResult['attempts'] ?? 1
                    ]);
                } else {
                    Logger::webhook('warning', 'License key sync failed, will retry later', [
                        'license_id' => $existing['id'],
                        'order_id' => $orderId,
                        'message' => $syncResult['message'] ?? 'Unknown error',
                        'will_retry' => $syncResult['will_retry'] ?? false
                    ]);
                }

            } else {
                // Crear nueva licencia
                $licenseKey = $this->generateLicenseKey($orderId, $plan['id']);

                $this->db->insert('licenses', [
                    'license_key' => $licenseKey,
                    'user_email' => $email,
                    'customer_name' => $customerName,
                    'subscription_price' => $productPrice,
                    'currency' => $data['currency'] ?? 'EUR',
                    'billing_cycle_text' => $billingCycleText,
                    'order_date' => $orderDate,
                    'last_order_id' => $orderId,
                    'customer_country' => $country,
                    'payment_method' => $data['payment_method_title'] ?? '',
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

                $newLicenseId = $this->db->lastInsertId();

                Logger::webhook('info', 'License created from order', [
                    'license_id' => $newLicenseId,
                    'license_key' => $licenseKey,
                    'order_id' => $orderId,
                    'email' => $email,
                    'plan' => $plan['id']
                ]);

                // Intentar sincronizar license_key a WooCommerce (con reintentos automáticos)
                $syncResult = $this->licenseSyncService->syncLicenseKey($newLicenseId);

                if ($syncResult['success']) {
                    Logger::webhook('info', 'License key synced to WooCommerce', [
                        'license_id' => $newLicenseId,
                        'order_id' => $orderId,
                        'license_key' => $licenseKey,
                        'attempts' => $syncResult['attempts'] ?? 1
                    ]);
                } else {
                    Logger::webhook('warning', 'License key sync failed, will retry later', [
                        'license_id' => $newLicenseId,
                        'order_id' => $orderId,
                        'license_key' => $licenseKey,
                        'message' => $syncResult['message'] ?? 'Unknown error',
                        'will_retry' => $syncResult['will_retry'] ?? false
                    ]);
                }
            }
        }
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
     * Formato: {order_id}-{plan_id}-{year}-{random}
     * Ejemplo: 628-10-2025-83772E2C
     */
    private function generateLicenseKey($orderId, $planId) {
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        return "{$orderId}-{$planId}-{$year}-{$random}";
    }

    // ========== HELPERS ==========

    /**
     * Mapear estado de WooCommerce
     */
    private function mapWooStatus($wooStatus) {
        $map = [
            'active' => 'active',
            'on-hold' => 'suspended',
            'pending' => 'suspended',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            'pending-cancel' => 'active'
        ];

        return $map[$wooStatus] ?? 'suspended';
    }
    
    /**
     * Obtener IP real del cliente (considerando proxies/CDN)
     */
    private function getClientIp() {
        // Orden de prioridad para detectar IP real
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy
            'REMOTE_ADDR'                // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For puede tener múltiples IPs separadas por coma
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]); // Primera IP es el cliente real
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * Obtener todas las IPs disponibles (para logging)
     */
    private function getAllIps() {
        return [
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'x_real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
            'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null
        ];
    }

    /**
     * Validar origen del webhook
     */
    private function validateOrigin($clientIp = null) {
        $ip = $clientIp ?? $this->getClientIp();

        // En desarrollo, permitir localhost
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return true;
        }

        // Verificar whitelist si está configurada
        if (defined('WEBHOOK_ALLOWED_IPS') && is_array(WEBHOOK_ALLOWED_IPS)) {
            return in_array($ip, WEBHOOK_ALLOWED_IPS);
        }

        // Por defecto, permitir todas las IPs (el cron actúa como backup)
        return true;
    }
    
    /**
     * Guardar log de webhook
     */
    private function logWebhook($event, $data) {
        $db = Database::getInstance();
        
        $db->insert('webhook_logs', [
            'event_type' => $event,
            'woo_subscription_id' => $data['id'] ?? null,
            'payload' => json_encode($data),
            'processed' => 1,
            'received_at' => date(DATE_FORMAT)
        ]);
    }
}
