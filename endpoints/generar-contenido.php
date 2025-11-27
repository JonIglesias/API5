<?php
/**
 * Endpoint: Generar Contenido de Post
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class GenerarContenidoEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $title = $this->params['title'] ?? $this->params['prompt'] ?? null;
        if (!$title) {
            Response::error('title es requerido', 400);
        }
        
        $minWords = $this->params['min_words'] ?? 800;
        $maxWords = $this->params['max_words'] ?? 1500;
        $niche = $this->params['niche'] ?? '';
        $companyDesc = $this->params['company_description'] ?? '';
        $keywords = $this->params['keywords_seo'] ?? '';
        $customPrompt = $this->params['custom_prompt'] ?? '';
        
        $promptTemplate = $this->loadPrompt('generar-contenido');
        if (!$promptTemplate) {
            Response::error('Error cargando template', 500);
        }
        
        // Template espera: {{custom_prompt}}, {{title}}, {{niche}}, {{company_description}}, {{min_words}}, {{max_words}}
        $prompt = $this->replaceVariables($promptTemplate, [
            'custom_prompt' => $customPrompt,  // Este es el PluginContentPrompt del plugin
            'title' => $title,
            'niche' => $niche,
            'company_description' => $companyDesc,
            'min_words' => $minWords,
            'max_words' => $maxWords
        ]);
        
        $maxTokens = (int)(($maxWords * 1.5) + 500);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => min($maxTokens, 4000)
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        // Limpiar HTML
        $content = preg_replace('/```html\s*/i', '', $result['content']);
        $content = preg_replace('/```\s*$/i', '', $content);
        
        $this->trackUsage('content', $result);
        
        // FORMATO V4: Solo 'content'
        Response::success(['content' => trim($content)]);
    }
}
