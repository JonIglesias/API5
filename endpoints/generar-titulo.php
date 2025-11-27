<?php
/**
 * Endpoint: Generar Título Individual
 *
 * FEATURES v4.16:
 * - Inyección dinámica de títulos previos de la cola (evita duplicados)
 * - Parámetros de temperatura optimizados para diversidad
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

        // [NUEVO v4.16] Inyectar títulos previos de la cola sin tocar el .md
        $fullPrompt = $this->appendQueueContext($fullPrompt, $campaignId, 10);

        // [NUEVO v4.16] Parámetros optimizados para mayor diversidad
        $generationParams = [
            'prompt' => $fullPrompt,
            'max_tokens' => 200,
            'temperature' => 0.85,           // Más creatividad (antes: default 0.7)
            'frequency_penalty' => 0.4,      // Penalizar repetición de tokens
            'presence_penalty' => 0.3        // Fomentar nuevas ideas
        ];

        // Generar título
        $result = $this->openai->generateContent($generationParams);

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        $generatedTitle = trim($result['content']);

        // [NUEVO v4.16] Guardar título en la cola (si es parte de una campaña)
        if ($campaignId) {
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
