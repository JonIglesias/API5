<?php
/**
 * StatsController - Gestión de Estadísticas
 * Compatible EXACTAMENTE con endpoints de API V3
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/models/License.php';

class StatsController {
    
    /**
     * GET/POST /get-stats
     * 
     * Obtener estadísticas detalladas de una licencia
     * REPLICA EXACTA del método Database::getDetailedStats() de V3
     */
    public function getStats() {
        // Leer de POST body (JSON) o GET params
        $input = Response::getJsonInput();
        
        $licenseKey = $input['license_key'] ?? $_GET['license_key'] ?? null;
        
        if (!$licenseKey) {
            Response::error('license_key es requerido', 400);
        }
        
        // Obtener licencia
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        if (!$license) {
            // Si no hay datos, devolver estructura vacía igual que V3
            Response::success([
                'operations' => [],
                'totals' => [
                    'total_operations' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0
                ],
                'period' => [
                    'from' => $input['date_from'] ?? $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
                    'to' => $input['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d')
                ]
            ]);
        }
        
        // Obtener fechas del filtro
        $dateFrom = $input['date_from'] ?? $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $input['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
        
        // ==============================================================================
        // LÓGICA IGUAL A V3: Database::getDetailedStats()
        // ==============================================================================
        $db = Database::getInstance();
        
        // 1. Obtener todos los registros del periodo agrupados por fecha y tipo
        $rawData = $db->query("
            SELECT 
                DATE(created_at) as date,
                operation_type,
                COUNT(*) as count,
                SUM(tokens_total) as tokens,
                0 as cost
            FROM " . DB_PREFIX . "usage_tracking
            WHERE license_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), operation_type
            ORDER BY date DESC
        ", [$license['id'], $dateFrom, $dateTo]);
        
        // Si no hay datos, devolver estructura vacía
        if (empty($rawData)) {
            Response::success([
                'operations' => [],
                'totals' => [
                    'total_operations' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        }
        
        // 2. Construir estructura igual que summaries.json de V3
        // $licenseData = [
        //   '2025-11-05' => [
        //     'operations' => 50,
        //     'tokens_total' => 18000,
        //     'cost_total' => 0.18,
        //     'by_type' => [
        //       'image_keywords' => ['count' => 25, 'tokens' => 9000, 'cost' => 0.09],
        //       'title' => ['count' => 25, 'tokens' => 9000, 'cost' => 0.09]
        //     ]
        //   ]
        // ]
        $licenseData = [];
        foreach ($rawData as $row) {
            $date = $row['date'];
            $opType = $row['operation_type'];
            
            if (!isset($licenseData[$date])) {
                $licenseData[$date] = [
                    'operations' => 0,
                    'tokens_total' => 0,
                    'cost_total' => 0,
                    'by_type' => []
                ];
            }
            
            $count = intval($row['count']);
            $tokens = intval($row['tokens']);
            $cost = floatval($row['cost']);
            
            $licenseData[$date]['operations'] += $count;
            $licenseData[$date]['tokens_total'] += $tokens;
            $licenseData[$date]['cost_total'] += $cost;
            
            $licenseData[$date]['by_type'][$opType] = [
                'count' => $count,
                'tokens' => $tokens,
                'cost' => $cost
            ];
        }
        
        // 3. Procesar igual que V3: Database::getDetailedStats()
        // Agregar todos los tipos de operaciones
        $operationsByType = [];
        $totalOps = 0;
        $totalTokens = 0;
        $totalCost = 0;
        
        foreach ($licenseData as $date => $dayData) {
            if (isset($dayData['by_type'])) {
                foreach ($dayData['by_type'] as $opType => $stats) {
                    if (!isset($operationsByType[$opType])) {
                        $operationsByType[$opType] = [
                            'quantity' => 0,
                            'tokens' => 0,
                            'cost' => 0
                        ];
                    }
                    
                    $operationsByType[$opType]['quantity'] += $stats['count'];
                    $operationsByType[$opType]['tokens'] += $stats['tokens'];
                    $operationsByType[$opType]['cost'] += $stats['cost'];
                    
                    $totalOps += $stats['count'];
                    $totalTokens += $stats['tokens'];
                    $totalCost += $stats['cost'];
                }
            }
        }
        
        // 4. Convertir a formato del plugin (igual que V3)
        $operations = [];
        foreach ($operationsByType as $type => $stats) {
            $operations[] = [
                'operation' => $type,
                'quantity' => $stats['quantity'],
                'tokens' => $stats['tokens'],
                'cost' => round($stats['cost'], 4)
            ];
        }
        
        // 5. Respuesta EXACTA de V3
        Response::success([
            'operations' => $operations,
            'totals' => [
                'total_operations' => $totalOps,
                'total_tokens' => $totalTokens,
                'total_cost' => round($totalCost, 4)
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ]);
    }
    
    /**
     * POST /reset-stats
     * 
     * Resetear estadísticas de una licencia (solo admin)
     * Compatible con V3
     */
    public function resetStats() {
        $data = Response::getJsonInput();
        
        $targetLicense = $data['target_license'] ?? null;
        
        if (!$targetLicense) {
            Response::error('target_license es requerido', 400);
        }
        
        // Obtener licencia
        $licenseModel = new License();
        $license = $licenseModel->findByKey($targetLicense);
        
        if (!$license) {
            Response::error('Licencia no encontrada', 404);
        }
        
        // Eliminar registros de tracking
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "usage_tracking WHERE license_id = ?");
        $stmt->execute([$license['id']]);
        
        Response::success([
            'message' => 'Estadísticas reseteadas correctamente',
            'license' => $targetLicense
        ]);
    }
    
    /**
     * Registrar uso de tokens (llamado internamente por otros controladores)
     */
    public static function trackUsage($licenseId, $operationType, $tokensTotal, $syncStatus = 'fresh') {
        try {
            $db = Database::getInstance();
            
            $db->insert('usage_tracking', [
                'license_id' => $licenseId,
                'operation_type' => $operationType,
                'tokens_total' => $tokensTotal,
                'sync_status_at_time' => $syncStatus,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Actualizar contador de la licencia
            $stmt = $db->prepare("
                UPDATE " . DB_PREFIX . "licenses 
                SET tokens_used_this_period = tokens_used_this_period + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tokensTotal, $licenseId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error tracking usage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener resumen de uso del mes actual (para endpoint /usage)
     */
    public static function getCurrentMonthUsage($licenseId) {
        $db = Database::getInstance();
        
        $firstDayOfMonth = date('Y-m-01 00:00:00');
        
        $result = $db->fetchOne("
            SELECT 
                COUNT(*) as total_operations,
                SUM(tokens_total) as total_tokens
            FROM " . DB_PREFIX . "usage_tracking
            WHERE license_id = ?
            AND created_at >= ?
        ", [$licenseId, $firstDayOfMonth]);
        
        return [
            'operations' => intval($result['total_operations'] ?? 0),
            'tokens' => intval($result['total_tokens'] ?? 0)
        ];
    }
}
