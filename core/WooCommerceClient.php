<?php
/**
 * WooCommerce API Client
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class WooCommerceClient {
    private $apiUrl;
    private $consumerKey;
    private $consumerSecret;
    
    public function __construct() {
        $this->apiUrl = rtrim(WC_API_URL, '/');
        $this->consumerKey = WC_CONSUMER_KEY;
        $this->consumerSecret = WC_CONSUMER_SECRET;
    }
    
    /**
     * Realizar petición GET
     */
    public function get($endpoint, $params = []) {
        return $this->request('GET', $endpoint, $params);
    }
    
    /**
     * Realizar petición POST
     */
    public function post($endpoint, $data = []) {
        return $this->request('POST', $endpoint, [], $data);
    }
    
    /**
     * Realizar petición PUT
     */
    public function put($endpoint, $data = []) {
        return $this->request('PUT', $endpoint, [], $data);
    }
    
    /**
     * Obtener suscripción por ID
     */
    public function getSubscription($subscriptionId) {
        return $this->get("subscriptions/{$subscriptionId}");
    }
    
    /**
     * Obtener suscripciones activas
     */
    public function getActiveSubscriptions($page = 1, $perPage = 100) {
        return $this->get('subscriptions', [
            'status' => 'active',
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * Obtener TODAS las suscripciones (con cualquier estado)
     */
    public function getAllSubscriptions($page = 1, $perPage = 100, $status = 'any') {
        $params = [
            'page' => $page,
            'per_page' => $perPage
        ];

        if ($status !== 'any') {
            $params['status'] = $status;
        }

        return $this->get('subscriptions', $params);
    }

    /**
     * Obtener suscripciones modificadas después de una fecha
     */
    public function getSubscriptionsModifiedAfter($date, $page = 1, $perPage = 100) {
        return $this->get('subscriptions', [
            'modified_after' => $date,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * Obtener suscripciones creadas después de una fecha
     */
    public function getSubscriptionsCreatedAfter($date, $page = 1, $perPage = 100) {
        return $this->get('subscriptions', [
            'after' => $date,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * Obtener suscripciones de un cliente
     */
    public function getCustomerSubscriptions($customerId) {
        return $this->get('subscriptions', ['customer' => $customerId]);
    }

    // ========== ORDERS API (para Flexible Subscriptions) ==========

    /**
     * Obtener pedidos completados o en procesamiento
     */
    public function getOrders($page = 1, $perPage = 100, $status = 'any') {
        $params = [
            'page' => $page,
            'per_page' => $perPage,
            'orderby' => 'date',
            'order' => 'desc'
        ];

        if ($status !== 'any') {
            $params['status'] = $status;
        }

        return $this->get('orders', $params);
    }

    /**
     * Obtener pedidos modificados O creados después de una fecha
     *
     * IMPORTANTE: Busca pedidos que cumplan CUALQUIERA de estas condiciones:
     * - Fueron creados después de $date
     * - Fueron modificados después de $date
     *
     * Esto asegura que capturamos:
     * - Pedidos nuevos (creados recientemente)
     * - Pedidos actualizados (modificados recientemente)
     */
    public function getOrdersModifiedAfter($date, $page = 1, $perPage = 100) {
        return $this->get('orders', [
            'after' => $date,  // Pedidos creados después de esta fecha
            'page' => $page,
            'per_page' => $perPage,
            'status' => ['processing', 'completed'],
            'orderby' => 'date',
            'order' => 'desc'
        ]);
    }

    /**
     * Obtener todos los pedidos completados y en procesamiento
     */
    public function getProcessableOrders($page = 1, $perPage = 100) {
        return $this->get('orders', [
            'page' => $page,
            'per_page' => $perPage,
            'status' => ['processing', 'completed']
        ]);
    }

    /**
     * Actualizar meta data de un pedido
     *
     * @param int $orderId ID del pedido en WooCommerce
     * @param string $key Clave del meta field
     * @param mixed $value Valor del meta field
     * @return array Respuesta de la API
     */
    public function updateOrderMeta($orderId, $key, $value) {
        $data = [
            'meta_data' => [
                [
                    'key' => $key,
                    'value' => $value
                ]
            ]
        ];

        return $this->put("orders/{$orderId}", $data);
    }

    /**
     * Crear una nota en un pedido
     *
     * @param int $orderId ID del pedido en WooCommerce
     * @param string $note Texto de la nota
     * @param bool $customerNote Si true, envía email al cliente
     * @return array Respuesta de la API
     */
    public function createOrderNote($orderId, $note, $customerNote = false) {
        $data = [
            'note' => $note,
            'customer_note' => $customerNote
        ];

        return $this->post("orders/{$orderId}/notes", $data);
    }

    /**
     * Realizar petición HTTP
     */
    private function request($method, $endpoint, $params = [], $data = []) {
        // Construir URL
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        // Añadir autenticación a params
        $params['consumer_key'] = $this->consumerKey;
        $params['consumer_secret'] = $this->consumerSecret;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Configurar cURL
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Datos para POST/PUT
        if (in_array($method, ['POST', 'PUT']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Ejecutar
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Verificar errores de conexión
        if ($response === false) {
            throw new Exception("WooCommerce API connection error: {$error}");
        }
        
        // Decodificar respuesta
        $result = json_decode($response, true);
        
        // Verificar errores de API
        if ($httpCode >= 400) {
            $errorMessage = $result['message'] ?? 'Unknown error';
            throw new Exception("WooCommerce API error ({$httpCode}): {$errorMessage}");
        }
        
        return $result;
    }
    
    /**
     * Test de conexión
     */
    public function testConnection() {
        try {
            $result = $this->get('system_status');
            return [
                'success' => true,
                'message' => 'Connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
