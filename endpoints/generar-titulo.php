<?php
/**
 * Endpoint: Generar Título Individual
 *
 * FEATURES v4.17:
 * - Inyección dinámica de títulos previos de la cola (evita duplicados)
 * - Detección de similitud con Levenshtein + similar_text (umbral: 85%)
 * - Sistema de reintentos si título es similar (máx: 3 intentos)
 * - Parámetros de temperatura optimizados para diversidad
 * - Auto-creación de tabla si no existe
 * - Limpieza automática de títulos >24h en cada request
 * - Almacenamiento de títulos en queue_titles para tracking
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

        // [NUEVO v4.16] Campaign ID para tracking de cola
        $campaignId = $this->params['campaign_id'] ?? null;

        if (!$prompt && !$topic) {
            Response::error('Prompt o topic es requerido', 400);
        }

        // [NUEVO v4.17] Auto-limpieza de títulos antiguos al inicio de cada request
        if ($campaignId) {
            TitleQueueManager::autoCleanup(24);
        }

        // Cargar template base (archivo .md editable)
        $template = $this->loadPrompt('generar-titulo');
        if (!$template) {
            Response::error('Error cargando template', 500);
        }

        // Preparar datos para las variables del template
        // Template espera: {{company_description}}, {{title_prompt}}, {{keywords_seo}}

        $companyDescription = $companyDesc ?? '';

        // El prompt del plugin (PluginTitlePrompt) se inserta en title_prompt
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

        // [NUEVO v4.17] Sistema de reintentos con detección de similitud
        $maxAttempts = 3;
        $generatedTitle = null;
        $lastSimilarityInfo = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Inyectar títulos previos (se actualiza en cada intento)
            $promptWithContext = $this->appendQueueContext($fullPrompt, $campaignId, 10);

            // En reintentos, añadir instrucción adicional
            if ($attempt > 1 && $lastSimilarityInfo) {
                $promptWithContext .= "\n\n⚠️ INTENTO #{$attempt}: El título anterior era muy similar a:\n";
                $promptWithContext .= "\"" . $lastSimilarityInfo['similar_to'] . "\"\n";
                $promptWithContext .= "Genera un título COMPLETAMENTE DIFERENTE en estructura, enfoque y palabras.\n";
            }

            // Parámetros optimizados (aumentar temperatura en reintentos)
            $temperature = 0.85 + (($attempt - 1) * 0.05); // 0.85 → 0.90 → 0.95
            $generationParams = [
                'prompt' => $promptWithContext,
                'max_tokens' => 200,
                'temperature' => min($temperature, 1.0),
                'frequency_penalty' => 0.4 + (($attempt - 1) * 0.1), // 0.4 → 0.5 → 0.6
                'presence_penalty' => 0.3
            ];

            // Generar título
            $result = $this->openai->generateContent($generationParams);

            if (!$result['success']) {
                Response::error($result['error'], 500);
            }

            $generatedTitle = trim($result['content']);

            // [NUEVO v4.17] Verificar similitud con títulos existentes
            if ($campaignId) {
                $similarityCheck = TitleQueueManager::isSimilarToAny($campaignId, $generatedTitle, 0.85);

                if ($similarityCheck['is_similar']) {
                    // Título muy similar - reintentar si quedan intentos
                    $lastSimilarityInfo = $similarityCheck;

                    if ($attempt < $maxAttempts) {
                        error_log("TitleGenerator: Título similar detectado (intento {$attempt}/{$maxAttempts}). Similitud: {$similarityCheck['similarity_percent']}%");
                        continue; // Reintentar
                    } else {
                        // Último intento - aceptar aunque sea similar
                        error_log("TitleGenerator: Título similar en último intento. Similitud: {$similarityCheck['similarity_percent']}%. Aceptando...");
                    }
                }
            }

            // Título aceptado - salir del loop
            break;
        }

        // Guardar título en la cola (si es parte de una campaña)
        if ($campaignId && $generatedTitle) {
            TitleQueueManager::addTitle(
                $campaignId,
                $this->license['id'],
                $generatedTitle
            );
        }

        // Trackear uso
        $this->trackUsage('title', $result);

        Response::success(['title' => $generatedTitle]);
    }
}
