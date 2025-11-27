<?php
/**
 * Endpoint: Generar Título Individual
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

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
        
        if (!$prompt && !$topic) {
            Response::error('Prompt o topic es requerido', 400);
        }
        
        // Cargar template
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
        
        // Reemplazar en template con las variables CORRECTAS
        $fullPrompt = $this->replaceVariables($template, [
            'company_description' => $companyDescription,
            'title_prompt' => $titlePrompt,
            'keywords_seo' => $allKeywords
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $fullPrompt,
            'max_tokens' => 200
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        $this->trackUsage('title', $result);
        
        Response::success(['title' => trim($result['content'])]);
    }
}
