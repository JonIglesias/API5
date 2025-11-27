<?php
/**
 * Gestor de títulos en colas/campañas
 *
 * Permite evitar repeticiones de títulos dentro de la misma cola/campaña
 * sin afectar otras campañas o usuarios. Ideal para generación masiva.
 *
 * USO:
 * - Cuando se genera un título en una campaña, se guarda aquí
 * - Antes de generar un nuevo título, se consultan los títulos previos
 * - Los títulos previos se inyectan al prompt para evitar repeticiones
 *
 * CARACTERÍSTICAS:
 * - Scope por campaign_id (colas independientes)
 * - Auto-limpieza: títulos >24h se eliminan automáticamente
 * - Lightweight: solo almacena texto, sin embeddings
 *
 * @package AutoPostsAPI
 * @version 4.16
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Database.php';

class TitleQueueManager {

    /**
     * Agregar título a la cola actual
     *
     * @param string $campaignId ID de la campaña/cola
     * @param int $licenseId ID de la licencia
     * @param string $title Título generado
     * @return bool True si se guardó correctamente
     */
    public static function addTitle($campaignId, $licenseId, $title) {
        if (!$campaignId || !$title) {
            return false;
        }

        $db = Database::getInstance();

        try {
            $db->query("
                INSERT INTO " . DB_PREFIX . "queue_titles
                (campaign_id, license_id, title_text, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$campaignId, $licenseId, trim($title)]);

            return true;

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error adding title to queue - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener títulos previos de la cola
     *
     * Retorna los últimos N títulos generados en esta campaña,
     * ordenados del más reciente al más antiguo.
     *
     * @param string $campaignId ID de la campaña/cola
     * @param int $limit Número máximo de títulos a retornar (default: 10)
     * @return array Lista de títulos (strings)
     */
    public static function getTitles($campaignId, $limit = 10) {
        if (!$campaignId) {
            return [];
        }

        $db = Database::getInstance();

        try {
            $titles = $db->query("
                SELECT title_text
                FROM " . DB_PREFIX . "queue_titles
                WHERE campaign_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ", [$campaignId, (int)$limit]);

            return array_column($titles, 'title_text');

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error fetching queue titles - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar si un título ya existe en la cola (duplicado exacto)
     *
     * Útil para validación antes de guardar. Compara sin distinguir
     * mayúsculas/minúsculas.
     *
     * @param string $campaignId ID de la campaña
     * @param string $title Título a verificar
     * @return bool True si el título ya existe en esta cola
     */
    public static function titleExists($campaignId, $title) {
        if (!$campaignId || !$title) {
            return false;
        }

        $db = Database::getInstance();

        try {
            $result = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM " . DB_PREFIX . "queue_titles
                WHERE campaign_id = ?
                AND LOWER(title_text) = LOWER(?)
            ", [$campaignId, trim($title)]);

            return ($result['count'] ?? 0) > 0;

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error checking title existence - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpiar todos los títulos de una campaña específica
     *
     * Útil si el usuario cancela/reinicia la campaña y quiere
     * empezar desde cero con los títulos.
     *
     * @param string $campaignId ID de la campaña
     * @return bool True si se eliminaron correctamente
     */
    public static function clearQueue($campaignId) {
        if (!$campaignId) {
            return false;
        }

        $db = Database::getInstance();

        try {
            $db->query("
                DELETE FROM " . DB_PREFIX . "queue_titles
                WHERE campaign_id = ?
            ", [$campaignId]);

            return true;

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error clearing queue - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de una cola específica
     *
     * Retorna información sobre cuántos títulos se han generado,
     * cuándo fue el primero y el último.
     *
     * @param string $campaignId ID de la campaña
     * @return array ['count' => int, 'first_created' => datetime, 'last_created' => datetime]
     */
    public static function getQueueStats($campaignId) {
        if (!$campaignId) {
            return ['count' => 0, 'first_created' => null, 'last_created' => null];
        }

        $db = Database::getInstance();

        try {
            $stats = $db->fetchOne("
                SELECT
                    COUNT(*) as count,
                    MIN(created_at) as first_created,
                    MAX(created_at) as last_created
                FROM " . DB_PREFIX . "queue_titles
                WHERE campaign_id = ?
            ", [$campaignId]);

            return [
                'count' => (int)($stats['count'] ?? 0),
                'first_created' => $stats['first_created'] ?? null,
                'last_created' => $stats['last_created'] ?? null
            ];

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error fetching queue stats - " . $e->getMessage());
            return ['count' => 0, 'first_created' => null, 'last_created' => null];
        }
    }

    /**
     * Obtener número total de títulos en todas las colas activas
     *
     * Útil para monitoreo y estadísticas generales.
     *
     * @return int Número total de títulos almacenados
     */
    public static function getTotalTitlesCount() {
        $db = Database::getInstance();

        try {
            $result = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM " . DB_PREFIX . "queue_titles
            ");

            return (int)($result['count'] ?? 0);

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error counting total titles - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpieza manual de títulos antiguos
     *
     * Elimina títulos más antiguos que el número de horas especificado.
     * Normalmente esto lo hace el event scheduler automáticamente.
     *
     * @param int $hoursOld Antigüedad en horas (default: 24)
     * @return int Número de títulos eliminados
     */
    public static function cleanupOldTitles($hoursOld = 24) {
        $db = Database::getInstance();

        try {
            $db->query("
                DELETE FROM " . DB_PREFIX . "queue_titles
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
            ", [(int)$hoursOld]);

            // Obtener número de filas afectadas depende de la implementación de Database
            // Por ahora retornamos true para indicar éxito
            return true;

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error cleaning up old titles - " . $e->getMessage());
            return false;
        }
    }
}
