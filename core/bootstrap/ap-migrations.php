<?php
if (!defined('ABSPATH')) exit;

// Ejecutar migraciones necesarias
function ap_run_migrations() {
    global $wpdb;
    
    $current_version = get_option('ap_db_version', '0');
    
    // Migración v1.1: Añadir columna company_desc
    if (version_compare($current_version, '1.1', '<')) {
        $table = $wpdb->prefix . 'ap_campaigns';
        
        // Verificar si la columna existe
        $columns = $wpdb->get_col("DESCRIBE $table");
        
        if (!in_array('company_desc', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN company_desc TEXT NULL AFTER domain");
            AP_Logger::info('Migración 1.1: Columna company_desc añadida');
        }
        
        update_option('ap_db_version', '1.1');
    }
    
    // Migración v1.2: Añadir columnas de thumbnail
    if (version_compare($current_version, '1.2', '<')) {
        $table = $wpdb->prefix . 'ap_queue';
        
        $columns = $wpdb->get_col("DESCRIBE $table");
        
        if (!in_array('featured_image_thumb', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN featured_image_thumb TEXT NULL AFTER featured_image_url");
            AP_Logger::info('Migración 1.2: Columna featured_image_thumb añadida');
        }
        
        if (!in_array('inner_image_thumb', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN inner_image_thumb TEXT NULL AFTER inner_image_url");
            AP_Logger::info('Migración 1.2: Columna inner_image_thumb añadida');
        }
        
        update_option('ap_db_version', '1.2');
    }
    
    // Migración v1.3: Añadir columna category_id para asignar categoría a posts
    // ESTA MIGRACIÓN SIEMPRE SE VERIFICA (para instalaciones que ya tenían v1.2)
    $table = $wpdb->prefix . 'ap_campaigns';
    $columns = $wpdb->get_col("DESCRIBE $table");
    
    if (!in_array('category_id', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN category_id INT NULL AFTER image_provider");
        AP_Logger::info('Migración 1.3: Columna category_id añadida');
        update_option('ap_db_version', '1.3');
    } elseif (version_compare($current_version, '1.3', '<')) {
        // La columna ya existe, solo actualizar versión
        update_option('ap_db_version', '1.3');
    }
    
    // Migración v1.4: Añadir columna prompt_content para prompt personalizado de generación
    if (!in_array('prompt_content', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN prompt_content TEXT NULL AFTER prompt_titles");
        AP_Logger::info('Migración 1.4: Columna prompt_content añadida');
        update_option('ap_db_version', '1.4');
    } elseif (version_compare($current_version, '1.4', '<')) {
        update_option('ap_db_version', '1.4');
    }
    
    // Migración v1.5: Añadir campaign_id único para tracking en API
    $columns = $wpdb->get_col("DESCRIBE $table");
    if (!in_array('campaign_id', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN campaign_id VARCHAR(64) NULL AFTER id");
        $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY campaign_id (campaign_id)");
        
        // Generar campaign_id para campañas existentes
        $existing = $wpdb->get_results("SELECT id FROM $table WHERE campaign_id IS NULL");
        foreach ($existing as $row) {
            $unique_id = 'campaign_' . $row->id;
            $wpdb->update($table, ['campaign_id' => $unique_id], ['id' => $row->id]);
        }
        
        AP_Logger::info('Migración 1.5: Columna campaign_id añadida y poblada');
        update_option('ap_db_version', '1.5');
    } elseif (version_compare($current_version, '1.5', '<')) {
        update_option('ap_db_version', '1.5');
    }
}

// Ejecutar al activar plugin
add_action('plugins_loaded', 'ap_run_migrations');