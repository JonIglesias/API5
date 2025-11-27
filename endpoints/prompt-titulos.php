<?php
/**
 * Endpoint: Generar Prompt Maestro de Títulos
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class PromptTitulosEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $companyDesc = $this->params['company_description'] ?? null;
        if (!$companyDesc) {
            Response::error('company_description es requerido', 400);
        }
        
        $niche = $this->params['niche'] ?? '';
        $keywords = $this->params['keywords_seo'] ?? [];
        $keywordsStr = is_array($keywords) && !empty($keywords) ? implode(', ', $keywords) : '';
        
        $promptTemplate = $this->loadPrompt('prompt-titulos');
        if (!$promptTemplate) {
            Response::error('Error cargando template', 500);
        }
        
        $keywordsSection = $keywordsStr ? "Keywords SEO preferidas: {$keywordsStr}\n\n" : '';
        $nicheSection = ($niche && strlen($niche) > 3) ? "Nota: El sector es '{$niche}'\n\n" : '';
        
        $prompt = $this->replaceVariables($promptTemplate, [
            'company_description' => $companyDesc,
            'keywords_section' => $keywordsSection,
            'niche_section' => $nicheSection
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 1000
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        $this->trackUsage('title_prompt', $result);
        
        // FORMATO V4: Solo 'prompt'
        Response::success(['prompt' => trim($result['content'])]);
    }
}
