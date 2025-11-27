<?php
if (!defined('ABSPATH')) exit;

function ap_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    
    // Tabla campañas
    $sql_campaigns = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ap_campaigns (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        domain varchar(255) NOT NULL,
        company_desc text,
        niche varchar(100),
        prompt_titles text,
        keywords_seo text,
        keywords_images text,
        publish_days varchar(50),
        start_date datetime,
        publish_time time,
        num_posts int(11) NOT NULL DEFAULT 0,
        post_length varchar(20) DEFAULT 'medio',
        image_provider varchar(50),
        queue_generated tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    
    // Tabla cola
    $sql_queue = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ap_queue (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        campaign_id bigint(20) NOT NULL,
        title varchar(500),
        image_keywords text,
        featured_image_url text,
        featured_image_thumb text,
        inner_image_url text,
        inner_image_thumb text,
        status varchar(20) DEFAULT 'pending',
        post_id bigint(20) DEFAULT NULL,
        tokens_estimated int(11) DEFAULT 0,
        tokens_used int(11) DEFAULT 0,
        scheduled_date datetime,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id)
    ) $charset;";
    
    // Tabla logs
    $sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ap_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        type varchar(20) NOT NULL,
        message text,
        context text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY type (type),
        KEY created_at (created_at)
    ) $charset;";
    
    // Tabla de consumo de tokens
    $sql_tokens = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ap_token_usage (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        action varchar(100) NOT NULL,
        tokens int(11) NOT NULL DEFAULT 0,
        context text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_campaigns);
    dbDelta($sql_queue);
    dbDelta($sql_logs);
    dbDelta($sql_tokens);
}

function ap_set_default_options() {
    $defaults = [
        'ap_api_url' => AP_API_URL_DEFAULT,
        'ap_license_key' => '',
        'ap_unsplash_key' => '',
        'ap_pixabay_key' => '',
        'ap_pexels_key' => ''
    ];
    
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
}
