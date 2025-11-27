<?php
/**
 * LicenseStatsController - Estadísticas completas para el plugin
 * 
 * Endpoint: POST /get-license-stats
 * 
 * Devuelve estadísticas agrupadas por campañas, colas y timeline
 * Compatible con el módulo de estadísticas del plugin
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/models/License.php';

class LicenseStatsController {
    
    /**
     * POST /get-license-stats
     * 
     * Obtener estadísticas completas de una licencia
     * Compatible con el formato esperado por el plugin
     */
    public function getLicenseStats() {
        $input = Response::getJsonInput();
        
        $licenseKey = $input['license_key'] ?? null;
        $dateFrom = $input['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $input['date_to'] ?? date('Y-m-d');
        
        if (!$licenseKey) {
            Response::error('license_key es requerido', 400);
        }
        
        // Obtener licencia
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        if (!$license) {
            Response::error('Licencia no encontrada', 404);
        }
        
        $db = Database::getInstance();
        
        // 1. Obtener información de uso (tokens)
        $usage = [
            'tokens_used' => intval($license['tokens_used_this_period']),
            'tokens_limit' => intval($license['tokens_limit']),
            'period_starts_at' => $license['period_starts_at'],
            'period_ends_at' => $license['period_ends_at']
        ];
        
        // 2. Verificar qué columnas existen
        $columns = $db->query("SHOW COLUMNS FROM " . DB_PREFIX . "usage_tracking");
        $columnNames = array_column($columns, 'Field');
        $hasCampaignId = in_array('campaign_id', $columnNames);
        $hasCampaignName = in_array('campaign_name', $columnNames);
        $hasBatchId = in_array('batch_id', $columnNames);
        $hasBatchType = in_array('batch_type', $columnNames);
        
        // 3. Construir SELECT dinámicamente
        $selectFields = "
            DATE(created_at) as date,
            MAX(created_at) as last_operation_at,
            operation_type,
            COUNT(*) as count,
            SUM(tokens_total) as tokens,
            SUM(COALESCE(tokens_input, 0)) as tokens_input,
            SUM(COALESCE(tokens_output, 0)) as tokens_output,
            SUM(COALESCE(cost_total, 0)) as cost";
        
        if ($hasBatchId) $selectFields .= ", batch_id";
        if ($hasBatchType) $selectFields .= ", batch_type";
        if ($hasCampaignId) $selectFields .= ", campaign_id";
        if ($hasCampaignName) $selectFields .= ", campaign_name";
        
        // 4. Construir GROUP BY dinámicamente
        $groupByParts = ["DATE(created_at)", "operation_type"];
        if ($hasCampaignId) array_unshift($groupByParts, "campaign_id");
        if ($hasBatchId) $groupByParts[] = "batch_id";
        $groupBy = implode(", ", $groupByParts);
        
        // 5. Obtener datos
        $rawData = $db->query("
            SELECT $selectFields
            FROM " . DB_PREFIX . "usage_tracking
            WHERE license_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY $groupBy
            ORDER BY date DESC, created_at DESC
        ", [$license['id'], $dateFrom, $dateTo]);
        
        if (empty($rawData)) {
            Response::success([
                'usage' => $usage,
                'by_operation' => [],
                'timeline' => []
            ]);
        }
        
        // 6. Procesar datos
        $result = $this->processStatsData($rawData, $hasCampaignId, $hasCampaignName, $hasBatchId, $hasBatchType);
        
        Response::success([
            'usage' => $usage,
            'by_operation' => $result['by_operation'],
            'timeline' => $result['timeline']
        ]);
    }
    
    /**
     * Procesar datos crudos y agrupar por campañas/colas
     */
    private function processStatsData($rawData, $hasCampaignId, $hasCampaignName, $hasBatchId, $hasBatchType) {
        // Mapeo de nombres amigables
        $nameMap = [
            'queue' => 'Colas Generadas',
            'title' => 'Títulos',
            'keywords' => 'Keywords',
            'keywords_images' => 'Keywords de Imagen',
            'keywords_seo' => 'Keywords SEO',
            'content' => 'Contenido',
            'meta' => 'Meta Descripciones',
            'excerpt' => 'Extractos',
            'complete' => 'Posts Completos',
            'company_description' => 'Descripción de Empresa',
            'content_prompt' => 'Prompt de Contenido',
            'title_prompt' => 'Prompt para Títulos',
            'campaign_image_keywords' => 'Keywords de Campaña'
        ];
        
        $campaignGroups = [];
        $queueGroups = [];
        $individualOperations = [];
        $timelineData = [];
        
        // Agrupar datos
        foreach ($rawData as $row) {
            $date = $row['date'];
            $lastOpAt = $row['last_operation_at'] ?? $date;
            $opType = $row['operation_type'];
            $count = intval($row['count']);
            $tokens = intval($row['tokens']);
            $cost = floatval($row['cost']);
            
            $campaignId = $hasCampaignId ? ($row['campaign_id'] ?? null) : null;
            $campaignName = $hasCampaignName ? ($row['campaign_name'] ?? 'Sin nombre') : 'Sin nombre';
            $batchId = $hasBatchId ? ($row['batch_id'] ?? null) : null;
            $batchType = $hasBatchType ? ($row['batch_type'] ?? null) : null;
            
            // Acumular en timeline
            if (!isset($timelineData[$date])) {
                $timelineData[$date] = [
                    'date' => $date,
                    'operations' => 0,
                    'tokens' => 0,
                    'cost' => 0
                ];
            }
            $timelineData[$date]['operations'] += $count;
            $timelineData[$date]['tokens'] += $tokens;
            $timelineData[$date]['cost'] += $cost;
            
            // Si tiene campaign_id, agrupar por campaña
            if ($campaignId) {
                if (!isset($campaignGroups[$campaignId])) {
                    $campaignGroups[$campaignId] = [
                        'campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                        'date' => $date,
                        'last_operation_at' => $lastOpAt,
                        'total_count' => 0,
                        'total_tokens' => 0,
                        'total_cost' => 0,
                        'queues' => [],
                        'operations' => []
                    ];
                } else {
                    // Actualizar con la fecha más reciente
                    if ($lastOpAt > $campaignGroups[$campaignId]['last_operation_at']) {
                        $campaignGroups[$campaignId]['last_operation_at'] = $lastOpAt;
                    }
                }
                
                $campaignGroups[$campaignId]['total_count'] += $count;
                $campaignGroups[$campaignId]['total_tokens'] += $tokens;
                $campaignGroups[$campaignId]['total_cost'] += $cost;
                
                // Si tiene batch_id (es parte de una cola)
                if ($batchId && $batchType === 'queue') {
                    if (!isset($campaignGroups[$campaignId]['queues'][$batchId])) {
                        $campaignGroups[$campaignId]['queues'][$batchId] = [
                            'batch_id' => $batchId,
                            'date' => $date,
                            'total_tokens' => 0,
                            'total_cost' => 0,
                            'items' => []
                        ];
                    }
                    
                    $campaignGroups[$campaignId]['queues'][$batchId]['total_tokens'] += $tokens;
                    $campaignGroups[$campaignId]['queues'][$batchId]['total_cost'] += $cost;
                    $campaignGroups[$campaignId]['queues'][$batchId]['items'][] = [
                        'type' => $opType,
                        'count' => $count,
                        'tokens' => $tokens,
                        'cost' => $cost
                    ];
                }
                
                // Acumular en operaciones de campaña
                if (!isset($campaignGroups[$campaignId]['operations'][$opType])) {
                    $campaignGroups[$campaignId]['operations'][$opType] = [
                        'count' => 0,
                        'tokens' => 0,
                        'cost' => 0
                    ];
                }
                $campaignGroups[$campaignId]['operations'][$opType]['count'] += $count;
                $campaignGroups[$campaignId]['operations'][$opType]['tokens'] += $tokens;
                $campaignGroups[$campaignId]['operations'][$opType]['cost'] += $cost;
            }
            // Si NO tiene campaign_id pero tiene batch_id (modo legacy)
            elseif ($batchId && $batchType === 'queue') {
                if (!isset($queueGroups[$batchId])) {
                    $queueGroups[$batchId] = [
                        'batch_id' => $batchId,
                        'date' => $date,
                        'total_tokens' => 0,
                        'total_cost' => 0,
                        'items' => []
                    ];
                }
                
                $queueGroups[$batchId]['total_tokens'] += $tokens;
                $queueGroups[$batchId]['total_cost'] += $cost;
                $queueGroups[$batchId]['items'][] = [
                    'type' => $opType,
                    'count' => $count,
                    'tokens' => $tokens,
                    'cost' => $cost
                ];
            }
            // Operaciones individuales (sin campaña ni cola)
            else {
                if (!isset($individualOperations[$opType])) {
                    $individualOperations[$opType] = [
                        'count' => 0,
                        'tokens' => 0,
                        'cost' => 0
                    ];
                }
                $individualOperations[$opType]['count'] += $count;
                $individualOperations[$opType]['tokens'] += $tokens;
                $individualOperations[$opType]['cost'] += $cost;
            }
        }
        
        // Construir array final by_operation
        $byOperation = [];
        
        // 1. CAMPAÑAS (si existen)
        foreach ($campaignGroups as $campaignId => $campaign) {
            $queuesDetails = [];
            
            foreach ($campaign['queues'] as $batchId => $queue) {
                // Agrupar items de la cola en título + keywords
                $subitems = [];
                $groupedItems = [];
                
                foreach ($queue['items'] as $item) {
                    $type = $item['type'];
                    if (!isset($groupedItems[$type])) {
                        $groupedItems[$type] = [
                            'type' => $type,
                            'display_name' => $nameMap[$type] ?? ucfirst($type),
                            'count' => 0,
                            'tokens' => 0,
                            'cost' => 0
                        ];
                    }
                    $groupedItems[$type]['count'] += $item['count'];
                    $groupedItems[$type]['tokens'] += $item['tokens'];
                    $groupedItems[$type]['cost'] += $item['cost'];
                }
                
                $queuesDetails[] = [
                    'batch_id' => $batchId,
                    'date' => $queue['date'],
                    'total_tokens' => $queue['total_tokens'],
                    'total_cost' => round($queue['total_cost'], 6),
                    'subitems' => array_values($groupedItems)
                ];
            }
            
            // Construir operaciones individuales de la campaña
            $operations = [];
            foreach ($campaign['operations'] as $opType => $opData) {
                $operations[] = [
                    'operation_type' => $opType,
                    'display_name' => $nameMap[$opType] ?? ucfirst($opType),
                    'count' => $opData['count'],
                    'tokens' => $opData['tokens'],
                    'cost' => round($opData['cost'], 6)
                ];
            }
            
            $byOperation[] = [
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign['campaign_name'],
                'date' => $campaign['date'],
                'last_operation_at' => $campaign['last_operation_at'],
                'total_count' => $campaign['total_count'],
                'total_tokens' => $campaign['total_tokens'],
                'total_cost' => round($campaign['total_cost'], 6),
                'queues_count' => count($campaign['queues']),
                'queues_details' => $queuesDetails,
                'operations' => $operations,
                'is_campaign' => true
            ];
        }
        
        // 2. COLAS (modo legacy, si NO hay campañas)
        if (empty($campaignGroups) && !empty($queueGroups)) {
            $queuesDetails = [];
            
            foreach ($queueGroups as $batchId => $queue) {
                $subitems = [];
                $groupedItems = [];
                
                foreach ($queue['items'] as $item) {
                    $type = $item['type'];
                    if (!isset($groupedItems[$type])) {
                        $groupedItems[$type] = [
                            'type' => $type,
                            'display_name' => $nameMap[$type] ?? ucfirst($type),
                            'count' => 0,
                            'tokens' => 0,
                            'cost' => 0
                        ];
                    }
                    $groupedItems[$type]['count'] += $item['count'];
                    $groupedItems[$type]['tokens'] += $item['tokens'];
                    $groupedItems[$type]['cost'] += $item['cost'];
                }
                
                $queuesDetails[] = [
                    'batch_id' => $batchId,
                    'date' => $queue['date'],
                    'total_tokens' => $queue['total_tokens'],
                    'total_cost' => round($queue['total_cost'], 6),
                    'subitems' => array_values($groupedItems)
                ];
            }
            
            $totalQueueTokens = array_sum(array_column($queueGroups, 'total_tokens'));
            $totalQueueCost = array_sum(array_column($queueGroups, 'total_cost'));
            
            $byOperation[] = [
                'operation_type' => 'queue',
                'display_name' => $nameMap['queue'],
                'count' => count($queueGroups),
                'tokens' => $totalQueueTokens,
                'cost' => round($totalQueueCost, 6),
                'is_group' => true,
                'is_campaign' => false,
                'queues_details' => $queuesDetails
            ];
        }
        
        // 3. OPERACIONES INDIVIDUALES
        foreach ($individualOperations as $opType => $opData) {
            $byOperation[] = [
                'operation_type' => $opType,
                'display_name' => $nameMap[$opType] ?? ucfirst($opType),
                'count' => $opData['count'],
                'tokens' => $opData['tokens'],
                'cost' => round($opData['cost'], 6),
                'is_campaign' => false,
                'is_group' => false
            ];
        }
        
        // Ordenar timeline por fecha (más reciente primero)
        $timeline = array_values($timelineData);
        usort($timeline, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        // Redondear valores en timeline
        foreach ($timeline as &$day) {
            $day['cost'] = round($day['cost'], 6);
        }
        
        return [
            'by_operation' => $byOperation,
            'timeline' => $timeline
        ];
    }
}
