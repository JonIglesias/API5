<?php
/**
 * Endpoint: Estadísticas de Licencia
 * 
 * Obtiene estadísticas agrupadas por campañas y colas
 * 
 * @package AutoPostsAPI
 * @version 5.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/models/License.php';

class EstadisticasLicenciaEndpoint {
    
    public function handle() {
        // Reutilizar la lógica del controlador pero sin depender de él
        require_once API_BASE_DIR . '/controllers/LicenseStatsController.php';
        $controller = new LicenseStatsController();
        $controller->getLicenseStats();
    }
}
