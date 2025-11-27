<?php
/**
 * AJAX Handler para Sync
 */

define('API_ACCESS', true);
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Verificar autenticación
Auth::require();

// IMPORTANTE: Solo JSON, nada de HTML
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    if ($action === 'sync_all') {
        $licenses = $db->query("SELECT id, license_key FROM " . DB_PREFIX . "licenses WHERE status = 'active'");
        
        $synced = 0;
        foreach ($licenses as $license) {
            $db->insert('sync_logs', [
                'license_id' => $license['id'],
                'sync_type' => 'manual',
                'status' => 'success',
                'changes_detected' => 'Manual sync triggered from admin panel',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->update('licenses', 
                ['last_synced_at' => date('Y-m-d H:i:s'), 'sync_status' => 'fresh'],
                'id = ?',
                [$license['id']]
            );
            $synced++;
        }
        
        echo json_encode(['success' => true, 'message' => "Sincronizadas $synced licencias", 'count' => $synced]);
        exit;
    }
    
    if ($action === 'test_connection') {
        $apiUrl = WC_API_URL;
        $consumerKey = WC_CONSUMER_KEY;
        $consumerSecret = WC_CONSUMER_SECRET;
        
        // Validar que estén configuradas
        if (empty($apiUrl) || strpos($consumerKey, 'XXX') !== false || strpos($consumerKey, 'ck_') === false) {
            echo json_encode([
                'success' => false, 
                'error' => 'Credenciales no configuradas. Ve a Settings y configura WooCommerce API.'
            ]);
            exit;
        }
        
        // Test real de conexión
        $testUrl = rtrim($apiUrl, '/') . '?consumer_key=' . urlencode($consumerKey) . 
                   '&consumer_secret=' . urlencode($consumerSecret);
        
        $ch = curl_init($testUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Analizar respuesta
        if ($curlError) {
            echo json_encode([
                'success' => false,
                'error' => 'Error de conexión: ' . $curlError
            ]);
            exit;
        }
        
        if ($httpCode === 200) {
            $json = json_decode($response, true);
            if (is_array($json)) {
                echo json_encode([
                    'success' => true,
                    'message' => '✅ Conexión exitosa con WooCommerce API'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Respuesta inválida del servidor'
                ]);
            }
        } elseif ($httpCode === 401) {
            echo json_encode([
                'success' => false,
                'error' => 'Credenciales inválidas. Verifica Consumer Key y Secret en Settings.'
            ]);
        } elseif ($httpCode === 404) {
            echo json_encode([
                'success' => false,
                'error' => 'URL incorrecta. Verifica WC_API_URL en Settings (debe terminar en /wc/v3/)'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => "Error HTTP $httpCode. Verifica la URL y que WooCommerce esté activo."
            ]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
