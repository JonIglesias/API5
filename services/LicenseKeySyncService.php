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
require_once API_BASE_DIR . '/services/AlertService.php';

class LicenseKeySyncService {
    private $wc;
    private $db;

    // Configuración de reintentos (usar config o fallback)
    const MAX_ATTEMPTS_FALLBACK = 5;              // Fallback si no está en config
    const RETRY_DELAY_SECONDS = 300;     // 5 minutos entre reintentos

    private function getMaxAttempts() {
        return defined('SYNC_MAX_ATTEMPTS') ? SYNC_MAX_ATTEMPTS : self::MAX_ATTEMPTS_FALLBACK;
    }

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
        $maxAttempts = $this->getMaxAttempts();
        if ($license['license_key_sync_attempts'] >= $maxAttempts && !$forceRetry) {
            Logger::sync('warning', 'Max sync attempts reached for license', [
                'license_id' => $licenseId,
                'attempts' => $license['license_key_sync_attempts'],
                'max_attempts' => $maxAttempts
            ]);

            // Enviar alerta por email
            AlertService::licenseMaxAttemptsReached([
                'license_id' => $licenseId,
                'license_key' => $license['license_key'],
                'user_email' => $license['user_email'],
                'order_id' => $license['woo_subscription_id'] ?? $license['last_order_id'] ?? null,
                'attempts' => $license['license_key_sync_attempts'],
                'last_attempt' => $license['license_key_last_sync_attempt'] ?? 'Never'
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

                    // Enviar nota al cliente con email
                    $this->sendCustomerNotification($orderId, $license['license_key']);

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
        $maxAttempts = $this->getMaxAttempts();

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
              AND (
                  (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
                  OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
              )
            ORDER BY created_at DESC
            LIMIT ?
        ", [$maxAttempts, self::RETRY_DELAY_SECONDS, $limit]);

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
        $maxAttempts = $this->getMaxAttempts();
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

        // Licencias pendientes (con order_id válido)
        $stats['pending'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND license_key_sync_attempts < ?
              AND (
                  (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
                  OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
              )
        ", [$maxAttempts])['count'];

        // Licencias que alcanzaron el máximo de intentos
        $stats['max_attempts'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND license_key_sync_attempts >= ?
        ", [$maxAttempts])['count'];

        // Licencias sin order_id válido (no se pueden sincronizar)
        $stats['no_order_id'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND (
                  (woo_subscription_id IS NULL OR woo_subscription_id = '' OR woo_subscription_id = 0)
                  AND (last_order_id IS NULL OR last_order_id = '' OR last_order_id = 0)
              )
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

    /**
     * Enviar notificación al cliente vía WooCommerce Customer Note
     * La nota con customer_note=true dispara automáticamente un email al cliente
     *
     * @param int $orderId ID del pedido en WooCommerce
     * @param string $licenseKey Clave de licencia
     */
    private function sendCustomerNotification($orderId, $licenseKey) {
        try {
            // Crear mensaje personalizado
            $message = "🔑 ¡Tu clave de licencia está lista!\n\n";
            $message .= "CLAVE DE LICENCIA: {$licenseKey}\n\n";
            $message .= "Guarda esta clave en un lugar seguro. La necesitarás para activar tu producto.\n\n";
            $message .= "También puedes consultar tu clave en cualquier momento accediendo a los detalles de este pedido desde tu cuenta.";

            // Enviar nota al cliente (customer_note=true envía email automáticamente)
            $result = $this->wc->createOrderNote($orderId, $message, true);

            // Verificar que se creó correctamente
            if (isset($result['id']) && $result['id'] > 0) {
                Logger::sync('info', 'Customer notification email sent successfully', [
                    'order_id' => $orderId,
                    'note_id' => $result['id'],
                    'license_key' => $licenseKey
                ]);
            } else {
                throw new Exception('Order note created but no ID returned');
            }

        } catch (Exception $e) {
            // Si falla el envío de la nota, registrar el error pero NO fallar la sincronización
            // La licencia ya está en WooCommerce y el cliente puede verla en su pedido
            Logger::sync('warning', 'Failed to send customer notification email', [
                'order_id' => $orderId,
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);

            // No lanzamos la excepción para que la sincronización se considere exitosa
        }
    }
}
