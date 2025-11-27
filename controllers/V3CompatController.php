<?php
/**
 * V3CompatController - Endpoints de compatibilidad con API V3
 *
 * @version 4.01
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/models/Plan.php';

class V3CompatController {
    
    /**
     * GET /get-active-plan
     * 
     * Obtener información del plan activo de una licencia
     */
    public function getActivePlan() {
        $licenseKey = $_GET['license_key'] ?? null;
        
        if (!$licenseKey) {
            Response::error('license_key es requerido', 400);
        }
        
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        if (!$license) {
            Response::error('Licencia no encontrada', 404);
        }
        
        // Obtener plan
        $planModel = new Plan();
        $plan = $planModel->findById($license['plan_id']);
        
        Response::success([
            'plan' => [
                'id' => $plan['id'],
                'name' => $plan['name'],
                'tokens_per_month' => $plan['tokens_per_month'],
                'billing_cycle' => $plan['billing_cycle'] ?? 'monthly',
                
                // TIMING - Para que el plugin sepa cuánto esperar
                'post_generation_delay' => $plan['post_generation_delay'] ?? 60,
                'api_timeout' => $plan['api_timeout'] ?? 120,
                'max_retries' => $plan['max_retries'] ?? 3,
                
                // LIMITS
                'requests_per_day' => $plan['requests_per_day'] ?? -1,
                'requests_per_month' => $plan['requests_per_month'] ?? -1,
                'max_words_per_request' => $plan['max_words_per_request'] ?? 2000,
                'max_campaigns' => $plan['max_campaigns'] ?? -1,
                'max_posts_per_campaign' => $plan['max_posts_per_campaign'] ?? -1,
                
                // FEATURES
                'features' => isset($plan['features']) ? json_decode($plan['features'], true) : []
            ],
            'license' => [
                'status' => $license['status'],
                'tokens_used' => $license['tokens_used_this_period'],
                'tokens_limit' => $license['tokens_limit'],
                'tokens_available' => max(0, $license['tokens_limit'] - $license['tokens_used_this_period']),
                'period_ends_at' => $license['period_ends_at']
            ]
        ]);
    }
    
    /**
     * POST /usage
     * 
     * Obtener uso actual (compatible con V3)
     */
    public function usage() {
        $data = Response::getJsonInput();
        $licenseKey = $data['license_key'] ?? null;
        
        if (!$licenseKey) {
            Response::error('license_key es requerido', 400);
        }
        
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        if (!$license) {
            Response::error('Licencia no encontrada', 404);
        }
        
        $planModel = new Plan();
        $plan = $planModel->findById($license['plan_id']);
        
        Response::success([
            'plan_name' => $plan['name'] ?? 'Unknown',
            'tokens_limit' => $license['tokens_limit'],
            'tokens_used' => $license['tokens_used_this_period'],
            'tokens_available' => max(0, $license['tokens_limit'] - $license['tokens_used_this_period']),
            'period_ends_at' => $license['period_ends_at']
        ]);
    }
    
    /**
     * GET /get-settings
     * 
     * Obtener configuración de la API (compatible con V3)
     * Ahora devuelve settings del plan de la licencia
     */
    public function getSettings() {
        $licenseKey = $_GET['license_key'] ?? null;
        
        // Si no hay licenseKey, devolver defaults
        if (!$licenseKey) {
            Response::success([
                'post_generation_delay' => 60,
                'api_timeout' => 120,
                'max_retries' => 3
            ]);
        }
        
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        if (!$license) {
            // Si licencia no existe, devolver defaults
            Response::success([
                'post_generation_delay' => 60,
                'api_timeout' => 120,
                'max_retries' => 3
            ]);
        }
        
        // Obtener plan de la licencia
        $planModel = new Plan();
        $plan = $planModel->findById($license['plan_id']);
        
        Response::success([
            'post_generation_delay' => $plan['post_generation_delay'] ?? 60,
            'api_timeout' => $plan['api_timeout'] ?? 120,
            'max_retries' => $plan['max_retries'] ?? 3,

            // Campos adicionales por si el plugin los necesita
            'max_words_per_request' => $plan['max_words_per_request'] ?? 2000,
            'requests_per_day' => $plan['requests_per_day'] ?? -1,
            'requests_per_month' => $plan['requests_per_month'] ?? -1,
            'max_campaigns' => $plan['max_campaigns'] ?? -1,
            'max_posts_per_campaign' => $plan['max_posts_per_campaign'] ?? -1
        ]);
    }
}
