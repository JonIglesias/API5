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
 * CARACTERÍSTICAS v4.17:
 * - Scope por campaign_id (colas independientes)
 * - Auto-creación de tabla si no existe
 * - Limpieza automática en cada request (títulos >24h)
 * - Detección de similitud con Levenshtein + similar_text
 * - Lightweight: solo almacena texto, sin embeddings
 *
 * @package AutoPostsAPI
 * @version 4.17
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Database.php';

class TitleQueueManager {

    /**
     * Auto-crear tabla si no existe
     *
     * Se ejecuta automáticamente en cada operación para asegurar
     * que la tabla existe antes de usarla.
     *
     * @return bool True si la tabla existe o se creó correctamente
     */
    private static function ensureTableExists() {
        $db = Database::getInstance();

        try {
            // Verificar si la tabla existe
            $result = $db->fetchOne("
                SHOW TABLES LIKE '" . DB_PREFIX . "queue_titles'
            ");

            if (!$result) {
                // Crear tabla
                $db->query("
                    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "queue_titles` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `campaign_id` VARCHAR(100) NOT NULL COMMENT 'ID de la campaña/cola',
                      `license_id` INT UNSIGNED NOT NULL COMMENT 'ID de la licencia propietaria',
                      `title_text` VARCHAR(500) NOT NULL COMMENT 'Texto del título generado',
                      `created_at` DATETIME NOT NULL COMMENT 'Fecha de generación',
                      PRIMARY KEY (`id`),
                      KEY `campaign_lookup` (`campaign_id`, `created_at`),
                      KEY `cleanup` (`created_at`),
                      FOREIGN KEY (`license_id`) REFERENCES `" . DB_PREFIX . "licenses`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Almacenamiento temporal de títulos generados en colas. TTL: 24 horas'
                ");

                error_log("TitleQueueManager: Table created successfully");
            }

            return true;

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error ensuring table exists - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpieza automática de títulos antiguos (>24h)
     *
     * Se ejecuta automáticamente en cada request de una nueva cola
     * para mantener la tabla limpia sin depender únicamente del event scheduler.
     *
     * @param int $hoursOld Antigüedad en horas (default: 24)
     * @return bool True si la limpieza fue exitosa
     */
    public static function autoCleanup($hoursOld = 24) {
        self::ensureTableExists();

        $db = Database::getInstance();

        try {
            $db->query("
                DELETE FROM " . DB_PREFIX . "queue_titles
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
            ", [(int)$hoursOld]);

            return true;

        } catch (Exception $e) {
            error_log("TitleQueueManager: Error during auto-cleanup - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si un título es similar a alguno existente en la cola
     *
     * Usa dos métodos de detección:
     * 1. Levenshtein distance (distancia de edición)
     * 2. similar_text (porcentaje de similitud)
     *
     * Un título se considera similar si:
     * - Similitud > 85% usando similar_text, O
     * - Distancia Levenshtein < 15% de la longitud del título
     *
     * @param string $campaignId ID de la campaña
     * @param string $newTitle Nuevo título a verificar
     * @param float $threshold Umbral de similitud (0-1, default: 0.85 = 85%)
     * @return array ['is_similar' => bool, 'similar_to' => string|null, 'similarity_percent' => float]
     */
    public static function isSimilarToAny($campaignId, $newTitle, $threshold = 0.85) {
        if (!$campaignId || !$newTitle) {
            return ['is_similar' => false, 'similar_to' => null, 'similarity_percent' => 0];
        }

        self::ensureTableExists();

        $existingTitles = self::getTitles($campaignId, 50); // Comparar con últimos 50

        if (empty($existingTitles)) {
            return ['is_similar' => false, 'similar_to' => null, 'similarity_percent' => 0];
        }

        $newTitleNormalized = strtolower(trim($newTitle));
        $maxSimilarity = 0;
        $mostSimilarTitle = null;

        foreach ($existingTitles as $existingTitle) {
            $existingNormalized = strtolower(trim($existingTitle));

            // [NUEVO v4.21] Método 0: Detección de palabras iniciales comunes
            // Si las primeras 2-3 palabras son idénticas o muy similares, rechazar
            $newWords = preg_split('/[\s:]+/', $newTitleNormalized, 5);
            $existingWords = preg_split('/[\s:]+/', $existingNormalized, 5);

            // Método 0a: Detectar palabra única distintiva (>7 chars) en primeras 3 palabras
            $newFirst3 = array_slice($newWords, 0, 3);
            $existingFirst3 = array_slice($existingWords, 0, 3);

            foreach ($newFirst3 as $newWord) {
                if (strlen($newWord) > 7) { // Palabra distintiva (ej: "catacata")
                    foreach ($existingFirst3 as $existingWord) {
                        similar_text($newWord, $existingWord, $wordSim);
                        if ($wordSim > 85) {
                            // Palabra distintiva repetida al inicio
                            return [
                                'is_similar' => true,
                                'similar_to' => $existingTitle,
                                'similarity_percent' => round($wordSim, 2),
                                'reason' => 'repeated_distinctive_word'
                            ];
                        }
                    }
                }
            }

            // Método 0b: Primeras 2-3 palabras significativas
            $newStart = array_filter(array_slice($newWords, 0, 3), function($w) { return strlen($w) > 3; });
            $existingStart = array_filter(array_slice($existingWords, 0, 3), function($w) { return strlen($w) > 3; });

            if (count($newStart) >= 2 && count($existingStart) >= 2) {
                $newStartStr = implode(' ', array_slice($newStart, 0, 2));
                $existingStartStr = implode(' ', array_slice($existingStart, 0, 2));

                similar_text($newStartStr, $existingStartStr, $startSimilarity);

                // Si las primeras 2 palabras clave son >65% similares, rechazar
                if ($startSimilarity > 65) {
                    return [
                        'is_similar' => true,
                        'similar_to' => $existingTitle,
                        'similarity_percent' => round($startSimilarity, 2),
                        'reason' => 'common_start_words'
                    ];
                }
            }

            // Método 1: similar_text (porcentaje de similitud)
            similar_text($newTitleNormalized, $existingNormalized, $percentSimilar);
            $percentSimilar = $percentSimilar / 100; // Convertir a 0-1

            // Método 2: Levenshtein (distancia de edición)
            $distance = levenshtein($newTitleNormalized, $existingNormalized);
            $maxLength = max(strlen($newTitleNormalized), strlen($existingNormalized));
            $levenshteinSimilarity = 1 - ($distance / $maxLength);

            // Usar el mayor de los dos métodos
            $similarity = max($percentSimilar, $levenshteinSimilarity);

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $mostSimilarTitle = $existingTitle;
            }

            // Si encontramos uno muy similar, no seguir buscando
            if ($similarity >= $threshold) {
                return [
                    'is_similar' => true,
                    'similar_to' => $existingTitle,
                    'similarity_percent' => round($similarity * 100, 2),
                    'reason' => 'overall_similarity'
                ];
            }
        }

        return [
            'is_similar' => false,
            'similar_to' => $mostSimilarTitle,
            'similarity_percent' => round($maxSimilarity * 100, 2)
        ];
    }

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

        self::ensureTableExists();

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

        self::ensureTableExists();

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

        self::ensureTableExists();

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

        self::ensureTableExists();

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

        self::ensureTableExists();

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
        self::ensureTableExists();

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
     *
     * @param int $hoursOld Antigüedad en horas (default: 24)
     * @return bool True si la limpieza fue exitosa
     */
    public static function cleanupOldTitles($hoursOld = 24) {
        return self::autoCleanup($hoursOld);
    }
}
