<?php
/**
 * UsageTracking Model
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class UsageTracking {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear registro de uso (método estático para compatibilidad)
     */
    public static function create($data) {
        $instance = new self();
        return $instance->track($data);
    }
    
    /**
     * Registrar uso - método estático V5
     */
    public static function record($licenseId, $operationType, $tokensTotal, $tokensInput = 0, $tokensOutput = 0, $campaignId = null, $campaignName = null, $batchId = null) {
        $instance = new self();
        
        // batch_type = 'queue' SOLO si viene batch_id
        $batchType = $batchId ? 'queue' : null;
        
        return $instance->track([
            'license_id' => $licenseId,
            'operation_type' => $operationType,
            'tokens_total' => $tokensTotal,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'batch_id' => $batchId,
            'batch_type' => $batchType
        ]);
    }
    
    /**
     * Registrar uso de tokens
     */
    public function track($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['sync_status_at_time'] = $data['sync_status_at_time'] ?? 'fresh';
        
        // ⭐ CALCULAR COSTOS
        $model = $data['model'] ?? 'gpt-4o-mini';
        require_once API_BASE_DIR . '/services/ModelPricingService.php';
        $prices = ModelPricingService::getPrices($model);
        
        // Si tenemos tokens separados, usar esos
        if (isset($data['tokens_input']) && isset($data['tokens_output']) 
            && ($data['tokens_input'] > 0 || $data['tokens_output'] > 0)) {
            
            $data['cost_input'] = ($data['tokens_input'] / 1000) * $prices['input'];
            $data['cost_output'] = ($data['tokens_output'] / 1000) * $prices['output'];
            $data['cost_total'] = $data['cost_input'] + $data['cost_output'];
            
        } 
        // Si solo tenemos tokens_total, calcular 50/50
        elseif (isset($data['tokens_total']) && $data['tokens_total'] > 0) {
            
            $tokensInput = floor($data['tokens_total'] / 2);
            $tokensOutput = ceil($data['tokens_total'] / 2);
            
            $data['tokens_input'] = $tokensInput;
            $data['tokens_output'] = $tokensOutput;
            $data['cost_input'] = ($tokensInput / 1000) * $prices['input'];
            $data['cost_output'] = ($tokensOutput / 1000) * $prices['output'];
            $data['cost_total'] = $data['cost_input'] + $data['cost_output'];
        }
        
        return $this->db->insert('usage_tracking', $data);
    }
    
    /**
     * DEPRECATED: Usar ModelPricingService en su lugar
     */
    private static function getModelPrices($model) {
        require_once API_BASE_DIR . '/services/ModelPricingService.php';
        return ModelPricingService::getPrices($model);
    }
    
    /**
     * Obtener historial de uso de una licencia
     */
    public function getByLicense($licenseId, $limit = 100) {
        $sql = "SELECT * FROM " . DB_PREFIX . "usage_tracking 
                WHERE license_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$licenseId, $limit]);
    }
    
    /**
     * Obtener uso del periodo actual
     */
    public function getCurrentPeriodUsage($licenseId, $periodStart) {
        $sql = "SELECT 
                    operation_type,
                    COUNT(*) as count,
                    SUM(tokens_total) as total_tokens
                FROM " . DB_PREFIX . "usage_tracking 
                WHERE license_id = ? 
                AND created_at >= ?
                GROUP BY operation_type";
        
        return $this->db->fetchAll($sql, [$licenseId, $periodStart]);
    }
    
    /**
     * Obtener estadísticas generales
     */
    public function getStats($startDate = null, $endDate = null) {
        $where = [];
        $params = [];
        
        if ($startDate) {
            $where[] = "created_at >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $where[] = "created_at <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT 
                    COUNT(*) as total_operations,
                    SUM(tokens_total) as total_tokens,
                    COUNT(DISTINCT license_id) as unique_licenses,
                    operation_type
                FROM " . DB_PREFIX . "usage_tracking 
                {$whereClause}
                GROUP BY operation_type";
        
        return $this->db->fetchAll($sql, $params);
    }
}
