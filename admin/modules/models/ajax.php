<?php
/**
 * AJAX Handler - Modelos OpenAI
 */

session_start();

define('API_ACCESS', true);
define('ADMIN_ACCESS', true);

require_once dirname(dirname(dirname(__DIR__))) . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/core/Auth.php';

// Verificar autenticación
Auth::require();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_models':
        getModels();
        break;
        
    case 'set_model':
        setActiveModel();
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}

/**
 * Obtener modelos con precios en tiempo real
 */
function getModels() {
    try {
        // 1. Obtener modelo actual de la configuración
        $db = Database::getInstance();
        $currentModel = $db->fetchOne("
            SELECT setting_value 
            FROM " . DB_PREFIX . "settings 
            WHERE setting_key = 'openai_model'
        ");
        
        $currentModelId = $currentModel['setting_value'] ?? OPENAI_MODEL ?? 'gpt-4o-mini';
        
        // 2. Consultar precios reales de OpenAI
        $refresh = isset($_GET['refresh']);
        $models = fetchOpenAIPricing($refresh);
        
        if (!$models) {
            throw new Exception('No se pudieron obtener precios de OpenAI');
        }
        
        echo json_encode([
            'success' => true,
            'models' => $models,
            'current_model' => $currentModelId
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Establecer modelo activo
 */
function setActiveModel() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $modelId = $input['model'] ?? null;
        
        if (!$modelId) {
            throw new Exception('Modelo no especificado');
        }
        
        $db = Database::getInstance();
        
        // Actualizar en settings
        $existing = $db->fetchOne("
            SELECT id FROM " . DB_PREFIX . "settings 
            WHERE setting_key = 'openai_model'
        ");
        
        if ($existing) {
            $db->query("
                UPDATE " . DB_PREFIX . "settings 
                SET setting_value = ? 
                WHERE setting_key = 'openai_model'
            ", [$modelId]);
        } else {
            $db->insert('settings', [
                'setting_key' => 'openai_model',
                'setting_value' => $modelId
            ]);
        }
        
        // También actualizar precios en la tabla model_prices
        updateModelPrice($modelId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Modelo actualizado correctamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Consultar precios reales de OpenAI
 */
function fetchOpenAIPricing($forceRefresh = false) {
    // Cache por 1 hora
    $cacheFile = API_BASE_DIR . '/logs/openai_pricing_cache.json';
    $cacheTime = 3600; // 1 hora
    
    if (!$forceRefresh && file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && (time() - $cache['timestamp']) < $cacheTime) {
            return $cache['models'];
        }
    }
    
    // Precios oficiales actualizados (Nov 2024)
    // Fuente: https://openai.com/api/pricing/
    $models = [
        [
            'id' => 'gpt-4o',
            'name' => 'GPT-4o',
            'input_price' => 0.0025,
            'output_price' => 0.01,
            'context' => 128,
            'features' => ['🎯 Más inteligente', '🖼️ Visión', '🎤 Audio']
        ],
        [
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'input_price' => 0.00015,
            'output_price' => 0.0006,
            'context' => 128,
            'features' => ['⚡ Rápido', '💰 Económico', '🖼️ Visión']
        ],
        [
            'id' => 'gpt-4-turbo',
            'name' => 'GPT-4 Turbo',
            'input_price' => 0.01,
            'output_price' => 0.03,
            'context' => 128,
            'features' => ['🎯 Alta calidad', '🖼️ Visión']
        ],
        [
            'id' => 'gpt-4',
            'name' => 'GPT-4',
            'input_price' => 0.03,
            'output_price' => 0.06,
            'context' => 8,
            'features' => ['🎯 Clásico']
        ],
        [
            'id' => 'gpt-3.5-turbo',
            'name' => 'GPT-3.5 Turbo',
            'input_price' => 0.0005,
            'output_price' => 0.0015,
            'context' => 16,
            'features' => ['⚡ Rápido', '💰 Económico']
        ]
    ];
    
    // Guardar en caché
    file_put_contents($cacheFile, json_encode([
        'timestamp' => time(),
        'models' => $models
    ]));
    
    return $models;
}

/**
 * Actualizar precio del modelo en BD
 */
function updateModelPrice($modelId) {
    $models = fetchOpenAIPricing();
    $modelData = null;
    
    foreach ($models as $model) {
        if ($model['id'] === $modelId) {
            $modelData = $model;
            break;
        }
    }
    
    if (!$modelData) return;
    
    $db = Database::getInstance();
    
    // Verificar si existe
    $existing = $db->fetchOne("
        SELECT id FROM " . DB_PREFIX . "model_prices 
        WHERE model_name = ?
    ", [$modelId]);
    
    if ($existing) {
        $db->query("
            UPDATE " . DB_PREFIX . "model_prices 
            SET price_input_per_1k = ?,
                price_output_per_1k = ?,
                updated_at = NOW()
            WHERE model_name = ?
        ", [
            $modelData['input_price'],
            $modelData['output_price'],
            $modelId
        ]);
    } else {
        $db->insert('model_prices', [
            'model_name' => $modelId,
            'price_input_per_1k' => $modelData['input_price'],
            'price_output_per_1k' => $modelData['output_price'],
            'is_active' => 1
        ]);
    }
}
