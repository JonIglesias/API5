<?php
if (!defined('ABSPATH')) exit;

function ap_load_modules() {
    $modules_dir = AP_PLUGIN_DIR . 'modules/';
    
    if (!is_dir($modules_dir)) {
        AP_Logger::error('Directorio de módulos no encontrado', ['dir' => $modules_dir]);
        return;
    }
    
    $modules = scandir($modules_dir);
    
    foreach ($modules as $module) {
        if ($module === '.' || $module === '..') continue;
        
        $module_path = $modules_dir . $module;
        
        if (!is_dir($module_path)) continue;
        
        // Buscar archivo principal (mismo nombre que carpeta)
        $main_file = $module_path . '/' . $module . '.php';
        
        if (file_exists($main_file)) {
            require_once $main_file;
            // AP_Logger::info("Módulo cargado: {$module}"); // Demasiado verbose
        } else {
            AP_Logger::warning("Módulo sin archivo principal: {$module}", ['expected' => $main_file]);
        }
    }
    
    do_action('ap_modules_loaded');
}

// Variables globales compartidas por todos los módulos
function ap_get_global_vars() {
    return [
        'api_url' => get_option('ap_api_url', AP_API_URL_DEFAULT),
        'license_key' => get_option('ap_license_key', ''),
        'unsplash_key' => get_option('ap_unsplash_key', ''),
        'pixabay_key' => get_option('ap_pixabay_key', ''),
        'pexels_key' => get_option('ap_pexels_key', '')
    ];
}

// Validar nonce + capability para AJAX
function ap_verify_ajax_request($capability = 'manage_options') {
    check_ajax_referer('ap_nonce', 'nonce');
    
    if (!current_user_can($capability)) {
        wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    }
}
