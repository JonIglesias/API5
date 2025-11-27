<?php
/**
 * Cliente API - VERSIÓN V11
 * Actualizado para API v11 con soporte de snapshots
 * 
 * CAMBIOS v11:
 * - Usa getActivePlanForLicense() para respetar snapshots
 * - Implementa caché del plan activo
 * - Respeta timing y límites del plan
 * - Timeouts configurables según plan
 */

if (!defined('ABSPATH')) exit;

class AP_API_Client {
    
    private $api_url;
    private $license_key;
    private $domain;
    private $active_plan_cache = null;
    
    const CACHE_KEY = 'ap_active_plan_v11';
    const CACHE_DURATION = 3600; // 1 hora
    
    public function __construct() {
        $this->api_url = rtrim(get_option('ap_api_url', AP_API_URL_DEFAULT), '/');
        $this->license_key = get_option('ap_license_key', '');
        $this->domain = parse_url(get_site_url(), PHP_URL_HOST);
    }
    
    /**
     * Petición genérica a la API con timeout configurable
     */
    private function request($endpoint, $data = [], $method = 'POST', $custom_timeout = null) {
        // Si la URL termina en index.php, usar query string
        $uses_query_string = strpos($this->api_url, 'index.php') !== false;
        
        if ($uses_query_string) {
            $url = $this->api_url . '?endpoint=' . urlencode($endpoint);
        } else {
            $url = $this->api_url . '/' . ltrim($endpoint, '/');
        }
        
        // Obtener timeout del plan si no se especifica
        // PERO: Evitar llamar get_active_plan si estamos generando cola para evitar recursión
        if ($custom_timeout === null) {
            $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';
            
            if ($is_generating || $endpoint === 'get-active-plan') {
                // Durante generación de cola o al obtener el plan, usar timeout por defecto
                $custom_timeout = 60;
            } else {
                // En otros casos, intentar obtener del plan
                $plan = $this->get_active_plan();
                $custom_timeout = $plan ? ($plan['timing']['api_timeout'] ?? 60) : 60;
            }
        }
        
        error_log('=== API REQUEST v11 ===');
        error_log('URL: ' . $url);
        error_log('Endpoint: ' . $endpoint);
        error_log('License: ' . substr($this->license_key, 0, 10) . '...');
        error_log('Domain: ' . $this->domain);
        error_log('Timeout: ' . $custom_timeout . 's');
        error_log('Data: ' . json_encode($data));
        
        // Preparar body base
        $body_base = [
            'license_key' => $this->license_key,
            'timestamp' => time()
        ];
        
        // Solo añadir domain si no viene ya en $data
        if (!isset($data['domain'])) {
            $body_base['domain'] = $this->domain;
        }
        
        // ⭐ NUEVO: Si existe campaign_id en el contexto global, añadirlo
        if (isset($GLOBALS['ap_current_campaign_id']) && !isset($data['campaign_id'])) {
            $body_base['campaign_id'] = $GLOBALS['ap_current_campaign_id'];
            error_log('📦 +campaign_id: ' . $GLOBALS['ap_current_campaign_id']);
        }
        
        // ⭐ NUEVO: Si existe campaign_name en el contexto global, añadirlo
        if (isset($GLOBALS['ap_current_campaign_name']) && !isset($data['campaign_name'])) {
            $body_base['campaign_name'] = $GLOBALS['ap_current_campaign_name'];
            error_log('📦 +campaign_name: ' . $GLOBALS['ap_current_campaign_name']);
        }
        
        // ⭐ NUEVO: Si existe batch_id en el contexto global, añadirlo (para colas)
        if (isset($GLOBALS['ap_current_batch_id']) && !isset($data['batch_id'])) {
            $body_base['batch_id'] = $GLOBALS['ap_current_batch_id'];
            error_log('📦 +batch_id: ' . $GLOBALS['ap_current_batch_id']);
        }
        
        // Merge: $data tiene prioridad sobre $body_base
        $body = array_merge($body_base, $data);
        
        error_log('Final Body: ' . json_encode($body));
        
        AP_Logger::info("API Request v11: {$endpoint}", ['url' => $url, 'timeout' => $custom_timeout]);
        
        // LOG ÚNICO PARA DEBUG - Con ID único
        $unique_id = uniqid('req_');
        AP_Logger::info("🔥 INICIANDO REQUEST", [
            'endpoint' => $endpoint,
            'unique_id' => $unique_id,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ]);
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'AutoPost/' . AP_VERSION,
                'X-License-Key' => $this->license_key
            ],
            'timeout' => $custom_timeout,
            'sslverify' => false
        ];
        
        // Para POST: body como JSON
        // Para GET: añadir parámetros a URL
        if ($method === 'POST') {
            $args['body'] = json_encode($body);
        } else {
            // Añadir parámetros a la URL para GET
            if (!empty($body)) {
                $separator = (strpos($url, '?') !== false) ? '&' : '?';
                $url .= $separator . http_build_query($body);
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            error_log('ERROR: ' . $error);
            AP_Logger::error("API Error: {$error}", ['endpoint' => $endpoint]);
            return [
                'success' => false,
                'error' => $error
            ];
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        error_log('HTTP Code: ' . $http_code);
        error_log('Response: ' . substr($body_response, 0, 500));
        
        if ($http_code !== 200) {
            // Mensaje específico para 404
            if ($http_code === 404) {
                error_log('❌ API NO ENCONTRADA (404): ' . $url);
                error_log('Verifica que index.php esté en: ' . dirname($url));
                
                AP_Logger::error("API no encontrada (404)", [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'message' => 'Verifica que los archivos de la API estén subidos correctamente'
                ]);
                
                return [
                    'success' => false,
                    'error' => "API no encontrada (404). Verifica que los archivos estén en: {$this->api_url}"
                ];
            }
            
            AP_Logger::error("API HTTP Error: {$http_code}", [
                'endpoint' => $endpoint,
                'response' => substr($body_response, 0, 200)
            ]);
            
            // Intentar parsear el error JSON
            $error_data = json_decode($body_response, true);
            $error_msg = isset($error_data['error']) ? $error_data['error'] : "HTTP {$http_code}";
            
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }
        
        $result = json_decode($body_response, true);
        
        if (!is_array($result)) {
            AP_Logger::error("API Invalid JSON", [
                'endpoint' => $endpoint,
                'response' => substr($body_response, 0, 200)
            ]);
            return [
                'success' => false,
                'error' => 'Respuesta inválida de la API'
            ];
        }
        
        error_log('Result: ' . json_encode($result));
        
        return $result;
    }
    
    // ================================================================
    // NUEVOS MÉTODOS v11: PLAN ACTIVO
    // ================================================================
    
    /**
     * Obtener plan activo de la licencia (respeta snapshots)
     * Usa caché para evitar llamadas excesivas
     */
    public function get_active_plan($force_refresh = false) {
        // Si ya está en memoria, devolverlo
        if (!$force_refresh && $this->active_plan_cache !== null) {
            AP_Logger::info('✅ Plan activo desde caché memoria');
            return $this->active_plan_cache;
        }
        
        // Intentar caché de WordPress solo si no estamos en AJAX generando cola
        $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';
        
        if (!$force_refresh && !$is_generating) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                $this->active_plan_cache = $cached;
                AP_Logger::info('✅ Plan activo desde transient WP');
                return $cached;
            }
        }
        
        // Llamar a la API
        AP_Logger::api('🌐 Llamando a API: get-active-plan', [
            'api_url' => $this->api_url,
            'license_key' => substr($this->license_key, 0, 10) . '...'
        ]);
        
        $result = $this->request('get-active-plan', [], 'GET');
        
        AP_Logger::api('📥 Respuesta get-active-plan', [
            'result_type' => gettype($result),
            'has_success' => isset($result['success']),
            'has_data' => isset($result['data']),
            'has_plan_direct' => isset($result['plan']),
            'has_plan_in_data' => isset($result['data']['plan']),
            'result' => $result
        ]);
        
        if (isset($result['success']) && $result['success']) {
            // La API v11 devuelve el plan en data.plan
            $plan = $result['data']['plan'] ?? $result['plan'] ?? null;
            
            if ($plan) {
                // Solo guardar en caché si NO estamos generando cola
                if (!$is_generating) {
                    set_transient(self::CACHE_KEY, $plan, self::CACHE_DURATION);
                }
                $this->active_plan_cache = $plan;
                
                AP_Logger::info('✅ Plan activo obtenido', [
                    'plan' => $plan['name'] ?? 'N/A',
                    'delay' => $plan['timing']['post_generation_delay'] ?? 'N/A',
                    'is_snapshot' => isset($plan['_is_snapshot']),
                    'cached' => !$is_generating
                ]);
                
                return $plan;
            }
        }
        
        AP_Logger::error('❌ Error obteniendo plan activo', [
            'error' => $result['error'] ?? 'Unknown',
            'full_result' => json_encode($result)
        ]);
        
        return null;
    }
    
    /**
     * Obtener estadísticas detalladas de uso
     */
    public function get_detailed_stats($date_from = null, $date_to = null) {
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }
        
        $result = $this->request('get-license-stats', [
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);
        
        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['data']
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error obteniendo estadísticas'
        ];
    }
    
    /**
     * Limpiar caché del plan (llamar después de renovar)
     */
    public function clear_plan_cache() {
        delete_transient(self::CACHE_KEY);
        $this->active_plan_cache = null;
        AP_Logger::info('Caché del plan limpiado');
    }
    
    /**
     * Verificar si se puede ejecutar una acción según límites del plan
     */
    public function can_execute($action, $count = 1) {
        $plan = $this->get_active_plan();
        
        if (!$plan) {
            return [
                'can_execute' => false,
                'error' => 'No se pudo obtener el plan activo'
            ];
        }
        
        // Por ahora solo validamos que tengamos el plan
        // TODO: Implementar validación de límites cuando la API lo soporte
        
        return ['can_execute' => true];
    }
    
    /**
     * Obtener delay entre posts según el plan
     */
    public function get_post_delay() {
        // Durante generación de cola, no usar cache para evitar recursión
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';
        
        if ($is_generating) {
            AP_Logger::info("⏱️ Delay durante GENERACIÓN de cola (default)", ['delay' => 30]);
            return 30; // Valor por defecto solo durante GENERACIÓN
        }
        
        $plan = $this->get_active_plan();
        $delay = $plan ? ($plan['timing']['post_generation_delay'] ?? 30) : 30;
        
        // SIEMPRE loguear el delay obtenido
        AP_Logger::info("⏱️ Delay configurado desde plan activo", [
            'delay_seconds' => $delay,
            'plan' => $plan['name'] ?? 'No plan',
            'is_snapshot' => isset($plan['_is_snapshot']) ? 'SÍ' : 'NO'
        ]);
        
        error_log("🕐 DELAY EJECUCIÓN: {$delay}s desde plan: " . ($plan['name'] ?? 'default'));
        
        return $delay;
    }
    
    /**
     * Obtener máximo de reintentos según el plan
     */
    public function get_max_retries() {
        // Durante generación de cola, no usar cache para evitar recursión
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';
        
        if ($is_generating) {
            return 3; // Valor por defecto durante generación
        }
        
        $plan = $this->get_active_plan();
        return $plan ? ($plan['timing']['max_retries'] ?? 3) : 3;
    }
    
    // ================================================================
    // ENDPOINTS CORREGIDOS
    // ================================================================
    
    /**
     * Verificar licencia
     */
    public function verify_license() {
        $result = $this->request('verify', []);
        
        // Si la verificación es exitosa, limpiar caché del plan
        if (isset($result['success']) && $result['success']) {
            $this->clear_plan_cache();
        }
        
        return $result;
    }
    
    /**
     * Obtener información de uso de la licencia
     */
    public function get_usage_info() {
        // Primero intentar obtener el plan activo
        $plan = $this->get_active_plan();
        
        $result = $this->request('usage', []);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            $usage = $data['usage'] ?? [];
            $limits = $data['limits'] ?? [];
            $plan_data = $data['plan'] ?? [];
            
            // Si tenemos plan activo, usar sus límites
            if ($plan) {
                $limits = $plan['limits'] ?? $limits;
                $plan_data = [
                    'name' => $plan['name'] ?? $plan_data['name'] ?? 'N/A',
                    'price' => $plan['price'] ?? 0
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'plan_name' => $plan_data['name'] ?? 'N/A',
                    'plan_price' => $plan_data['price'] ?? 0,
                    'tokens_limit' => $data['tokens_limit'] ?? $limits['tokens_per_month'] ?? 0,
                    'tokens_used' => $data['tokens_used'] ?? $usage['tokens_this_month'] ?? 0,
                    'posts_limit' => $data['posts_limit'] ?? 0,
                    'posts_used' => $data['posts_used'] ?? 0,
                    'is_snapshot' => isset($plan['_is_snapshot'])
                ]
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error obteniendo información de uso'
        ];
    }
    
    /**
     * 1. Generar descripción de empresa
     * CORREGIDO: usa generate-meta con type=company_description
     */
    public function generate_company_description($domain) {
        $data = [
            'type' => 'company_description',
            'domain' => $domain
        ];
        
        $result = $this->request('generate-meta', $data);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'description' => $data['description'] ?? $data['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando descripción'
        ];
    }
    
    /**
     * 2. Generar prompt de títulos
     * CORREGIDO: usa generate-meta con type=title_prompt
     */
    public function generate_title_prompt($niche, $description, $keywords = []) {
        $result = $this->request('generate-meta', [
            'type' => 'title_prompt',
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $keywords
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'prompt' => $data['prompt'] ?? $data['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando prompt'
        ];
    }
    
    /**
     * 4b. Generar prompt personalizado para contenido
     */
    public function generate_content_prompt($niche, $description, $keywords = []) {
        $result = $this->request('generate-content-prompt', [
            'niche' => $niche,
            'company_desc' => $description,
            'keywords_seo' => $keywords
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'prompt' => $data['prompt'] ?? $data['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando prompt de contenido'
        ];
    }
    
    /**
     * 3. Generar keywords SEO
     * CORREGIDO: usa generate-keywords con type=seo
     */
    public function generate_seo_keywords($niche, $description) {
        $result = $this->request('generate-keywords', [
            'type' => 'seo',
            'niche' => $niche,
            'company_description' => $description
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'keywords' => $data['keywords'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando keywords'
        ];
    }
    
    /**
     * 4. Generar título individual
     * CORRECTO: generate-title ya existe en la API
     */
    public function generate_post_title($prompt, $description, $keywords, $retry_attempt = 0) {
        $result = $this->request('generate-title', [
            'prompt' => $prompt,
            'company_description' => $description,
            'keywords_seo' => $keywords,
            'retry' => $retry_attempt
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'title' => $data['title'] ?? $result['title'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando título'
        ];
    }
    
    /**
     * 5. Generar keywords para imágenes
     * CORREGIDO: usa generate-keywords con type=images
     */
    public function generate_image_keywords($title, $niche, $description, $seo_keywords = '', $base_image_keywords = '') {
        $result = $this->request('generate-keywords', [
            'type' => 'images',
            'title' => $title,
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $seo_keywords,
            'keywords_images_base' => $base_image_keywords  // Keywords de imagen de la campaña
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'keywords' => $data['keywords'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando keywords de imágenes'
        ];
    }
    
    /**
     * 5b. Generar keywords para imágenes A NIVEL DE CAMPAÑA
     * NUEVO: usa generate-image-keys-campaign (sin título, genérico)
     */
    public function generate_campaign_image_keywords($niche, $description, $seo_keywords = '') {
        $result = $this->request('generate-image-keys-campaign', [
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $seo_keywords
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'keywords' => $data['keywords'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando keywords de imágenes de campaña'
        ];
    }
    
    /**
     * 6. Generar contenido del post
     * CORREGIDO: usa generate-post (no generate-content)
     */
    public function generate_post_content($title, $niche, $description, $keywords, $custom_prompt = '') {
        $result = $this->request('generate-post', [
            'title' => $title,
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $keywords,
            'custom_prompt' => $custom_prompt
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'content' => $data['content'] ?? $result['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando contenido'
        ];
    }
    
    /**
     * 7. Generar post completo (título + contenido)
     * NUEVO: usa generate-complete
     */
    public function generate_complete_post($niche, $description, $keywords, $config = []) {
        $result = $this->request('generate-complete', [
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $keywords,
            'config' => $config
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'title' => $data['title'] ?? $result['title'] ?? '',
                'content' => $data['content'] ?? $result['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando post completo'
        ];
    }
    
    /**
     * Obtener configuración de la API
     */
    public function get_api_settings() {
        $result = $this->request('get-settings', []);
        
        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['data'] ?? []
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error obteniendo configuración'
        ];
    }
}