<?php
/**
 * LicenseValidator Service - CON CAPTURA AUTOMÁTICA DE DOMINIO
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/core/Database.php';

class LicenseValidator {
    
    /**
     * Validar licencia
     */
    public function validate($licenseKey, $domain = null) {
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        // Licencia no existe
        if (!$license) {
            return [
                'valid' => false,
                'reason' => 'License key not found'
            ];
        }
        
        // Verificar estado
        if ($license['status'] !== 'active') {
            return [
                'valid' => false,
                'reason' => 'License is ' . $license['status']
            ];
        }
        
        // Verificar expiración
        if ($license['period_ends_at'] && strtotime($license['period_ends_at']) < time()) {
            return [
                'valid' => false,
                'reason' => 'License expired on ' . date('Y-m-d', strtotime($license['period_ends_at']))
            ];
        }
        
        // Verificar tokens disponibles
        if ($license['tokens_used_this_period'] >= $license['tokens_limit']) {
            $available = 0;
            $used = $license['tokens_used_this_period'];
            $limit = $license['tokens_limit'];
            
            return [
                'valid' => false,
                'reason' => sprintf(
                    'Token limit exceeded. Used: %s / Limit: %s. Upgrade your plan or wait for next billing cycle (resets on %s)',
                    number_format($used),
                    number_format($limit),
                    date('Y-m-d', strtotime($license['period_ends_at']))
                ),
                'tokens_used' => $used,
                'tokens_limit' => $limit,
                'period_ends_at' => $license['period_ends_at']
            ];
        }
        
        // Verificar dominio
        if ($domain !== null) {
            $domainCheck = $this->validateDomain($license, $domain);
            if (!$domainCheck['valid']) {
                return $domainCheck;
            }
            
            // Si se capturó un nuevo dominio, actualizar la licencia
            if (isset($domainCheck['domain_captured'])) {
                $license['domain'] = $domainCheck['domain_captured'];
            }
        }
        
        return [
            'valid' => true,
            'license' => $license
        ];
    }
    
    /**
     * Validar dominio autorizado
     * 
     * Si domain está vacío en BD → lo captura automáticamente
     * Si domain ya existe → valida que coincida
     */
    private function validateDomain($license, $domain) {
        $normalizedDomain = $this->normalizeDomain($domain);
        
        // Si la licencia NO tiene dominio asignado → CAPTURAR AUTOMÁTICAMENTE
        if (empty($license['domain'])) {
            $this->captureDomain($license['id'], $normalizedDomain);
            
            return [
                'valid' => true,
                'domain_captured' => $normalizedDomain
            ];
        }
        
        // Si ya tiene dominio → VALIDAR que coincida
        $licenseDomain = $this->normalizeDomain($license['domain']);
        
        if ($normalizedDomain === $licenseDomain) {
            return ['valid' => true];
        }
        
        // Dominios no coinciden
        return [
            'valid' => false,
            'reason' => "License key is registered to domain '{$license['domain']}'. Contact support to change domain."
        ];
    }
    
    /**
     * Capturar dominio automáticamente
     */
    private function captureDomain($licenseId, $domain) {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE " . DB_PREFIX . "licenses 
                SET domain = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$domain, $licenseId]);
            
            // Log de captura
            error_log("API Claude V4: Domain captured automatically - License ID: {$licenseId}, Domain: {$domain}");
            
        } catch (Exception $e) {
            error_log("API Claude V4: Error capturing domain - " . $e->getMessage());
        }
    }
    
    /**
     * Normalizar dominio
     * 
     * Limpia www, http, https, barras finales
     */
    private function normalizeDomain($domain) {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);
        $domain = rtrim($domain, '/');
        
        // Eliminar path si existe (solo quedarse con el dominio)
        $parts = explode('/', $domain);
        $domain = $parts[0];
        
        return $domain;
    }
    
    /**
     * Verificar si una licencia puede usar más tokens
     */
    public function canUseTokens($license, $tokensToUse = 0) {
        $available = $license['tokens_limit'] - $license['tokens_used_this_period'];
        return $available >= $tokensToUse;
    }
    
    /**
     * Validar solo licencia (sin dominio)
     * Útil para endpoints admin
     */
    public function validateLicenseOnly($licenseKey) {
        return $this->validate($licenseKey, null);
    }
}
