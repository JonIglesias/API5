<?php
if (!defined('ABSPATH')) exit;

/**
 * Sistema de Logs COMPLETO v2.0
 * 
 * Registra:
 * - API (requests/responses)
 * - HTTP (llamadas externas completas)
 * - Errores PHP
 * - Errores WordPress
 * - JavaScript (vía AJAX)
 * - Debug/Info general
 * - IA (generaciones)
 * - Cron/Background tasks
 * - Database queries
 */
class AP_Logger {
    
    // Tipos de logs
    const TYPE_ERROR = 'errors';
    const TYPE_WARNING = 'warnings';
    const TYPE_INFO = 'info';
    const TYPE_IA = 'ia';
    const TYPE_API = 'api';
    const TYPE_HTTP = 'http';
    const TYPE_JS = 'javascript';
    const TYPE_CRON = 'cron';
    const TYPE_DB = 'database';
    const TYPE_DEBUG = 'debug';
    const TYPE_WP = 'wordpress';
    
    // Niveles de severidad
    const LEVEL_CRITICAL = 'CRITICAL';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';
    
    private static $initialized = false;
    
    /**
     * Inicializar captura de errores
     */
    public static function init() {
        if (self::$initialized) return;
        
        // Capturar errores PHP
        set_error_handler([__CLASS__, 'capture_php_error']);
        
        // Capturar errores fatales
        register_shutdown_function([__CLASS__, 'capture_fatal_error']);
        
        // Hook para errores de WordPress
        add_action('wp_error_added', [__CLASS__, 'capture_wp_error'], 10, 4);
        
        // AJAX para logs de JavaScript
        add_action('wp_ajax_ap_log_js', [__CLASS__, 'ajax_log_js']);
        add_action('wp_ajax_nopriv_ap_log_js', [__CLASS__, 'ajax_log_js']);
        
        self::$initialized = true;
    }
    
    /**
     * Directorio de logs
     */
    private static function get_log_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/autopost-logs';
    }
    
    /**
     * Asegurar que existe el directorio
     */
    private static function ensure_log_dir() {
        $log_dir = self::get_log_dir();
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Proteger con .htaccess
            file_put_contents($log_dir . '/.htaccess', "deny from all\n");
            
            // Index.php vacío
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
        
        return $log_dir;
    }
    
    /**
     * Obtener archivo de log según tipo
     */
    private static function get_log_file($type) {
        $log_dir = self::ensure_log_dir();
        return $log_dir . '/' . $type . '.log';
    }
    
    /**
     * Escribir log (método principal)
     */
    public static function log($message, $type = self::TYPE_INFO, $level = self::LEVEL_INFO, $context = []) {
        $log_file = self::get_log_file($type);
        
        // Formato mejorado
        $timestamp = date('Y-m-d H:i:s');
        $memory = round(memory_get_usage() / 1024 / 1024, 2) . 'MB';
        
        // Añadir información del contexto si existe
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = "\n" . str_repeat(' ', 20) . '└─ ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        
        // Formato: [TIMESTAMP] [LEVEL] [MEMORY] Message
        $log_line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            $timestamp,
            str_pad($level, 8),
            str_pad($memory, 8),
            $message,
            $contextStr
        );
        
        // Escribir al archivo específico
        @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Copiar errores y warnings a errors.log
        if ($level === self::LEVEL_ERROR || $level === self::LEVEL_CRITICAL || $type === self::TYPE_ERROR) {
            @file_put_contents(
                self::get_log_file(self::TYPE_ERROR),
                $log_line,
                FILE_APPEND | LOCK_EX
            );
        }
        
        // Rotar si es muy grande (>10MB)
        self::rotate_if_needed($log_file);
    }
    
    // ============================================
    // MÉTODOS PÚBLICOS DE LOGGING
    // ============================================
    
    public static function error($message, $context = []) {
        self::log($message, self::TYPE_ERROR, self::LEVEL_ERROR, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log($message, self::TYPE_WARNING, self::LEVEL_WARNING, $context);
    }
    
    public static function info($message, $context = []) {
        self::log($message, self::TYPE_INFO, self::LEVEL_INFO, $context);
    }
    
    public static function debug($message, $context = []) {
        self::log($message, self::TYPE_DEBUG, self::LEVEL_DEBUG, $context);
    }
    
    public static function ia($message, $context = []) {
        self::log($message, self::TYPE_IA, self::LEVEL_INFO, $context);
    }
    
    public static function api($message, $context = []) {
        self::log($message, self::TYPE_API, self::LEVEL_INFO, $context);
    }
    
    public static function http($message, $context = []) {
        self::log($message, self::TYPE_HTTP, self::LEVEL_INFO, $context);
    }
    
    public static function cron($message, $context = []) {
        self::log($message, self::TYPE_CRON, self::LEVEL_INFO, $context);
    }
    
    public static function db($message, $context = []) {
        self::log($message, self::TYPE_DB, self::LEVEL_INFO, $context);
    }
    
    /**
     * Log HTTP completo (request + response)
     */
    public static function http_request($url, $method, $args = [], $response = null) {
        $context = [
            'url' => $url,
            'method' => $method,
            'headers' => $args['headers'] ?? [],
            'body' => isset($args['body']) ? (strlen($args['body']) > 1000 ? substr($args['body'], 0, 1000) . '...' : $args['body']) : null,
        ];
        
        if ($response) {
            if (is_wp_error($response)) {
                $context['response'] = [
                    'error' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ];
            } else {
                $context['response'] = [
                    'code' => wp_remote_retrieve_response_code($response),
                    'message' => wp_remote_retrieve_response_message($response),
                    'body_length' => strlen(wp_remote_retrieve_body($response)),
                    'body_preview' => substr(wp_remote_retrieve_body($response), 0, 500)
                ];
            }
        }
        
        self::http("HTTP Request: {$method} {$url}", $context);
    }
    
    /**
     * Log de llamada a API completa
     */
    public static function api_call($endpoint, $method, $request_data, $response_data) {
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'request' => $request_data,
            'response' => is_array($response_data) ? $response_data : ['raw' => substr($response_data, 0, 500)]
        ];
        
        self::api("API Call: {$method} {$endpoint}", $context);
    }
    
    // ============================================
    // CAPTURA AUTOMÁTICA DE ERRORES
    // ============================================
    
    /**
     * Capturar errores PHP
     */
    public static function capture_php_error($errno, $errstr, $errfile, $errline) {
        $levels = [
            E_ERROR => self::LEVEL_ERROR,
            E_WARNING => self::LEVEL_WARNING,
            E_NOTICE => self::LEVEL_INFO,
            E_USER_ERROR => self::LEVEL_ERROR,
            E_USER_WARNING => self::LEVEL_WARNING,
            E_USER_NOTICE => self::LEVEL_INFO,
        ];
        
        $level = $levels[$errno] ?? self::LEVEL_ERROR;
        
        self::log(
            "PHP Error: {$errstr}",
            self::TYPE_ERROR,
            $level,
            [
                'file' => $errfile,
                'line' => $errline,
                'errno' => $errno
            ]
        );
        
        // No bloquear el error handler de WordPress
        return false;
    }
    
    /**
     * Capturar errores fatales
     */
    public static function capture_fatal_error() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::log(
                "FATAL ERROR: {$error['message']}",
                self::TYPE_ERROR,
                self::LEVEL_CRITICAL,
                [
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => $error['type']
                ]
            );
        }
    }
    
    /**
     * Capturar errores de WordPress
     */
    public static function capture_wp_error($code, $message, $data, $wp_error) {
        // Solo loguear si es un WP_Error del plugin
        if (strpos($code, 'ap_') === 0 || strpos($message, 'AutoPost') !== false) {
            self::log(
                "WordPress Error: {$message}",
                self::TYPE_WP,
                self::LEVEL_ERROR,
                ['code' => $code, 'data' => $data]
            );
        }
    }
    
    /**
     * AJAX para logs de JavaScript
     */
    public static function ajax_log_js() {
        $message = sanitize_text_field($_POST['message'] ?? '');
        $level = sanitize_text_field($_POST['level'] ?? 'info');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        
        $levelMap = [
            'error' => self::LEVEL_ERROR,
            'warn' => self::LEVEL_WARNING,
            'info' => self::LEVEL_INFO,
            'debug' => self::LEVEL_DEBUG
        ];
        
        self::log(
            "JS: {$message}",
            self::TYPE_JS,
            $levelMap[$level] ?? self::LEVEL_INFO,
            $context
        );
        
        wp_send_json_success();
    }
    
    // ============================================
    // LECTURA Y GESTIÓN DE LOGS
    // ============================================
    
    /**
     * Leer logs con filtros opcionales
     */
    public static function read_log($type = 'info', $lines = 500, $search = '', $level = '') {
        $log_file = self::get_log_file($type);
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $file_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$file_lines) {
            return [];
        }
        
        // Aplicar filtros
        if ($search || $level) {
            $file_lines = array_filter($file_lines, function($line) use ($search, $level) {
                $match = true;
                
                if ($search && stripos($line, $search) === false) {
                    $match = false;
                }
                
                if ($level && stripos($line, "[{$level}]") === false) {
                    $match = false;
                }
                
                return $match;
            });
        }
        
        // Últimas N líneas
        $file_lines = array_slice($file_lines, -$lines);
        
        return array_reverse($file_lines);
    }
    
    /**
     * Limpiar un log específico
     */
    public static function clear_log($type) {
        $log_file = self::get_log_file($type);
        
        if (file_exists($log_file)) {
            return @unlink($log_file);
        }
        
        return true;
    }
    
    /**
     * Limpiar todos los logs
     */
    public static function clear_all_logs() {
        $types = [
            self::TYPE_ERROR, self::TYPE_WARNING, self::TYPE_INFO, 
            self::TYPE_IA, self::TYPE_API, self::TYPE_HTTP,
            self::TYPE_JS, self::TYPE_CRON, self::TYPE_DB,
            self::TYPE_DEBUG, self::TYPE_WP
        ];
        
        foreach ($types as $type) {
            self::clear_log($type);
        }
        
        return true;
    }
    
    /**
     * Obtener tamaño de un log
     */
    public static function get_log_size($type) {
        $log_file = self::get_log_file($type);
        
        if (file_exists($log_file)) {
            return filesize($log_file);
        }
        
        return 0;
    }
    
    /**
     * Obtener todos los logs con sus tamaños
     */
    public static function get_all_logs_info() {
        $types = [
            self::TYPE_ERROR, self::TYPE_WARNING, self::TYPE_INFO, 
            self::TYPE_IA, self::TYPE_API, self::TYPE_HTTP,
            self::TYPE_JS, self::TYPE_CRON, self::TYPE_DB,
            self::TYPE_DEBUG, self::TYPE_WP
        ];
        
        $info = [];
        foreach ($types as $type) {
            $size = self::get_log_size($type);
            $info[$type] = [
                'size' => $size,
                'size_formatted' => self::format_bytes($size),
                'exists' => $size > 0
            ];
        }
        
        return $info;
    }
    
    /**
     * Descargar log como archivo
     */
    public static function download_log($type) {
        $log_file = self::get_log_file($type);
        
        if (!file_exists($log_file)) {
            return false;
        }
        
        $filename = 'autopost-' . $type . '-' . date('Y-m-d-His') . '.log';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($log_file));
        
        readfile($log_file);
        exit;
    }
    
    /**
     * Rotar log si es muy grande
     */
    private static function rotate_if_needed($log_file) {
        if (!file_exists($log_file)) return;
        
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (filesize($log_file) > $max_size) {
            $backup = $log_file . '.' . date('Y-m-d-His') . '.old';
            @rename($log_file, $backup);
            
            // Mantener solo los últimos 5 backups
            $pattern = dirname($log_file) . '/' . basename($log_file) . '.*.old';
            $backups = glob($pattern);
            
            if (count($backups) > 5) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                foreach (array_slice($backups, 0, -5) as $old_backup) {
                    @unlink($old_backup);
                }
            }
        }
    }
    
    /**
     * Formatear bytes
     */
    private static function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}

// Inicializar
AP_Logger::init();
