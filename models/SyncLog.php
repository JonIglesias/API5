<?php
/**
 * SyncLog Model
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class SyncLog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear registro de sync
     */
    public function create($data) {
        $data['created_at'] = date(DATE_FORMAT);
        return $this->db->insert('sync_logs', $data);
    }
    
    /**
     * Obtener logs de una licencia
     */
    public function getByLicense($licenseId, $limit = 50) {
        $sql = "SELECT * FROM " . DB_PREFIX . "sync_logs 
                WHERE license_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$licenseId, $limit]);
    }
    
    /**
     * Obtener logs recientes
     */
    public function getRecent($limit = 100) {
        $sql = "SELECT sl.*, l.license_key 
                FROM " . DB_PREFIX . "sync_logs sl
                LEFT JOIN " . DB_PREFIX . "licenses l ON sl.license_id = l.id
                ORDER BY sl.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Obtener estadísticas de sync
     */
    public function getStats($hours = 24) {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $sql = "SELECT 
                    sync_type,
                    status,
                    COUNT(*) as count
                FROM " . DB_PREFIX . "sync_logs 
                WHERE created_at >= ?
                GROUP BY sync_type, status";
        
        return $this->db->fetchAll($sql, [$since]);
    }
    
    /**
     * Contar fallos recientes
     */
    public function countRecentFailures($hours = 24) {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $sql = "SELECT COUNT(*) as count 
                FROM " . DB_PREFIX . "sync_logs 
                WHERE created_at >= ? 
                AND status = 'failed'";
        
        $result = $this->db->fetchOne($sql, [$since]);
        return $result['count'] ?? 0;
    }
}
