<?php
/**
 * AlertService - Sistema de alertas por email para errores críticos
 *
 * Envía notificaciones por email cuando ocurren errores críticos como:
 * - Licencias que no se pueden sincronizar tras múltiples intentos
 * - Errores en el auto-sync
 * - Otros fallos críticos del sistema
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Logger.php';

class AlertService {

    /**
     * Enviar alerta de licencia no sincronizada
     */
    public static function licenseMaxAttemptsReached($licenseData) {
        if (!defined('ENABLE_ALERTS') || !ENABLE_ALERTS) {
            return;
        }

        $subject = '🚨 ALERTA: Licencia no sincronizada tras múltiples intentos';

        $message = "Se ha alcanzado el máximo de intentos de sincronización para una licencia.\n\n";
        $message .= "DETALLES DE LA LICENCIA:\n";
        $message .= "========================\n";
        $message .= "License Key: {$licenseData['license_key']}\n";
        $message .= "Email Usuario: {$licenseData['user_email']}\n";
        $message .= "Order ID: " . ($licenseData['order_id'] ?? 'N/A') . "\n";
        $message .= "Intentos realizados: {$licenseData['attempts']}\n";
        $message .= "Último intento: {$licenseData['last_attempt']}\n\n";

        $message .= "POSIBLES CAUSAS:\n";
        $message .= "========================\n";
        $message .= "- Order ID inválido o inexistente en WooCommerce\n";
        $message .= "- Problemas de conexión con WooCommerce API\n";
        $message .= "- Credenciales API incorrectas o expiradas\n";
        $message .= "- Permisos insuficientes en WooCommerce\n\n";

        $message .= "ACCIÓN REQUERIDA:\n";
        $message .= "========================\n";
        $message .= "1. Revisa los logs en: logs/sync.log\n";
        $message .= "2. Verifica que el Order ID exista en WooCommerce\n";
        $message .= "3. Comprueba las credenciales de la API de WooCommerce\n";
        $message .= "4. Si el problema persiste, resetea el estado de sincronización:\n";
        $message .= "   UPDATE api_licenses SET license_key_sync_attempts = 0 WHERE id = {$licenseData['license_id']};\n\n";

        $message .= "Para más información, consulta los logs de sincronización.\n";

        self::sendAlert($subject, $message, 'license_sync_failed');
    }

    /**
     * Enviar alerta de error en auto-sync
     */
    public static function autoSyncFailed($errorData) {
        if (!defined('ENABLE_ALERTS') || !ENABLE_ALERTS) {
            return;
        }

        $subject = '🚨 ALERTA: Error crítico en Auto-Sync';

        $message = "Ha ocurrido un error crítico durante la sincronización automática.\n\n";
        $message .= "DETALLES DEL ERROR:\n";
        $message .= "========================\n";
        $message .= "Tipo: {$errorData['type']}\n";
        $message .= "Mensaje: {$errorData['message']}\n";
        $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

        if (isset($errorData['context'])) {
            $message .= "CONTEXTO:\n";
            $message .= "========================\n";
            $message .= print_r($errorData['context'], true) . "\n\n";
        }

        $message .= "ACCIÓN REQUERIDA:\n";
        $message .= "========================\n";
        $message .= "1. Revisa los logs en: logs/sync.log\n";
        $message .= "2. Verifica la conexión con WooCommerce\n";
        $message .= "3. Comprueba que los planes tienen woo_product_id configurado\n";
        $message .= "4. Ejecuta diagnósticos: php diagnose-orders.php\n\n";

        self::sendAlert($subject, $message, 'auto_sync_failed');
    }

    /**
     * Enviar alerta de múltiples licencias sin sincronizar
     */
    public static function multipleLicensesPending($count, $details = []) {
        if (!defined('ENABLE_ALERTS') || !ENABLE_ALERTS) {
            return;
        }

        // Solo alertar si hay muchas licencias pendientes (más de 10)
        if ($count < 10) {
            return;
        }

        $subject = "⚠️ ALERTA: {$count} licencias pendientes de sincronizar";

        $message = "Hay {$count} licencias que no se han podido sincronizar a WooCommerce.\n\n";
        $message .= "Esto puede indicar un problema sistemático que requiere atención.\n\n";

        $message .= "RESUMEN:\n";
        $message .= "========================\n";
        $message .= "Total pendientes: {$count}\n";

        if (!empty($details['max_attempts'])) {
            $message .= "Con máximo de intentos: {$details['max_attempts']}\n";
        }
        if (!empty($details['no_order_id'])) {
            $message .= "Sin Order ID válido: {$details['no_order_id']}\n";
        }

        $message .= "\nACCIÓN REQUERIDA:\n";
        $message .= "========================\n";
        $message .= "1. Ejecuta diagnóstico: php diagnose-sync.php\n";
        $message .= "2. Revisa los logs: tail -f logs/sync.log\n";
        $message .= "3. Verifica la configuración de WooCommerce API\n\n";

        self::sendAlert($subject, $message, 'multiple_pending');
    }

    /**
     * Enviar alerta genérica
     */
    private static function sendAlert($subject, $message, $type = 'generic') {
        try {
            $to = defined('ALERT_EMAIL') ? ALERT_EMAIL : 'jon@bocetos.com';

            // Headers para email HTML
            $headers = [
                'From: API Claude Sync <noreply@bocetosmarketing.com>',
                'Reply-To: ' . $to,
                'X-Mailer: PHP/' . phpversion(),
                'X-Alert-Type: ' . $type,
                'Content-Type: text/plain; charset=UTF-8'
            ];

            // Añadir timestamp al mensaje
            $fullMessage = "ALERTA GENERADA: " . date('Y-m-d H:i:s') . "\n";
            $fullMessage .= str_repeat('=', 60) . "\n\n";
            $fullMessage .= $message;
            $fullMessage .= "\n" . str_repeat('=', 60) . "\n";
            $fullMessage .= "Esta es una alerta automática del sistema de sincronización de licencias.\n";
            $fullMessage .= "Servidor: " . gethostname() . "\n";
            $fullMessage .= "Entorno: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'production') . "\n";

            // Enviar email
            $sent = mail($to, $subject, $fullMessage, implode("\r\n", $headers));

            if ($sent) {
                Logger::sync('info', 'Alert email sent', [
                    'type' => $type,
                    'to' => $to,
                    'subject' => $subject
                ]);
            } else {
                Logger::sync('error', 'Failed to send alert email', [
                    'type' => $type,
                    'to' => $to,
                    'subject' => $subject
                ]);
            }

        } catch (Exception $e) {
            Logger::sync('error', 'Exception sending alert email', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verificar y alertar sobre licencias con problemas
     * Se ejecuta periódicamente desde el auto-sync
     */
    public static function checkAndAlertPendingLicenses() {
        if (!defined('ENABLE_ALERTS') || !ENABLE_ALERTS) {
            return;
        }

        try {
            $db = Database::getInstance();
            $maxAttempts = defined('SYNC_MAX_ATTEMPTS') ? SYNC_MAX_ATTEMPTS : 5;

            // Obtener estadísticas de licencias pendientes
            $stats = [];

            // Licencias con max attempts
            $result = $db->fetchOne("
                SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
                WHERE license_key_synced_to_woo = 0
                  AND license_key_sync_attempts >= ?
            ", [$maxAttempts]);
            $stats['max_attempts'] = $result['count'] ?? 0;

            // Licencias sin order ID
            $result = $db->fetchOne("
                SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses
                WHERE license_key_synced_to_woo = 0
                  AND (woo_subscription_id IS NULL OR woo_subscription_id = '' OR woo_subscription_id = 0)
                  AND (last_order_id IS NULL OR last_order_id = '' OR last_order_id = 0)
            ");
            $stats['no_order_id'] = $result['count'] ?? 0;

            // Total pendientes
            $total = $stats['max_attempts'] + $stats['no_order_id'];

            if ($total >= 10) {
                self::multipleLicensesPending($total, $stats);
            }

        } catch (Exception $e) {
            Logger::sync('error', 'Error checking pending licenses for alerts', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
