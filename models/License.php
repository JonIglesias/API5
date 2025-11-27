<?php
/**
 * License Model
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class License {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener licencia por key
     */
    public function findByKey($licenseKey) {
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses WHERE license_key = ?";
        return $this->db->fetchOne($sql, [$licenseKey]);
    }
    
    /**
     * Obtener licencia por ID de suscripción WooCommerce
     */
    public function findBySubscriptionId($subscriptionId) {
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses WHERE woo_subscription_id = ?";
        return $this->db->fetchOne($sql, [$subscriptionId]);
    }
    
    /**
     * Crear nueva licencia
     */
    public function create($data) {
        // Generar license_key si no existe
        if (!isset($data['license_key'])) {
            $data['license_key'] = $this->generateLicenseKey($data['plan_id'] ?? 'basic');
        }
        
        // Timestamps
        $data['created_at'] = date(DATE_FORMAT);
        $data['updated_at'] = date(DATE_FORMAT);
        
        return $this->db->insert('licenses', $data);
    }
    
    /**
     * Actualizar licencia
     */
    public function update($licenseId, $data) {
        $data['updated_at'] = date(DATE_FORMAT);
        return $this->db->update('licenses', $data, 'id = ?', [$licenseId]);
    }
    
    /**
     * Incrementar uso de tokens
     */
    public static function incrementTokens($licenseId, $tokens) {
        $instance = new self();
        $sql = "UPDATE " . DB_PREFIX . "licenses 
                SET tokens_used_this_period = tokens_used_this_period + ? 
                WHERE id = ?";
        return $instance->db->query($sql, [$tokens, $licenseId]);
    }
    
    /**
     * Resetear tokens del periodo
     */
    public function resetPeriodTokens($licenseId) {
        $data = [
            'tokens_used_this_period' => 0,
            'period_starts_at' => date(DATE_FORMAT),
            'updated_at' => date(DATE_FORMAT)
        ];
        return $this->db->update('licenses', $data, 'id = ?', [$licenseId]);
    }
    
    /**
     * Obtener licencias críticas (próximas a expirar o nuevas)
     */
    public function getCriticalLicenses() {
        $criticalDate = date('Y-m-d H:i:s', strtotime('+' . CRITICAL_DAYS_BEFORE_EXPIRY . ' days'));
        $newLicenseDate = date('Y-m-d H:i:s', strtotime('-' . NEW_LICENSE_AGE_HOURS . ' hours'));
        
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses 
                WHERE status = 'active' 
                AND (
                    period_ends_at <= ? 
                    OR created_at >= ?
                )";
        
        return $this->db->fetchAll($sql, [$criticalDate, $newLicenseDate]);
    }
    
    /**
     * Obtener licencias normales
     */
    public function getRegularLicenses() {
        $criticalDate = date('Y-m-d H:i:s', strtotime('+' . CRITICAL_DAYS_BEFORE_EXPIRY . ' days'));
        $newLicenseDate = date('Y-m-d H:i:s', strtotime('-' . NEW_LICENSE_AGE_HOURS . ' hours'));
        
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses 
                WHERE status = 'active' 
                AND period_ends_at > ? 
                AND created_at < ?";
        
        return $this->db->fetchAll($sql, [$criticalDate, $newLicenseDate]);
    }
    
    /**
     * Obtener licencias inactivas
     */
    public function getInactiveLicenses() {
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses 
                WHERE status IN ('expired', 'cancelled', 'suspended')";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Verificar si licencia necesita sincronización
     */
    public function needsSync($license) {
        if (!$license['last_synced_at']) {
            return true;
        }
        
        $lastSync = strtotime($license['last_synced_at']);
        $elapsed = time() - $lastSync;
        
        // Críticas: cada 30 min
        if ($this->isCritical($license)) {
            return $elapsed > SYNC_CRITICAL_INTERVAL;
        }
        
        // Inactivas: cada 24h
        if (in_array($license['status'], ['expired', 'cancelled', 'suspended'])) {
            return $elapsed > SYNC_INACTIVE_INTERVAL;
        }
        
        // Normales: cada 6h
        return $elapsed > SYNC_REGULAR_INTERVAL;
    }
    
    /**
     * Verificar si licencia es crítica
     */
    private function isCritical($license) {
        // Nueva (< 48h)
        $createdAt = strtotime($license['created_at']);
        if ((time() - $createdAt) < (NEW_LICENSE_AGE_HOURS * 3600)) {
            return true;
        }
        
        // Próxima a expirar (< 7 días)
        $expiresAt = strtotime($license['period_ends_at']);
        if (($expiresAt - time()) < (CRITICAL_DAYS_BEFORE_EXPIRY * 86400)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generar license key
     */
    private function generateLicenseKey($planId) {
        $prefix = strtoupper($planId);
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        return "{$prefix}-{$year}-{$random}";
    }
    
    /**
     * Obtener estado del caché
     */
    public function getCacheStatus($license) {
        if (!$license['last_synced_at']) {
            return 'expired';
        }
        
        $lastSync = strtotime($license['last_synced_at']);
        $elapsed = time() - $lastSync;
        
        if ($elapsed < CACHE_FRESH_SECONDS) {
            return 'fresh';
        } elseif ($elapsed < CACHE_VALID_SECONDS) {
            return 'valid';
        } elseif ($elapsed < CACHE_STALE_SECONDS) {
            return 'stale';
        } else {
            return 'expired';
        }
    }
    
    /**
     * Obtener todas las licencias con paginación
     */
    public function getAll($page = 1, $perPage = 50, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['plan_id'])) {
            $where[] = "plan_id = ?";
            $params[] = $filters['plan_id'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses 
                {$whereClause}
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
}
