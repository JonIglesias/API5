<?php
/**
 * Endpoint: Plan Activo
 * 
 * Obtiene el plan activo de la licencia (con snapshots)
 * 
 * @package AutoPostsAPI
 * @version 5.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/controllers/V3CompatController.php';

class PlanActivoEndpoint {
    public function handle() {
        $controller = new V3CompatController();
        $controller->getActivePlan();
    }
}
