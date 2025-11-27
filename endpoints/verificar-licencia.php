<?php
/**
 * Endpoint: Verificar Licencia
 * 
 * Verifica la validez de una licencia y dominio
 * 
 * @package AutoPostsAPI
 * @version 5.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/services/LicenseValidator.php';

class VerificarLicenciaEndpoint {
    
    public function handle() {
        $input = Response::getJsonInput();
        
        $licenseKey = $input['license_key'] ?? $_GET['license_key'] ?? null;
        $domain = $input['domain'] ?? $_GET['domain'] ?? null;
        
        if (!$licenseKey) {
            Response::error('license_key es requerida', 400);
        }
        
        if (!$domain) {
            Response::error('domain es requerido', 400);
        }
        
        // Validar licencia
        $validator = new LicenseValidator();
        $validation = $validator->validate($licenseKey, $domain);
        
        if (!$validation['valid']) {
            Response::error($validation['reason'] ?? 'Licencia inválida', 401, [
                'valid' => false,
                'upgrade_url' => $validation['upgrade_url'] ?? null
            ]);
        }
        
        $license = $validation['license'];
        
        // Respuesta exitosa
        Response::success([
            'valid' => true,
            'license' => [
                'key' => $license['license_key'],
                'status' => $license['status'],
                'plan_id' => $license['plan_id'],
                'plan_name' => $license['plan_name'] ?? 'Plan Desconocido',
                'expires_at' => $license['period_ends_at'],
                'tokens_available' => max(0, $license['tokens_limit'] - $license['tokens_used_this_period']),
                'tokens_limit' => $license['tokens_limit'],
                'tokens_used' => $license['tokens_used_this_period']
            ]
        ]);
    }
}
