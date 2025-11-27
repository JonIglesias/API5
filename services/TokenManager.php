<?php
/**
 * TokenManager Service
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/models/License.php';

class TokenManager {
    
    /**
     * Incrementar uso de tokens (método estático)
     */
    public static function incrementUsage($licenseId, $tokensUsed) {
        $licenseModel = new License();
        return $licenseModel->incrementTokens($licenseId, $tokensUsed);
    }
    
    /**
     * Verificar si hay tokens disponibles
     */
    public function hasTokensAvailable($license, $tokensNeeded) {
        $available = $license['tokens_limit'] - $license['tokens_used_this_period'];
        return $available >= $tokensNeeded;
    }
    
    /**
     * Consumir tokens
     */
    public function consume($licenseId, $tokensUsed) {
        $licenseModel = new License();
        return $licenseModel->incrementTokens($licenseId, $tokensUsed);
    }
    
    /**
     * Resetear tokens del periodo
     */
    public function resetPeriod($licenseId) {
        $licenseModel = new License();
        return $licenseModel->resetPeriodTokens($licenseId);
    }
    
    /**
     * Verificar y resetear tokens si el periodo ha expirado
     */
    public function checkAndResetIfNeeded($license) {
        if (!$license['period_starts_at']) {
            return false;
        }
        
        $periodStart = strtotime($license['period_starts_at']);
        $oneMonthLater = strtotime('+1 month', $periodStart);
        
        // Si ha pasado un mes desde el inicio del periodo
        if (time() > $oneMonthLater) {
            $this->resetPeriod($license['id']);
            return true;
        }
        
        return false;
    }
}
