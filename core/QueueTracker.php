<?php
/**
 * Helper para trackear operaciones en colas
 * 
 * DETECCIÓN AUTOMÁTICA:
 * - Si el request incluye 'campaign_id', se marca como parte de una cola (batch_type = 'queue')
 * - Si NO incluye campaign_id, se marca como operación individual (batch_type = null)
 * 
 * USO EN EL PLUGIN:
 * 1. Establecer variable global al iniciar campaña:
 *    $GLOBALS['ap_current_campaign_id'] = 'campaign_' . $campaign_id;
 * 
 * 2. En ap-api-client.php, esto se añade automáticamente a cada request
 * 
 * 3. La API detecta el campaign_id y agrupa las operaciones
 * 
 * RESULTADO:
 * - Todas las operaciones con el MISMO campaign_id = 1 cola en estadísticas
 * - Si regeneran la cola (borran posts), sigue siendo la misma campaña = misma cola
 */

class QueueTracker {
    
    /**
     * Detectar campaign_id del request actual
     * 
     * @param array|null $requestData Request data (POST/JSON body)
     * @return string|null Campaign ID si existe
     */
    public static function detectCampaignId($requestData = null) {
        if ($requestData === null) {
            // Intentar leer de php://input
            $input = file_get_contents('php://input');
            $requestData = json_decode($input, true) ?? [];
        }
        
        // Buscar campaign_id en diferentes ubicaciones
        return $requestData['campaign_id'] 
            ?? $_POST['campaign_id'] 
            ?? $_GET['campaign_id']
            ?? null;
    }
    
    /**
     * Detectar campaign_name del request actual
     * 
     * @param array|null $requestData Request data (POST/JSON body)
     * @return string|null Campaign name si existe
     */
    public static function detectCampaignName($requestData = null) {
        if ($requestData === null) {
            // Intentar leer de php://input
            $input = file_get_contents('php://input');
            $requestData = json_decode($input, true) ?? [];
        }
        
        // Buscar campaign_name en diferentes ubicaciones
        return $requestData['campaign_name'] 
            ?? $_POST['campaign_name'] 
            ?? $_GET['campaign_name']
            ?? null;
    }
    
    /**
     * Registrar una operación (automáticamente detecta si es cola o individual)
     * 
     * @param int $licenseId ID de la licencia
     * @param string $operationType Tipo de operación (title, keywords_images, etc)
     * @param int $tokensUsed DEPRECATED: Usar $tokensInput + $tokensOutput
     * @param array|null $requestData Request data para detectar campaign_id
     * @param int $tokensInput Tokens de entrada (prompt)
     * @param int $tokensOutput Tokens de salida (completion)
     * @param string $modelUsed Modelo usado (gpt-4, gpt-3.5-turbo, etc)
     * @return bool True si se guardó correctamente
     */
    public static function track($licenseId, $operationType, $tokensUsed = 0, $requestData = null, $tokensInput = null, $tokensOutput = null, $modelUsed = null) {
        $db = Database::getInstance();
        
        try {
            // Detectar si es parte de una cola
            $campaignId = self::detectCampaignId($requestData);
            $campaignName = self::detectCampaignName($requestData);
            $batchType = $campaignId ? 'queue' : null;
            
            // Si no se especificaron tokens separados, asumir que tokensUsed es el total
            if ($tokensInput === null && $tokensOutput === null) {
                // Retrocompatibilidad: usar tokens_total
                $tokensTotal = $tokensUsed;
                $tokensInput = null;
                $tokensOutput = null;
                $costInput = null;
                $costOutput = null;
                $costTotal = null;
            } else {
                // Nuevo sistema: calcular costos
                $tokensTotal = $tokensInput + $tokensOutput;
                
                // Calcular costos según modelo (precios por 1K tokens)
                $prices = self::getModelPrices($modelUsed);
                $costInput = ($tokensInput / 1000) * $prices['input'];
                $costOutput = ($tokensOutput / 1000) * $prices['output'];
                $costTotal = $costInput + $costOutput;
            }
            
            $db->query("
                INSERT INTO " . DB_PREFIX . "usage_tracking 
                (license_id, operation_type, batch_id, batch_type, campaign_id, campaign_name, 
                 tokens_total, tokens_input, tokens_output, 
                 cost_input, cost_output, cost_total, model_used, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $licenseId, 
                $operationType, 
                $campaignId,  // batch_id (para compatibilidad)
                $batchType, 
                $campaignId,  // campaign_id (nuevo campo)
                $campaignName, // campaign_name (nuevo campo)
                $tokensTotal,
                $tokensInput,
                $tokensOutput,
                $costInput,
                $costOutput,
                $costTotal,
                $modelUsed
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error tracking operation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener precios del modelo
     * Precios actualizados a Nov 2024
     * 
     * @param string|null $model Nombre del modelo
     * @return array ['input' => precio, 'output' => precio] por 1K tokens
     */
    private static function getModelPrices($model) {
        $prices = [
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4o' => ['input' => 0.005, 'output' => 0.015],
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
        ];
        
        // Buscar precio exacto o por familia
        if (isset($prices[$model])) {
            return $prices[$model];
        }
        
        // Detectar familia del modelo
        foreach ($prices as $modelName => $price) {
            if (strpos($model, $modelName) !== false) {
                return $price;
            }
        }
        
        // Precio por defecto (gpt-4o-mini)
        return ['input' => 0.00015, 'output' => 0.0006];
    }
    
    /**
     * Obtener estadísticas de una campaña específica
     * 
     * @param string $campaignId ID de la campaña
     * @return array Estadísticas de la campaña
     */
    public static function getCampaignStats($campaignId) {
        $db = Database::getInstance();
        
        $operations = $db->query("
            SELECT 
                operation_type,
                COUNT(*) as count,
                SUM(tokens_total) as tokens,
                MIN(created_at) as first_operation,
                MAX(created_at) as last_operation
            FROM " . DB_PREFIX . "usage_tracking
            WHERE batch_id = ?
            GROUP BY operation_type
        ", [$campaignId]);
        
        $total = $db->fetchOne("
            SELECT 
                COUNT(*) as total_operations,
                SUM(tokens_total) as total_tokens,
                MIN(created_at) as started_at,
                MAX(created_at) as completed_at
            FROM " . DB_PREFIX . "usage_tracking
            WHERE batch_id = ?
        ", [$campaignId]);
        
        return [
            'campaign_id' => $campaignId,
            'operations' => $operations,
            'total_operations' => $total['total_operations'] ?? 0,
            'total_tokens' => $total['total_tokens'] ?? 0,
            'started_at' => $total['started_at'] ?? null,
            'completed_at' => $total['completed_at'] ?? null,
            'duration_seconds' => strtotime($total['completed_at']) - strtotime($total['started_at'])
        ];
    }
    
    /**
     * Obtener todas las colas (campañas) con sus estadísticas
     * 
     * @param int|null $licenseId Filtrar por licencia (opcional)
     * @param int $limit Número máximo de resultados
     * @return array Array de colas con estadísticas
     */
    public static function getAllQueues($licenseId = null, $limit = 50) {
        $db = Database::getInstance();
        
        $where = "batch_type = 'queue'";
        $params = [];
        
        if ($licenseId) {
            $where .= " AND license_id = ?";
            $params[] = $licenseId;
        }
        
        $queues = $db->query("
            SELECT 
                batch_id as campaign_id,
                license_id,
                COUNT(*) as total_operations,
                SUM(tokens_total) as total_tokens,
                MIN(created_at) as started_at,
                MAX(created_at) as completed_at,
                GROUP_CONCAT(DISTINCT operation_type) as operation_types
            FROM " . DB_PREFIX . "usage_tracking
            WHERE {$where}
            GROUP BY batch_id, license_id
            ORDER BY completed_at DESC
            LIMIT {$limit}
        ", $params);
        
        return $queues;
    }
}
