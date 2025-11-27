<?php
/**
 * Endpoint: Configuración
 * 
 * Obtiene la configuración de la API
 * 
 * @package AutoPostsAPI
 * @version 5.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/controllers/V3CompatController.php';

class ConfiguracionEndpoint {
    public function handle() {
        $controller = new V3CompatController();
        $controller->getSettings();
    }
}
