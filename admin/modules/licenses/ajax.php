<?php
/**
 * Licencias - AJAX Handler
 */

define('API_ACCESS', true);
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';

Auth::require();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$db = Database::getInstance();

try {
    switch ($action) {
        case 'suspend':
            $id = $input['id'] ?? 0;
            $db->update('licenses', 
                ['status' => 'suspended'],
                'id = ?',
                [$id]
            );
            Response::success(['message' => 'Licencia suspendida']);
            break;
            
        case 'activate':
            $id = $input['id'] ?? 0;
            $db->update('licenses',
                ['status' => 'active'],
                'id = ?',
                [$id]
            );
            Response::success(['message' => 'Licencia activada']);
            break;
            
        case 'delete':
            $id = $input['id'] ?? 0;
            $db->delete('licenses', 'id = ?', [$id]);
            Response::success(['message' => 'Licencia eliminada']);
            break;
            
        default:
            Response::error('Acción no válida', 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}
