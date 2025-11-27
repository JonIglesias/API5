<?php
/**
 * Endpoint: Keywords SEO
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class KeywordsSEOEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $companyDesc = $this->params['company_description'] ?? null;
        if (!$companyDesc) {
            Response::error('company_description es requerido', 400);
        }
        
        $niche = $this->params['niche'] ?? '';
        
        $promptTemplate = $this->loadPrompt('keywords-seo');
        if (!$promptTemplate) {
            Response::error('Error cargando template', 500);
        }
        
        $nicheSection = ($niche && strlen($niche) > 3) 
            ? "Categoría general: {$niche}\n\n"
            : '';
        
        $prompt = $this->replaceVariables($promptTemplate, [
            'company_description' => $companyDesc,
            'niche_section' => $nicheSection
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 500
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        $this->trackUsage('keywords_seo', $result);
        
        // FORMATO V4: Solo 'keywords'
        Response::success(['keywords' => trim($result['content'])]);
    }
}
