<?php
/**
 * AJAX Handler para guardar prompts .md
 */

// Definir acceso antes de cargar config
define('API_ACCESS', true);

// Cargar configuración
require_once __DIR__ . '/../../../config.php';
session_start();

// Verificar autenticación
require_once API_BASE_DIR . '/core/Auth.php';
if (!Auth::check()) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => 'No autenticado']));
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $slug = $input['slug'] ?? '';
    $content = $input['content'] ?? '';
    
    if (!$slug) {
        echo json_encode(['success' => false, 'error' => 'Slug requerido']);
        exit;
    }
    
    // Validar slug (solo letras, números y guiones)
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        echo json_encode(['success' => false, 'error' => 'Slug inválido']);
        exit;
    }
    
    $prompts_dir = API_BASE_DIR . '/prompts/';
    $file_path = $prompts_dir . $slug . '.md';
    
    if (!file_exists($file_path)) {
        echo json_encode(['success' => false, 'error' => 'Archivo no existe']);
        exit;
    }
    
    // Guardar
    $result = file_put_contents($file_path, $content);
    
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => 'Error al escribir archivo']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Prompt guardado correctamente',
        'bytes' => $result
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
