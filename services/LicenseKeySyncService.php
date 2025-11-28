<?php
/**
 * LicenseKeySyncService - Sincronizar license_keys a WooCommerce con reintentos
 *
 * Este servicio se asegura de que todas las license_keys se envíen a WooCommerce
 * de forma confiable, con reintentos automáticos si falla el primer intento.
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/WooCommerceClient.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/core/Logger.php';

class LicenseKeySyncService {
    private $wc;
    private $db;

    // Configuración de reintentos
    const MAX_ATTEMPTS = 5;              // Máximo 5 intentos
    const RETRY_DELAY_SECONDS = 300;     // 5 minutos entre reintentos

    public function __construct() {
        $this->wc = new WooCommerceClient();
        $this->db = Database::getInstance();
    }

    /**
     * Sincronizar license_key de una licencia específica a WooCommerce
     *
     * @param int $licenseId ID de la licencia
     * @param bool $forceRetry Forzar reintento aunque ya se haya alcanzado el máximo
     * @return array Resultado de la sincronización
     */
    public function syncLicenseKey($licenseId, $forceRetry = false) {
        // Obtener licencia
        $license = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "licenses WHERE id = ?",
            [$licenseId]
        );

        if (!$license) {
            return [
                'success' => false,
                'message' => 'License not found'
            ];
        }

        // Si ya está sincronizada, no hacer nada
        if ($license['license_key_synced_to_woo'] == 1 && !$forceRetry) {
            return [
                'success' => true,
                'message' => 'Already synced',
                'skipped' => true
            ];
        }

        // Verificar si debe reintentar (máximo de intentos)
        if ($license['license_key_sync_attempts'] >= self::MAX_ATTEMPTS && !$forceRetry) {
            Logger::sync('warning', 'Max sync attempts reached for license', [
                'license_id' => $licenseId,
                'attempts' => $license['license_key_sync_attempts']
            ]);

            return [
                'success' => false,
                'message' => 'Max attempts reached',
                'max_attempts' => true
            ];
        }

        // Verificar delay entre reintentos
        if ($license['license_key_last_sync_attempt']) {
            $lastAttempt = strtotime($license['license_key_last_sync_attempt']);
            $timeSinceLastAttempt = time() - $lastAttempt;

            if ($timeSinceLastAttempt < self::RETRY_DELAY_SECONDS && !$forceRetry) {
                return [
                    'success' => false,
                    'message' => 'Too soon to retry',
                    'wait_seconds' => self::RETRY_DELAY_SECONDS - $timeSinceLastAttempt
                ];
            }
        }

        // Verificar que tenga order_id
        $orderId = $license['woo_subscription_id'] ?? $license['last_order_id'] ?? null;

        if (!$orderId) {
            Logger::sync('warning', 'License has no WooCommerce order ID', [
                'license_id' => $licenseId
            ]);

            return [
                'success' => false,
                'message' => 'No WooCommerce order ID'
            ];
        }

        // Incrementar contador de intentos
        $this->db->query("
            UPDATE " . DB_PREFIX . "licenses
            SET license_key_sync_attempts = license_key_sync_attempts + 1,
                license_key_last_sync_attempt = NOW()
            WHERE id = ?
        ", [$licenseId]);

        // Intentar enviar a WooCommerce
        try {
            $result = $this->wc->updateOrderMeta($orderId, '_license_key', $license['license_key']);

            // Verificar que se guardó correctamente
            if (isset($result['meta_data']) && is_array($result['meta_data'])) {
                $found = false;
                foreach ($result['meta_data'] as $meta) {
                    if ($meta['key'] === '_license_key' && $meta['value'] === $license['license_key']) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    // Marcar como sincronizada
                    $this->db->query("
                        UPDATE " . DB_PREFIX . "licenses
                        SET license_key_synced_to_woo = 1
                        WHERE id = ?
                    ", [$licenseId]);

                    Logger::sync('info', 'License key synced to WooCommerce successfully', [
                        'license_id' => $licenseId,
                        'order_id' => $orderId,
                        'license_key' => $license['license_key'],
                        'attempts' => $license['license_key_sync_attempts'] + 1
                    ]);

                    return [
                        'success' => true,
                        'message' => 'License key synced successfully',
                        'attempts' => $license['license_key_sync_attempts'] + 1
                    ];
                }
            }

            throw new Exception('License key not found in WooCommerce response');

        } catch (Exception $e) {
            Logger::sync('error', 'Failed to sync license key to WooCommerce', [
                'license_id' => $licenseId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'attempts' => $license['license_key_sync_attempts'] + 1
            ]);

            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'attempts' => $license['license_key_sync_attempts'] + 1,
                'will_retry' => ($license['license_key_sync_attempts'] + 1) < self::MAX_ATTEMPTS
            ];
        }
    }

    /**
     * Sincronizar todas las licencias pendientes
     *
     * @param int $limit Límite de licencias a procesar
     * @return array Resumen de la sincronización
     */
    public function syncPendingLicenseKeys($limit = 100) {
        // Obtener licencias que necesitan sincronización
        $licenses = $this->db->query("
            SELECT id, license_key, license_key_sync_attempts, woo_subscription_id, last_order_id
            FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND license_key_sync_attempts < ?
              AND (
                  license_key_last_sync_attempt IS NULL
                  OR license_key_last_sync_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)
              )
              AND (woo_subscription_id IS NOT NULL OR last_order_id IS NOT NULL)
            ORDER BY created_at DESC
            LIMIT ?
        ", [self::MAX_ATTEMPTS, self::RETRY_DELAY_SECONDS, $limit]);

        $results = [
            'total' => count($licenses),
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($licenses as $license) {
            $result = $this->syncLicenseKey($license['id']);

            if ($result['success']) {
                $results['synced']++;
            } elseif (isset($result['skipped']) && $result['skipped']) {
                $results['skipped']++;
            } else {
                $results['failed']++;
            }
        }

        Logger::sync('info', 'Batch license key sync completed', $results);

        return $results;
    }

    /**
     * Obtener estadísticas de sincronización
     *
     * @return array Estadísticas
     */
    public function getSyncStats() {
        $stats = [];

        // Total de licencias
        $stats['total_licenses'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
        ")['count'];

        // Licencias sincronizadas
        $stats['synced'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 1
        ")['count'];

        // Licencias pendientes
        $stats['pending'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND license_key_sync_attempts < ?
              AND (woo_subscription_id IS NOT NULL OR last_order_id IS NOT NULL)
        ", [self::MAX_ATTEMPTS])['count'];

        // Licencias que alcanzaron el máximo de intentos
        $stats['max_attempts'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND license_key_sync_attempts >= ?
        ", [self::MAX_ATTEMPTS])['count'];

        // Licencias sin order_id (no se pueden sincronizar)
        $stats['no_order_id'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND woo_subscription_id IS NULL
              AND last_order_id IS NULL
        ")['count'];

        return $stats;
    }

    /**
     * Resetear el estado de sincronización de una licencia
     * (útil para forzar reintento)
     *
     * @param int $licenseId ID de la licencia
     */
    public function resetSyncStatus($licenseId) {
        $this->db->query("
            UPDATE " . DB_PREFIX . "licenses
            SET license_key_synced_to_woo = 0,
                license_key_sync_attempts = 0,
                license_key_last_sync_attempt = NULL
            WHERE id = ?
        ", [$licenseId]);

        Logger::sync('info', 'License sync status reset', [
            'license_id' => $licenseId
        ]);
    }
}
