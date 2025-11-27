<?php
if (!defined('ABSPATH')) exit;

// Interceptar POST MUY TEMPRANO para PHP 8+ compatibility
add_action('plugins_loaded', 'ap_sanitize_post_very_early', 1);
function ap_sanitize_post_very_early() {
    if (empty($_POST)) return;
    
    // Convertir TODOS los valores null a string vacío recursivamente
    $_POST = array_map('ap_ensure_string', $_POST);
    $_REQUEST = array_map('ap_ensure_string', $_REQUEST);
}

function ap_ensure_string($value) {
    if (is_array($value)) {
        return array_map('ap_ensure_string', $value);
    }
    
    // Si es null, devolver string vacío
    if ($value === null) {
        return '';
    }
    
    return $value;
}

// Suprimir warnings de deprecated en producción para este plugin
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'autopost') !== false) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
