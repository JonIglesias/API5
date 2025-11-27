<?php
define('API_ACCESS', true);
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';

Auth::require();

header('Content-Type: application/json');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? '';
    
    if ($action === 'get' && $id) {
        $plan = $db->fetchOne("SELECT * FROM " . DB_PREFIX . "plans WHERE id = ?", [$id]);
        Response::success(['data' => $plan]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'save':
                $data = $input['data'];
                $id = $data['id'] ?? null;
                
                if ($id) {
                    // Update
                    $db->update('plans', [
                        'name' => $data['name'],
                        'tokens_per_month' => $data['tokens_per_month'],
                        'billing_cycle' => $data['billing_cycle'],
                        'woo_product_id' => $data['woo_product_id'] ?: null
                    ], 'id = ?', [$id]);
                    
                    // ⭐ NUEVO: Actualizar tokens_limit en todas las licencias que usan este plan
                    $db->query("
                        UPDATE " . DB_PREFIX . "licenses 
                        SET tokens_limit = ? 
                        WHERE plan_id = ? 
                        AND status = 'active'
                    ", [$data['tokens_per_month'], $id]);
                    
                    // Obtener cuántas licencias se actualizaron
                    $updatedCount = $db->query("
                        SELECT COUNT(*) as count 
                        FROM " . DB_PREFIX . "licenses 
                        WHERE plan_id = ? 
                        AND status = 'active'
                    ", [$id])[0]['count'];
                    
                    Response::success([
                        'message' => "Plan guardado. {$updatedCount} licencias activas actualizadas con el nuevo límite de tokens."
                    ]);
                } else {
                    // Insert
                    $db->insert('plans', [
                        'id' => $data['plan_id'],
                        'name' => $data['name'],
                        'tokens_per_month' => $data['tokens_per_month'],
                        'billing_cycle' => $data['billing_cycle'],
                        'woo_product_id' => $data['woo_product_id'] ?: null,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    Response::success(['message' => 'Plan creado']);
                }
                break;
                
            case 'toggle':
                $db->update('plans', 
                    ['is_active' => $input['is_active']],
                    'id = ?',
                    [$input['id']]
                );
                Response::success(['message' => 'Estado actualizado']);
                break;
                
            default:
                Response::error('Acción no válida', 400);
        }
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}
