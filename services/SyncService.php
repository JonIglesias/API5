<?php
/**
 * SyncService - Sincronización con WooCommerce
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/WooCommerceClient.php';
require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/models/Plan.php';
require_once API_BASE_DIR . '/models/SyncLog.php';

class SyncService {
    private $wc;
    private $licenseModel;
    private $planModel;
    private $syncLogModel;
    
    public function __construct() {
        $this->wc = new WooCommerceClient();
        $this->licenseModel = new License();
        $this->planModel = new Plan();
        $this->syncLogModel = new SyncLog();
    }
    
    /**
     * Sincronizar una licencia específica
     */
    public function syncLicense($licenseId, $syncType = 'manual') {
        $license = $this->licenseModel->findByKey($licenseId);
        
        if (!$license) {
            return [
                'success' => false,
                'message' => 'License not found'
            ];
        }
        
        if (!$license['woo_subscription_id']) {
            return [
                'success' => false,
                'message' => 'No WooCommerce subscription linked'
            ];
        }
        
        try {
            // Obtener suscripción de WooCommerce
            $subscription = $this->wc->getSubscription($license['woo_subscription_id']);
            
            // Detectar cambios
            $changes = $this->detectChanges($license, $subscription);
            
            if (empty($changes)) {
                $this->logSync($license['id'], $syncType, 'no_changes', null, $subscription);
                
                // Actualizar last_synced_at
                $this->licenseModel->update($license['id'], [
                    'last_synced_at' => date(DATE_FORMAT)
                ]);
                
                return [
                    'success' => true,
                    'message' => 'No changes detected'
                ];
            }
            
            // Aplicar cambios
            $this->applyChanges($license['id'], $changes, $subscription);
            
            $this->logSync($license['id'], $syncType, 'success', $changes, $subscription);
            
            Logger::sync('info', 'License synced successfully', [
                'license_id' => $license['id'],
                'changes' => $changes
            ]);
            
            return [
                'success' => true,
                'message' => 'Sync completed',
                'changes' => $changes
            ];
            
        } catch (Exception $e) {
            $this->logSync($license['id'], $syncType, 'failed', null, null, $e->getMessage());
            
            Logger::sync('error', 'Sync failed', [
                'license_id' => $license['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Detectar cambios entre licencia local y suscripción WooCommerce
     */
    private function detectChanges($license, $subscription) {
        $changes = [];
        
        // Status
        $wooStatus = $this->mapWooStatus($subscription['status']);
        if ($license['status'] !== $wooStatus) {
            $changes['status'] = [
                'old' => $license['status'],
                'new' => $wooStatus
            ];
        }
        
        // Next payment date
        if (isset($subscription['next_payment_date'])) {
            $newDate = date(DATE_FORMAT, strtotime($subscription['next_payment_date']));
            if ($license['period_ends_at'] !== $newDate) {
                $changes['period_ends_at'] = [
                    'old' => $license['period_ends_at'],
                    'new' => $newDate
                ];
            }
        }
        
        // Plan (si cambió el producto)
        if (isset($subscription['line_items'][0]['product_id'])) {
            $productId = $subscription['line_items'][0]['product_id'];
            $plan = $this->planModel->findByWooProductId($productId);
            
            if ($plan && $license['plan_id'] !== $plan['id']) {
                $changes['plan_id'] = [
                    'old' => $license['plan_id'],
                    'new' => $plan['id']
                ];
                
                $changes['tokens_limit'] = [
                    'old' => $license['tokens_limit'],
                    'new' => $plan['tokens_per_month']
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Aplicar cambios a la licencia
     */
    private function applyChanges($licenseId, $changes, $subscription) {
        $updateData = [
            'last_synced_at' => date(DATE_FORMAT)
        ];
        
        foreach ($changes as $field => $change) {
            $updateData[$field] = $change['new'];
        }
        
        $this->licenseModel->update($licenseId, $updateData);
    }
    
    /**
     * Mapear estado de WooCommerce a nuestro sistema
     */
    private function mapWooStatus($wooStatus) {
        $map = [
            'active' => 'active',
            'on-hold' => 'suspended',
            'pending' => 'suspended',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            'pending-cancel' => 'active'
        ];
        
        return $map[$wooStatus] ?? 'suspended';
    }
    
    /**
     * Registrar sync en log
     */
    private function logSync($licenseId, $syncType, $status, $changes = null, $wooResponse = null, $error = null) {
        $this->syncLogModel->create([
            'license_id' => $licenseId,
            'sync_type' => $syncType,
            'status' => $status,
            'changes_detected' => $changes ? json_encode($changes) : null,
            'woo_response' => $wooResponse ? json_encode($wooResponse) : null,
            'error_message' => $error
        ]);
    }
    
    /**
     * Sincronizar todas las licencias activas
     */
    public function syncAllActive() {
        $licenses = $this->licenseModel->getAll(1, 1000, ['status' => 'active']);
        
        $results = [
            'total' => count($licenses),
            'success' => 0,
            'failed' => 0,
            'no_changes' => 0
        ];
        
        foreach ($licenses as $license) {
            $result = $this->syncLicense($license['license_key'], 'manual');
            
            if ($result['success']) {
                if (isset($result['changes']) && !empty($result['changes'])) {
                    $results['success']++;
                } else {
                    $results['no_changes']++;
                }
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
}
