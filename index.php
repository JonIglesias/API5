<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers CORS - ANTES de cualquier output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * API Claude V5 - REFACTORIZADA
 *
 * Cada endpoint en su propio archivo
 * Todos los prompts en /prompts/
 *
 * @version 5.5
 */

define('API_ACCESS', true);

require_once __DIR__ . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/core/Router.php';
require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/Logger.php';

session_start();

$route = $_GET['route'] ?? '';
$router = new Router();

Logger::api('info', 'Incoming request', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'route' => $route,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

// ============================================================================
// CAPA DE COMPATIBILIDAD V4 (Plugin WordPress)
// ============================================================================

require_once __DIR__ . '/v4-compat.php';

// ============================================================================
// LICENCIAS Y CONFIGURACIÓN
// ============================================================================

$router->post('verify', function() {
    require_once API_BASE_DIR . '/endpoints/verificar-licencia.php';
    $endpoint = new VerificarLicenciaEndpoint();
    $endpoint->handle();
});

$router->get('plan/active', function() {
    require_once API_BASE_DIR . '/endpoints/plan-activo.php';
    $endpoint = new PlanActivoEndpoint();
    $endpoint->handle();
});

$router->get('settings', function() {
    require_once API_BASE_DIR . '/endpoints/configuracion.php';
    $endpoint = new ConfiguracionEndpoint();
    $endpoint->handle();
});

// ============================================================================
// GENERACIÓN DE CONTENIDO - ENDPOINTS SEPARADOS
// ============================================================================

// Prompt de títulos personalizado
$router->post('generate/prompt-titulos', function() {
    require_once API_BASE_DIR . '/endpoints/prompt-titulos.php';
    $endpoint = new PromptTitulosEndpoint();
    $endpoint->handle();
});

// Descripción de empresa desde dominio
$router->post('generate/descripcion-empresa', function() {
    require_once API_BASE_DIR . '/endpoints/descripcion-empresa.php';
    $endpoint = new DescripcionEmpresaEndpoint();
    $endpoint->handle();
});

// Prompt personalizado de contenido
$router->post('generate/prompt-contenido', function() {
    require_once API_BASE_DIR . '/endpoints/prompt-contenido.php';
    $endpoint = new PromptContenidoEndpoint();
    $endpoint->handle();
});

// Keywords SEO
$router->post('generate/keywords-seo', function() {
    require_once API_BASE_DIR . '/endpoints/keywords-seo.php';
    $endpoint = new KeywordsSEOEndpoint();
    $endpoint->handle();
});

// Keywords para imágenes
$router->post('generate/keywords-imagenes', function() {
    require_once API_BASE_DIR . '/endpoints/keywords-imagenes.php';
    $endpoint = new KeywordsImagenesEndpoint();
    $endpoint->handle();
});

// Keywords de campaña (base de imágenes)
$router->post('generate/keywords-campana', function() {
    require_once API_BASE_DIR . '/endpoints/keywords-campana.php';
    $endpoint = new KeywordsCampanaEndpoint();
    $endpoint->handle();
});

// Título individual
$router->post('generate/titulo', function() {
    require_once API_BASE_DIR . '/endpoints/generar-titulo.php';
    $endpoint = new GenerarTituloEndpoint();
    $endpoint->handle();
});

// Contenido del post
$router->post('generate/contenido', function() {
    require_once API_BASE_DIR . '/endpoints/generar-contenido.php';
    $endpoint = new GenerarContenidoEndpoint();
    $endpoint->handle();
});

// ============================================================================
// ESTADÍSTICAS
// ============================================================================

$router->post('stats/license', function() {
    require_once API_BASE_DIR . '/endpoints/estadisticas-licencia.php';
    $endpoint = new EstadisticasLicenciaEndpoint();
    $endpoint->handle();
});

// Admin stats (si existe)
$router->post('get-stats', function() {
    require_once API_BASE_DIR . '/controllers/StatsController.php';
    $controller = new StatsController();
    $controller->getStats();
});

$router->post('reset-stats', function() {
    require_once API_BASE_DIR . '/controllers/StatsController.php';
    $controller = new StatsController();
    $controller->resetStats();
});

// ============================================================================
// WEBHOOKS
// ============================================================================

// Endpoint de prueba GET (para verificar que el webhook sea accesible)
$router->get('webhooks/woocommerce', function() {
    Response::success([
        'message' => 'Webhook endpoint is active and accessible',
        'endpoint' => 'POST /webhooks/woocommerce',
        'version' => API_VERSION,
        'timestamp' => date(DATE_FORMAT)
    ]);
});

// Webhook principal POST
$router->post('webhooks/woocommerce', function() {
    require_once API_BASE_DIR . '/services/WebhookHandler.php';
    $handler = new WebhookHandler();
    $handler->handle();
});

// ============================================================================
// CRON - AUTO SYNC
// ============================================================================

// Endpoint para ejecutar auto-sync via cron (usar con curl)
$router->get('cron/auto-sync', function() {
    require_once API_BASE_DIR . '/services/AutoSyncService.php';

    $syncType = $_GET['type'] ?? 'recent';
    $autoSync = new AutoSyncService();

    if ($syncType === 'full') {
        $results = $autoSync->syncAll();
    } else {
        $results = $autoSync->syncRecent(2);
    }

    // Guardar estado del cron
    $autoSync->saveCronStatus($results, $syncType);

    // Limpiar logs antiguos (cada ejecución, pero solo borra si hay algo viejo)
    // DB: 30 días, Archivos: 7 días o >10MB
    $cleaned = $autoSync->cleanOldLogs(30, 7, 10);

    // Log
    Logger::sync('info', "Cron auto-sync completed ({$syncType})", [
        'type' => $syncType,
        'results' => $results
    ]);

    Response::success([
        'message' => 'Auto-sync completed',
        'type' => $syncType,
        'results' => $results,
        'logs_cleaned' => $cleaned
    ]);
});

// ============================================================================
// ADMIN
// ============================================================================

$router->get('admin', function() {
    header('Location: ' . API_BASE_URL . '/admin/index.php');
    exit;
});

// ============================================================================
// EJECUTAR
// ============================================================================

try {
    $router->run($route);
} catch (Exception $e) {
    Logger::api('error', 'Unhandled exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    Response::error('Internal server error: ' . $e->getMessage(), 500, 'INTERNAL_ERROR');
} catch (Error $e) {
    Logger::api('error', 'Fatal error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    Response::error('Fatal error: ' . $e->getMessage(), 500, 'FATAL_ERROR');
}
