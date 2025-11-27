<?php
/**
 * Endpoint: Prompt de Contenido Personalizado
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class PromptContenidoEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $companyDesc = $this->params['company_description'] ?? '';
        $niche = $this->params['niche'] ?? '';
        $keywords = $this->params['keywords_seo'] ?? '';
        
        $promptTemplate = $this->loadPrompt('prompt-contenido');
        if (!$promptTemplate) {
            Response::error('Error cargando template', 500);
        }
        
        $companyDescSection = $companyDesc ? "DESCRIPCIÓN DE LA EMPRESA:\n---\n{$companyDesc}\n---\n\n" : '';
        $keywordsSection = $keywords ? "Keywords principales: {$keywords}\n\n" : '';
        $nicheSection = ($niche && strlen($niche) > 3) ? "Categoría: {$niche}\n\n" : '';
        
        $prompt = $this->replaceVariables($promptTemplate, [
            'company_description_section' => $companyDescSection,
            'keywords_section' => $keywordsSection,
            'niche_section' => $nicheSection
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 500
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        $this->trackUsage('content_prompt', $result);
        
        // FORMATO V4: Solo 'prompt'
        Response::success(['prompt' => trim($result['content'])]);
    }
}
