<?php
/**
 * Endpoint: Generar Título Individual
 *
 * FEATURES v4.18:
 * - Inyección dinámica de títulos previos de la cola (evita duplicados)
 * - Detección de similitud con Levenshtein + similar_text (umbral: 75% - MÁS ESTRICTO)
 * - Sistema de reintentos si título es similar (máx: 3 intentos)
 * - Parámetros de temperatura optimizados para diversidad
 * - Auto-creación de tabla si no existe
 * - Limpieza automática de títulos >24h en cada request
 * - Almacenamiento de títulos en queue_titles para tracking
 * - LOGS DE DEBUG EXTENSIVOS para troubleshooting
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';
require_once API_BASE_DIR . '/services/TitleQueueManager.php';

class GenerarTituloEndpoint extends BaseEndpoint {

    public function handle() {
        $this->validateLicense();

        // Datos principales
        $prompt = $this->params['prompt'] ?? null;
        $topic = $this->params['topic'] ?? null;

        // Contexto adicional
        $domain = $this->params['domain'] ?? null;
        $companyDesc = $this->params['company_description'] ?? null;
        $keywordsSEO = $this->params['keywords_seo'] ?? [];
        $keywords = $this->params['keywords'] ?? [];

        // Campaign ID para tracking de cola
        $campaignId = $this->params['campaign_id'] ?? null;

        if (!$prompt && !$topic) {
            Response::error('Prompt o topic es requerido', 400);
        }

        // DEBUG: Log campaign info
        error_log("=== TitleGenerator START ===");
        error_log("Campaign ID: " . ($campaignId ?: 'NULL (operación individual)'));
        error_log("License ID: " . $this->license['id']);

        // Auto-limpieza de títulos antiguos al inicio de cada request
        if ($campaignId) {
            $cleanupResult = TitleQueueManager::autoCleanup(24);
            error_log("Auto-cleanup executed: " . ($cleanupResult ? 'OK' : 'FAILED'));
        }

        // DEBUG: Verificar cuántos títulos hay en la cola ANTES de generar
        if ($campaignId) {
            $previousTitles = TitleQueueManager::getTitles($campaignId, 50);
            error_log("Títulos previos en cola: " . count($previousTitles));
            if (!empty($previousTitles)) {
                error_log("Últimos 3 títulos: " . implode(' | ', array_slice($previousTitles, 0, 3)));
            }
        }

        // Cargar template base (archivo .md editable)
        $template = $this->loadPrompt('generar-titulo');
        if (!$template) {
            Response::error('Error cargando template', 500);
        }

        // Preparar datos para las variables del template
        $companyDescription = $companyDesc ?? '';
        $titlePrompt = $prompt ?? $topic ?? '';

        // Combinar keywords SEO
        $keywordsSEOStr = is_array($keywordsSEO) && !empty($keywordsSEO) ? implode(', ', $keywordsSEO) : '';
        $keywordsStr = is_array($keywords) && !empty($keywords) ? implode(', ', $keywords) : '';
        $allKeywords = trim($keywordsSEOStr . ($keywordsSEOStr && $keywordsStr ? ', ' : '') . $keywordsStr);

        // Reemplazar variables en template
        $fullPrompt = $this->replaceVariables($template, [
            'company_description' => $companyDescription,
            'title_prompt' => $titlePrompt,
            'keywords_seo' => $allKeywords
        ]);

        // [NUEVO v4.18] Umbral más estricto: 75% (antes 85%)
        $similarityThreshold = 0.75;

        // Sistema de reintentos con detección de similitud
        $maxAttempts = 3;
        $generatedTitle = null;
        $lastSimilarityInfo = null;
        $debugInfo = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            error_log("--- Intento #{$attempt}/{$maxAttempts} ---");

            // Inyectar títulos previos (se actualiza en cada intento)
            $promptWithContext = $this->appendQueueContext($fullPrompt, $campaignId, 15); // Aumentado a 15 títulos

            // DEBUG: Verificar si se inyectó contexto
            $contextInjected = strlen($promptWithContext) > strlen($fullPrompt);
            error_log("Contexto de títulos inyectado: " . ($contextInjected ? 'SÍ (' . (strlen($promptWithContext) - strlen($fullPrompt)) . ' chars)' : 'NO'));

            // En reintentos, añadir advertencia
            if ($attempt > 1 && $lastSimilarityInfo) {
                $promptWithContext .= "\n\nWARNING - Attempt #{$attempt}: Previous title was rejected ({$lastSimilarityInfo['similarity_percent']}% similar to existing title). Generate a completely different title.\n";
            }

            // Parámetros optimizados (aumentar temperatura en reintentos)
            $temperature = 0.85 + (($attempt - 1) * 0.05); // 0.85 → 0.90 → 0.95
            $generationParams = [
                'prompt' => $promptWithContext,
                'max_tokens' => 200,
                'temperature' => min($temperature, 1.0),
                'frequency_penalty' => 0.5 + (($attempt - 1) * 0.1), // 0.5 → 0.6 → 0.7 (MÁS AGRESIVO)
                'presence_penalty' => 0.4 // Aumentado de 0.3 a 0.4
            ];

            error_log("OpenAI params: temp=" . $generationParams['temperature'] . ", freq_pen=" . $generationParams['frequency_penalty']);

            // Generar título
            $result = $this->openai->generateContent($generationParams);

            if (!$result['success']) {
                Response::error($result['error'], 500);
            }

            $generatedTitle = trim($result['content']);
            error_log("Título generado: " . $generatedTitle);

            // Verificar similitud con títulos existentes
            if ($campaignId) {
                $similarityCheck = TitleQueueManager::isSimilarToAny($campaignId, $generatedTitle, $similarityThreshold);

                error_log("Similitud check: " . ($similarityCheck['is_similar'] ? "RECHAZADO" : "ACEPTADO") . " - {$similarityCheck['similarity_percent']}% (umbral: " . ($similarityThreshold * 100) . "%)");

                if ($similarityCheck['similar_to']) {
                    error_log("Más similar a: " . $similarityCheck['similar_to']);
                }

                $debugInfo["attempt_{$attempt}"] = [
                    'title' => $generatedTitle,
                    'similarity_percent' => $similarityCheck['similarity_percent'],
                    'is_similar' => $similarityCheck['is_similar'],
                    'similar_to' => $similarityCheck['similar_to'],
                    'temperature' => $generationParams['temperature'],
                    'frequency_penalty' => $generationParams['frequency_penalty']
                ];

                if ($similarityCheck['is_similar']) {
                    // Título muy similar - reintentar si quedan intentos
                    $lastSimilarityInfo = $similarityCheck;

                    if ($attempt < $maxAttempts) {
                        error_log("→ REINTENTANDO... (similitud: {$similarityCheck['similarity_percent']}%)");
                        continue; // Reintentar
                    } else {
                        // Último intento - aceptar aunque sea similar
                        error_log("→ ACEPTANDO en último intento (similitud: {$similarityCheck['similarity_percent']}%)");
                    }
                }
            }

            // Título aceptado - salir del loop
            error_log("→ TÍTULO ACEPTADO");
            break;
        }

        // Guardar título en la cola (si es parte de una campaña)
        if ($campaignId && $generatedTitle) {
            $saved = TitleQueueManager::addTitle(
                $campaignId,
                $this->license['id'],
                $generatedTitle
            );
            error_log("Título guardado en DB: " . ($saved ? 'OK' : 'FAILED'));

            // Verificar que realmente se guardó
            $titlesAfter = TitleQueueManager::getTitles($campaignId, 1);
            error_log("Verificación guardado - Último título en DB: " . ($titlesAfter[0] ?? 'NINGUNO'));
        }

        error_log("=== TitleGenerator END ===\n");

        // Trackear uso
        $this->trackUsage('title', $result);

        // [NUEVO v4.18] Incluir info de debug en respuesta (solo si hay campaignId)
        $response = ['title' => $generatedTitle];

        if ($campaignId && !empty($debugInfo)) {
            $response['_debug'] = [
                'attempts' => count($debugInfo),
                'threshold' => ($similarityThreshold * 100) . '%',
                'attempts_detail' => $debugInfo
            ];
        }

        Response::success($response);
    }
}
