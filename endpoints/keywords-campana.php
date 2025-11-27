<?php
/**
 * Endpoint: Keywords Base de Campaña
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class KeywordsCampanaEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $niche = $this->params['niche'] ?? null;
        $companyDesc = $this->params['company_description'] ?? null;
        
        if (!$niche) {
            Response::error('niche es requerido', 400);
        }
        
        if (!$companyDesc) {
            Response::error('company_description es requerido', 400);
        }
        
        $keywordsSEO = $this->params['keywords_seo'] ?? '';
        
        $promptTemplate = $this->loadPrompt('keywords-campana');
        if (!$promptTemplate) {
            Response::error('Error cargando template', 500);
        }
        
        $seoSection = $keywordsSEO ? "SEO Keywords: {$keywordsSEO}\n" : '';
        
        $prompt = $this->replaceVariables($promptTemplate, [
            'niche' => $niche,
            'company_description' => $companyDesc,
            'keywords_seo_section' => $seoSection
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 350,
            'temperature' => 0.7
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        $this->trackUsage('campaign_image_keywords', $result);
        
        // FORMATO V4: Solo 'keywords'
        Response::success(['keywords' => trim($result['content'])]);
    }
}
