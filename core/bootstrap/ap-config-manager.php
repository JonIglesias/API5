<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestor de configuración usando tabla propia
 */
class AP_Config_Manager {
    
    private static $cache = [];
    
    /**
     * Obtener valor de configuración
     */
    public static function get($key, $default = '') {
        // Usar cache para evitar múltiples consultas
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ap_config';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT config_value FROM $table WHERE config_key = %s",
            $key
        ));
        
        if ($value === null) {
            self::$cache[$key] = $default;
            return $default;
        }
        
        self::$cache[$key] = $value;
        return $value;
    }
    
    /**
     * Guardar valor de configuración
     */
    public static function set($key, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_config';
        
        $result = $wpdb->replace(
            $table,
            [
                'config_key' => $key,
                'config_value' => $value
            ],
            ['%s', '%s']
        );
        
        // Actualizar cache
        self::$cache[$key] = $value;
        
        return $result !== false;
    }
    
    /**
     * Guardar múltiples valores
     */
    public static function set_multiple($data) {
        foreach ($data as $key => $value) {
            self::set($key, $value);
        }
    }
    
    /**
     * Eliminar valor
     */
    public static function delete($key) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_config';
        
        $wpdb->delete($table, ['config_key' => $key], ['%s']);
        unset(self::$cache[$key]);
    }
    
    /**
     * Obtener todas las configuraciones
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_config';
        
        $results = $wpdb->get_results(
            "SELECT config_key, config_value FROM $table",
            ARRAY_A
        );
        
        $config = [];
        foreach ($results as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        return $config;
    }
    
    /**
     * Limpiar cache
     */
    public static function clear_cache() {
        self::$cache = [];
    }
}
