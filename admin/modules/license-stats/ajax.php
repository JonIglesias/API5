<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!defined('API_ACCESS')) {
    define('API_ACCESS', true);
}

// Cargar solo lo esencial
try {
    require_once __DIR__ . '/../../../config.php';
    require_once __DIR__ . '/../../../core/Database.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Error al cargar dependencias: ' . $e->getMessage()
    ]);
    exit;
}

header('Content-Type: application/json');

// Obtener acción desde GET, POST o JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Si no hay action en GET/POST, intentar leer desde JSON body
if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'get_all_licenses':
        getAllLicenses();
        break;
        
    case 'get_stats':
        getStats();
        break;
        
    case 'reset_stats':
        resetStats();
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}

function getAllLicenses() {
    try {
        $db = Database::getInstance();
        
        // PRIMERO: Ver qué columnas existen realmente
        $columns = $db->query("SHOW COLUMNS FROM " . DB_PREFIX . "licenses");
        
        // Construir query dinámicamente con solo las columnas que existen
        $availableColumns = [];
        foreach ($columns as $col) {
            $availableColumns[] = $col['Field'];
        }
        
        // Columnas que queremos (si existen)
        $desiredColumns = [
            'id',
            'license_key',
            'user_email',
            'plan_id',
            'status',
            'tokens_used_this_period',
            'tokens_limit',
            'period_starts_at',
            'period_ends_at',
            'authorized_domains',
            'activated_domain',
            'domains'
        ];
        
        // Filtrar solo las que existen
        $columnsToSelect = [];
        foreach ($desiredColumns as $desired) {
            if (in_array($desired, $availableColumns)) {
                $columnsToSelect[] = $desired;
            }
        }
        
        // Si no hay columnas base, error
        if (empty($columnsToSelect)) {
            throw new Exception('No se encontraron columnas válidas en la tabla licenses');
        }
        
        $licenses = $db->query("
            SELECT " . implode(', ', $columnsToSelect) . "
            FROM " . DB_PREFIX . "licenses
            ORDER BY created_at DESC
        ");
        
        // Agregar campo domains_text si existe alguna columna de dominios
        foreach ($licenses as &$license) {
            $license['domains_text'] = '';
            
            if (isset($license['authorized_domains']) && !empty($license['authorized_domains'])) {
                $domains = json_decode($license['authorized_domains'], true);
                $license['domains_text'] = is_array($domains) ? implode(', ', $domains) : $license['authorized_domains'];
            } elseif (isset($license['activated_domain'])) {
                $license['domains_text'] = $license['activated_domain'];
            } elseif (isset($license['domains'])) {
                $license['domains_text'] = $license['domains'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'licenses' => $licenses ?? [],
            'debug_columns' => $availableColumns // Para que veas qué columnas existen
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error al obtener licencias: ' . $e->getMessage()
        ]);
    }
}

function getStats() {
    $licenseId = $_GET['license_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    if (!$licenseId) {
        echo json_encode(['success' => false, 'error' => 'license_id requerido']);
        return;
    }
    
    $db = Database::getInstance();
    
    // Mapeo de nombres personalizados
    $nameMap = [
        'queue' => 'Colas Generadas',
        'title' => 'Títulos',
        'keywords' => 'Keywords',
        'keywords_images' => 'Set de Keywords de Imagen de Post',
        'keywords_seo' => 'Set de Keywords SEO',
        'content' => 'Contenido',
        'meta' => 'Meta Descripciones',
        'company_description' => 'Descripción de Empresa',
        'content_prompt' => 'Prompt de Contenido',
        'title_prompt' => 'Prompt para Títulos',
        'campaign_image_keywords' => 'Set de Keywords de Imagen de Campaña'
    ];
    
    // Verificar qué columnas existen (para compatibilidad con BD sin campaign_id/campaign_name)
    $columns = $db->query("SHOW COLUMNS FROM " . DB_PREFIX . "usage_tracking");
    $columnNames = array_column($columns, 'Field');
    $hasCampaignId = in_array('campaign_id', $columnNames);
    $hasCampaignName = in_array('campaign_name', $columnNames);
    
    // Construir SELECT dinámicamente
    $selectFields = "
        DATE(created_at) as date,
        MAX(created_at) as last_operation_at,
        operation_type,
        batch_id,
        batch_type,
        COUNT(*) as count,
        SUM(tokens_total) as tokens,
        SUM(COALESCE(tokens_input, 0)) as tokens_input,
        SUM(COALESCE(tokens_output, 0)) as tokens_output,
        SUM(COALESCE(cost_total, 0)) as cost";
    
    if ($hasCampaignId) {
        $selectFields .= ", campaign_id";
    }
    if ($hasCampaignName) {
        $selectFields .= ", campaign_name";
    }
    
    // Construir GROUP BY dinámicamente
    $groupBy = "DATE(created_at), operation_type, batch_id";
    if ($hasCampaignId) {
        $groupBy = "campaign_id, " . $groupBy;
    }
    
    // Obtener datos
    $rawData = $db->query("
        SELECT $selectFields
        FROM " . DB_PREFIX . "usage_tracking
        WHERE license_id = ?
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY $groupBy
        ORDER BY date DESC, created_at DESC
    ", [$licenseId, $dateFrom, $dateTo]);
    
    if (empty($rawData)) {
        echo json_encode([
            'success' => true,
            'stats' => [
                'general' => [
                    'total_operations' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0
                ],
                'by_operation' => [],
                'by_date' => []
            ]
        ]);
        return;
    }
    
    // Agrupar por campañas (si existen) o por colas (modo legacy)
    $campaignGroups = [];
    $queueGroups = [];
    $licenseData = [];
    $individualOperations = []; // Operaciones sin campaña
    
    foreach ($rawData as $row) {
        $date = $row['date'];
        $lastOpAt = $row['last_operation_at'] ?? $date;
        $opType = $row['operation_type'];
        $batchId = $row['batch_id'];
        $batchType = $row['batch_type'];
        $campaignId = $hasCampaignId ? ($row['campaign_id'] ?? null) : null;
        $campaignName = $hasCampaignName ? ($row['campaign_name'] ?? 'Sin nombre') : 'Sin nombre';
        
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
        
        // MODO CAMPAÑA: Si tiene campaign_id, agrupar por campaña
        if ($hasCampaignId && !empty($campaignId)) {
            if (!isset($campaignGroups[$campaignId])) {
                $campaignGroups[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $campaignName,
                    'date' => $date,
                    'last_operation_at' => $lastOpAt,
                    'total_count' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                    'queues' => [], // Colas dentro de esta campaña
                    'operations' => [] // Operaciones individuales de esta campaña
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
            
            // DEBUG
            error_log("📊 Procesando: campaign=$campaignId, op=$opType, batch_type=$batchType, batch_id=$batchId");
            
            // Si es parte de una cola (batch_type = 'queue')
            if ($batchType === 'queue' && !empty($batchId)) {
                if (!isset($campaignGroups[$campaignId]['queues'][$batchId])) {
                    $campaignGroups[$campaignId]['queues'][$batchId] = [
                        'date' => $date,
                        'items' => [],
                        'total_tokens' => 0,
                        'total_cost' => 0
                    ];
                }
                
                $campaignGroups[$campaignId]['queues'][$batchId]['items'][] = [
                    'type' => $opType,
                    'count' => $count,
                    'tokens' => $tokens,
                    'cost' => $cost
                ];
                $campaignGroups[$campaignId]['queues'][$batchId]['total_tokens'] += $tokens;
                $campaignGroups[$campaignId]['queues'][$batchId]['total_cost'] += $cost;
            } else {
                // Operación individual dentro de campaña
                error_log("  → Añadiendo a operaciones individuales");
                if (!isset($campaignGroups[$campaignId]['operations'][$opType])) {
                    $campaignGroups[$campaignId]['operations'][$opType] = [
                        'type' => $opType,
                        'count' => 0,
                        'tokens' => 0,
                        'cost' => 0
                    ];
                }
                $campaignGroups[$campaignId]['operations'][$opType]['count'] += $count;
                $campaignGroups[$campaignId]['operations'][$opType]['tokens'] += $tokens;
                $campaignGroups[$campaignId]['operations'][$opType]['cost'] += $cost;
            }
        }
        // MODO LEGACY: Agrupar por colas (batch_type = 'queue')
        else if ($batchType === 'queue' && !empty($batchId)) {
            if (!isset($queueGroups[$batchId])) {
                $queueGroups[$batchId] = [
                    'date' => $date,
                    'items' => [],
                    'total_tokens' => 0,
                    'total_cost' => 0
                ];
            }
            
            $queueGroups[$batchId]['items'][] = [
                'type' => $opType,
                'count' => $count,
                'tokens' => $tokens,
                'cost' => $cost
            ];
            $queueGroups[$batchId]['total_tokens'] += $tokens;
            $queueGroups[$batchId]['total_cost'] += $cost;
        } 
        // Operación individual (sin campaña ni cola)
        else {
            if (!isset($individualOperations[$opType])) {
                $individualOperations[$opType] = [
                    'type' => $opType,
                    'count' => 0,
                    'tokens' => 0,
                    'cost' => 0
                ];
            }
            $individualOperations[$opType]['count'] += $count;
            $individualOperations[$opType]['tokens'] += $tokens;
            $individualOperations[$opType]['cost'] += $cost;
        }
        
        $licenseData[$date]['operations'] += $count;
        $licenseData[$date]['tokens_total'] += $tokens;
        $licenseData[$date]['cost_total'] += $cost;
        
        if (!isset($licenseData[$date]['by_type'][$opType])) {
            $licenseData[$date]['by_type'][$opType] = [
                'count' => 0,
                'tokens' => 0,
                'cost' => 0
            ];
        }
        
        $licenseData[$date]['by_type'][$opType]['count'] += $count;
        $licenseData[$date]['by_type'][$opType]['tokens'] += $tokens;
        $licenseData[$date]['by_type'][$opType]['cost'] += $cost;
    }
    
    // Calcular totales
    $totalOps = 0;
    $totalTokens = 0;
    $totalCost = 0;
    $byOperation = [];
    $byDate = [];
    
    // MODO CAMPAÑA: Si hay campañas, mostrarlas
    if (!empty($campaignGroups)) {
        foreach ($campaignGroups as $campaignId => $campaign) {
            // Construir detalles de colas de esta campaña
            $queuesDetails = [];
            foreach ($campaign['queues'] as $batchId => $queue) {
                $titlesCount = 0;
                $keywordsCount = 0;
                $titlesTokens = 0;
                $keywordsTokens = 0;
                $titlesCost = 0;
                $keywordsCost = 0;
                
                foreach ($queue['items'] as $item) {
                    if ($item['type'] === 'title') {
                        $titlesCount += $item['count'];
                        $titlesTokens += $item['tokens'];
                        $titlesCost += $item['cost'];
                    } elseif ($item['type'] === 'keywords_images') {
                        $keywordsCount += $item['count'];
                        $keywordsTokens += $item['tokens'];
                        $keywordsCost += $item['cost'];
                    }
                }
                
                $queuesDetails[] = [
                    'batch_id' => $batchId,
                    'date' => $queue['date'],
                    'total_tokens' => $queue['total_tokens'],
                    'total_cost' => $queue['total_cost'],
                    'subitems' => [
                        [
                            'type' => 'title',
                            'display_name' => $nameMap['title'],
                            'count' => $titlesCount,
                            'tokens' => $titlesTokens,
                            'cost' => $titlesCost
                        ],
                        [
                            'type' => 'keywords_images',
                            'display_name' => $nameMap['keywords_images'],
                            'count' => $keywordsCount,
                            'tokens' => $keywordsTokens,
                            'cost' => $keywordsCost
                        ]
                    ]
                ];
            }
            
            // Construir operaciones individuales de esta campaña
            $operations = [];
            foreach ($campaign['operations'] as $opType => $opData) {
                $operations[] = [
                    'operation_type' => $opType,
                    'display_name' => $nameMap[$opType] ?? ucfirst($opType),
                    'count' => $opData['count'],
                    'tokens' => $opData['tokens'],
                    'cost' => $opData['cost']
                ];
            }
            
            $totalOps += $campaign['total_count'];
            $totalTokens += $campaign['total_tokens'];
            $totalCost += $campaign['total_cost'];
            
            $byOperation[] = [
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign['campaign_name'],
                'date' => $campaign['date'],
                'total_count' => $campaign['total_count'],
                'total_tokens' => $campaign['total_tokens'],
                'total_cost' => $campaign['total_cost'],
                'queues_count' => count($campaign['queues']),
                'queues_details' => $queuesDetails,
                'operations' => $operations,
                'is_campaign' => true
            ];
        }
    }
    // MODO LEGACY: Si NO hay campañas pero hay colas, mostrar colas agrupadas
    else if (!empty($queueGroups)) {
        $queuesDetails = [];
        $queuesTotalTokens = 0;
        $queuesTotalCost = 0;
        
        foreach ($queueGroups as $batchId => $queue) {
            $queuesTotalTokens += $queue['total_tokens'];
            $queuesTotalCost += $queue['total_cost'];
            
            $titlesCount = 0;
            $keywordsCount = 0;
            $titlesTokens = 0;
            $keywordsTokens = 0;
            $titlesCost = 0;
            $keywordsCost = 0;
            
            foreach ($queue['items'] as $item) {
                if ($item['type'] === 'title') {
                    $titlesCount += $item['count'];
                    $titlesTokens += $item['tokens'];
                    $titlesCost += $item['cost'];
                } elseif ($item['type'] === 'keywords_images') {
                    $keywordsCount += $item['count'];
                    $keywordsTokens += $item['tokens'];
                    $keywordsCost += $item['cost'];
                }
            }
            
            $queuesDetails[] = [
                'batch_id' => $batchId,
                'date' => $queue['date'],
                'total_tokens' => $queue['total_tokens'],
                'total_cost' => $queue['total_cost'],
                'subitems' => [
                    [
                        'type' => 'title',
                        'display_name' => $nameMap['title'],
                        'count' => $titlesCount,
                        'tokens' => $titlesTokens,
                        'cost' => $titlesCost
                    ],
                    [
                        'type' => 'keywords_images',
                        'display_name' => $nameMap['keywords_images'],
                        'count' => $keywordsCount,
                        'tokens' => $keywordsTokens,
                        'cost' => $keywordsCost
                    ]
                ]
            ];
        }
        
        $totalOps += count($queueGroups);
        $totalTokens += $queuesTotalTokens;
        $totalCost += $queuesTotalCost;
        
        // Añadir como entrada única "Colas Generadas"
        $byOperation[] = [
            'operation_type' => 'queue',
            'display_name' => $nameMap['queue'],
            'count' => count($queueGroups),
            'tokens' => $queuesTotalTokens,
            'cost' => $queuesTotalCost,
            'is_group' => true,
            'queues_details' => $queuesDetails
        ];
    }
    
    // Añadir operaciones individuales (sin campaña ni cola)
    foreach ($individualOperations as $opType => $opData) {
        $totalOps += $opData['count'];
        $totalTokens += $opData['tokens'];
        $totalCost += $opData['cost'];
        
        $byOperation[] = [
            'operation_type' => $opType,
            'display_name' => $nameMap[$opType] ?? ucfirst($opType),
            'count' => $opData['count'],
            'tokens' => $opData['tokens'],
            'cost' => $opData['cost'],
            'is_campaign' => false,
            'is_group' => false
        ];
    }
    
    // Construir datos por fecha
    foreach ($licenseData as $date => $dayData) {
        $byDate[] = [
            'date' => $date,
            'operations' => $dayData['operations'],
            'tokens' => $dayData['tokens_total'],
            'cost' => $dayData['cost_total']
        ];
    }
    
    // Ordenar: Campañas primero, luego colas (legacy), luego operaciones individuales
    usort($byOperation, function($a, $b) {
        // Campañas siempre primero
        $aIsCampaign = $a['is_campaign'] ?? false;
        $bIsCampaign = $b['is_campaign'] ?? false;
        
        if ($aIsCampaign && !$bIsCampaign) return -1;
        if (!$aIsCampaign && $bIsCampaign) return 1;
        
        // Colas (legacy mode) después de campañas
        $aIsQueue = ($a['operation_type'] ?? '') === 'queue';
        $bIsQueue = ($b['operation_type'] ?? '') === 'queue';
        
        if ($aIsQueue && !$bIsQueue) return -1;
        if (!$aIsQueue && $bIsQueue) return 1;
        
        // Entre campañas, ordenar por última operación (más reciente primero)
        if ($aIsCampaign && $bIsCampaign) {
            $aDate = $a['last_operation_at'] ?? ($a['date'] ?? '');
            $bDate = $b['last_operation_at'] ?? ($b['date'] ?? '');
            return strcmp($bDate, $aDate);
        }
        
        // Entre operaciones individuales, ordenar por count
        return ($b['count'] ?? 0) - ($a['count'] ?? 0);
    });
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'general' => [
                'total_operations' => $totalOps,
                'total_tokens' => $totalTokens,
                'total_cost' => round($totalCost, 4)
            ],
            'by_operation' => $byOperation,
            'by_date' => $byDate
        ]
    ]);
}

function resetStats() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $licenseKey = $input['license_key'] ?? '';
        
        if (empty($licenseKey)) {
            echo json_encode(['success' => false, 'error' => 'license_key requerido']);
            return;
        }
        
        $db = Database::getInstance();
        
        // Buscar licencia
        $license = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "licenses WHERE license_key = ?", [$licenseKey]);
        
        if (!$license) {
            echo json_encode(['success' => false, 'error' => 'Licencia no encontrada']);
            return;
        }
        
        // Eliminar TODOS los registros de tracking de esta licencia
        $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "usage_tracking WHERE license_id = ?");
        $deleted = $stmt->execute([$license['id']]);
        
        if ($deleted) {
            echo json_encode([
                'success' => true,
                'message' => 'Estadísticas reseteadas correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Error al resetear estadísticas'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
}
