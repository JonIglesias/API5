<?php
/**
 * Capa de Compatibilidad V4 → V5
 * 
 * Este archivo mapea todos los endpoints antiguos (V4) a los nuevos (V5)
 * para mantener 100% de retrocompatibilidad con el plugin WordPress.
 * 
 * IMPORTANTE: No modificar estos mappings sin actualizar el plugin
 * 
 * @version 1.0
 * @since API V5
 */

defined('API_ACCESS') or die('Direct access not permitted');

// ============================================================================
// ENDPOINTS DE COMPATIBILIDAD V4
// ============================================================================

/**
 * generate-meta?type=company_description
 * → generate/descripcion-empresa
 */
$router->post('generate-meta', function() {
    $params = Response::getJsonInput();
    $type = $params['type'] ?? null;
    
    switch($type) {
        case 'company_description':
            require_once API_BASE_DIR . '/endpoints/descripcion-empresa.php';
            $endpoint = new DescripcionEmpresaEndpoint();
            $endpoint->handle();
            break;
            
        case 'title_prompt':
            require_once API_BASE_DIR . '/endpoints/prompt-titulos.php';
            $endpoint = new PromptTitulosEndpoint();
            $endpoint->handle();
            break;
            
        default:
            Response::error("Tipo no soportado: {$type}", 400);
    }
});

/**
 * generate-content-prompt
 * → generate/prompt-contenido
 */
$router->post('generate-content-prompt', function() {
    require_once API_BASE_DIR . '/endpoints/prompt-contenido.php';
    $endpoint = new PromptContenidoEndpoint();
    $endpoint->handle();
});

/**
 * generate-keywords?type=seo
 * generate-keywords?type=images
 * → generate/keywords-seo
 * → generate/keywords-imagenes
 */
$router->post('generate-keywords', function() {
    $params = Response::getJsonInput();
    $type = $params['type'] ?? null;
    
    switch($type) {
        case 'seo':
            require_once API_BASE_DIR . '/endpoints/keywords-seo.php';
            $endpoint = new KeywordsSEOEndpoint();
            $endpoint->handle();
            break;
            
        case 'images':
            require_once API_BASE_DIR . '/endpoints/keywords-imagenes.php';
            $endpoint = new KeywordsImagenesEndpoint();
            $endpoint->handle();
            break;
            
        default:
            Response::error("Tipo no soportado: {$type}", 400);
    }
});

/**
 * generate-image-keys-campaign
 * → generate/keywords-campana
 */
$router->post('generate-image-keys-campaign', function() {
    require_once API_BASE_DIR . '/endpoints/keywords-campana.php';
    $endpoint = new KeywordsCampanaEndpoint();
    $endpoint->handle();
});

/**
 * generate-title
 * → generate/titulo
 */
$router->post('generate-title', function() {
    require_once API_BASE_DIR . '/endpoints/generar-titulo.php';
    $endpoint = new GenerarTituloEndpoint();
    $endpoint->handle();
});

/**
 * generate-post
 * → generate/contenido
 */
$router->post('generate-post', function() {
    require_once API_BASE_DIR . '/endpoints/generar-contenido.php';
    $endpoint = new GenerarContenidoEndpoint();
    $endpoint->handle();
});

/**
 * get-active-plan
 * → plan/active
 */
$router->get('get-active-plan', function() {
    require_once API_BASE_DIR . '/endpoints/plan-activo.php';
    $endpoint = new PlanActivoEndpoint();
    $endpoint->handle();
});

/**
 * get-settings
 * → settings
 */
$router->get('get-settings', function() {
    require_once API_BASE_DIR . '/endpoints/configuracion.php';
    $endpoint = new ConfiguracionEndpoint();
    $endpoint->handle();
});

/**
 * get-license-stats
 * → stats/license
 */
$router->post('get-license-stats', function() {
    require_once API_BASE_DIR . '/endpoints/estadisticas-licencia.php';
    $endpoint = new EstadisticasLicenciaEndpoint();
    $endpoint->handle();
});

/**
 * usage (POST) - Endpoint V3 legacy
 * Mantenido para compatibilidad extrema
 */
$router->post('usage', function() {
    // Este endpoint ya no se usa en V4 del plugin, pero lo mantenemos
    // por si hay instalaciones antiguas
    require_once API_BASE_DIR . '/endpoints/plan-activo.php';
    $endpoint = new PlanActivoEndpoint();
    $endpoint->handle();
});

/**
 * usage (GET) - Endpoint V3 legacy
 */
$router->get('usage', function() {
    require_once API_BASE_DIR . '/endpoints/plan-activo.php';
    $endpoint = new PlanActivoEndpoint();
    $endpoint->handle();
});

// ============================================================================
// LOG DE COMPATIBILIDAD
// ============================================================================

Logger::api('debug', 'Capa de compatibilidad V4 cargada', [
    'mapped_endpoints' => [
        'generate-meta',
        'generate-content-prompt',
        'generate-keywords',
        'generate-image-keys-campaign',
        'generate-title',
        'generate-post',
        'get-active-plan',
        'get-settings',
        'get-license-stats',
        'usage'
    ]
]);
