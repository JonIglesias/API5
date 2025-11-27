<?php
/**
 * Plan Model
 *
 * @version 4.01
 */

defined('API_ACCESS') or die('Direct access not permitted');

class Plan {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener plan por ID
     */
    public function findById($planId) {
        $sql = "SELECT * FROM " . DB_PREFIX . "plans WHERE id = ?";
        return $this->db->fetchOne($sql, [$planId]);
    }
    
    /**
     * Obtener plan por ID de producto WooCommerce
     */
    public function findByWooProductId($productId) {
        $sql = "SELECT * FROM " . DB_PREFIX . "plans WHERE woo_product_id = ?";
        return $this->db->fetchOne($sql, [$productId]);
    }
    
    /**
     * Obtener todos los planes activos
     */
    public function getActive() {
        $sql = "SELECT * FROM " . DB_PREFIX . "plans WHERE active = 1 ORDER BY tokens_per_month ASC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obtener todos los planes
     */
    public function getAll() {
        $sql = "SELECT * FROM " . DB_PREFIX . "plans ORDER BY tokens_per_month ASC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Crear plan
     */
    public function create($data) {
        $data['created_at'] = date(DATE_FORMAT);
        $data['updated_at'] = date(DATE_FORMAT);
        return $this->db->insert('plans', $data);
    }
    
    /**
     * Actualizar plan
     */
    public function update($planId, $data) {
        $data['updated_at'] = date(DATE_FORMAT);
        return $this->db->update('plans', $data, 'id = ?', [$planId]);
    }
    
    /**
     * Eliminar plan
     */
    public function delete($planId) {
        return $this->db->delete('plans', 'id = ?', [$planId]);
    }
}
