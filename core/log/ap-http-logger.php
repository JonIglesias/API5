<?php
if (!defined('ABSPATH')) exit;

/**
 * HTTP Logger Interceptor
 * Captura TODAS las llamadas HTTP de WordPress automáticamente
 */
class AP_HTTP_Logger {
    
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        // Hook ANTES de la petición HTTP
        add_filter('pre_http_request', [__CLASS__, 'log_http_request'], 10, 3);
        
        // Hook DESPUÉS de la petición HTTP (para response)
        add_action('http_api_debug', [__CLASS__, 'log_http_response'], 10, 5);
        
        self::$initialized = true;
    }
    
    /**
     * Loguear petición HTTP
     */
    public static function log_http_request($preempt, $args, $url) {
        // Solo loguear peticiones relacionadas con el plugin
        if (self::should_log_url($url)) {
            AP_Logger::http(
                "🔵 HTTP Request Iniciado",
                [
                    'url' => $url,
                    'method' => $args['method'] ?? 'GET',
                    'timeout' => $args['timeout'] ?? 'default',
                    'headers' => self::sanitize_headers($args['headers'] ?? []),
                    'body_size' => isset($args['body']) ? strlen($args['body']) : 0
                ]
            );
        }
        
        // No preemptamos, dejamos que continúe
        return $preempt;
    }
    
    /**
     * Loguear respuesta HTTP
     */
    public static function log_http_response($response, $context, $class, $args, $url) {
        // Solo loguear peticiones relacionadas con el plugin
        if (!self::should_log_url($url)) return;
        
        if (is_wp_error($response)) {
            AP_Logger::http(
                "❌ HTTP Request Error",
                [
                    'url' => $url,
                    'error_code' => $response->get_error_code(),
                    'error_message' => $response->get_error_message(),
                    'error_data' => $response->get_error_data()
                ]
            );
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $level = $code >= 200 && $code < 300 ? '✅' : '⚠️';
            
            AP_Logger::http(
                "{$level} HTTP Request Completado",
                [
                    'url' => $url,
                    'status_code' => $code,
                    'status_message' => wp_remote_retrieve_response_message($response),
                    'response_headers' => self::sanitize_headers(wp_remote_retrieve_headers($response)),
                    'body_size' => strlen($body),
                    'body_preview' => self::preview_body($body, 500),
                    'execution_time' => self::get_execution_time($context)
                ]
            );
        }
    }
    
    /**
     * Decidir si loguear esta URL
     */
    private static function should_log_url($url) {
        // Loguear si contiene ciertas keywords
        $keywords = [
            'bocetosmarketing.com/api_claude',
            'api.anthropic.com',
            'api.unsplash.com',
            'api.pexels.com',
            'pixabay.com/api',
            // Añade más según necesites
        ];
        
        foreach ($keywords as $keyword) {
            if (strpos($url, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitizar headers (ocultar API keys)
     */
    private static function sanitize_headers($headers) {
        if (!is_array($headers)) return [];
        
        $sensitive = ['authorization', 'x-api-key', 'x-license-key', 'cookie'];
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $headers[$key] = '***OCULTO***';
            }
        }
        
        return $headers;
    }
    
    /**
     * Preview del body
     */
    private static function preview_body($body, $max_length = 500) {
        if (strlen($body) <= $max_length) {
            return $body;
        }
        
        return substr($body, 0, $max_length) . '... [' . strlen($body) . ' bytes total]';
    }
    
    /**
     * Obtener tiempo de ejecución
     */
    private static function get_execution_time($context) {
        // WordPress no proporciona esto fácilmente, así que lo estimamos
        return 'N/A';
    }
}

// Inicializar
AP_HTTP_Logger::init();
