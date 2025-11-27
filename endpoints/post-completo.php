<?php
/**
 * Endpoint: Post Completo (Título + Contenido)
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class PostCompletoEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $niche = $this->params['niche'] ?? '';
        $companyDesc = $this->params['company_description'] ?? '';
        $keywords = $this->params['keywords_seo'] ?? '';
        $config = $this->params['config'] ?? [];
        
        $promptTemplate = $this->loadPrompt('post-completo');
        if (!$promptTemplate) {
            Response::error('Error cargando template', 500);
        }
        
        // Template espera: {{company_description}}, {{niche_section}}, {{keywords_section}}, {{config_section}}
        
        $nicheSection = $niche ? "Categoría: {$niche}\n" : '';
        $keywordsSection = $keywords ? "Keywords SEO: {$keywords}\n" : '';
        $configSection = !empty($config) ? "Configuración: " . json_encode($config) . "\n" : '';
        
        $prompt = $this->replaceVariables($promptTemplate, [
            'company_description' => $companyDesc,
            'niche_section' => $nicheSection,
            'keywords_section' => $keywordsSection,
            'config_section' => $configSection
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 3000
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        // Extraer título y contenido
        $response = $result['content'];
        $title = '';
        $content = '';
        
        if (preg_match('/TÍTULO:\s*(.+?)\n/i', $response, $matches)) {
            $title = trim($matches[1]);
        }
        
        if (preg_match('/CONTENIDO:\s*(.+)/is', $response, $matches)) {
            $content = trim($matches[1]);
        }
        
        // Limpiar
        $content = preg_replace('/```html\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        
        $this->trackUsage('complete', $result);
        
        Response::success([
            'title' => $title,
            'content' => trim($content),
            'tokens_used' => $result['usage']['total_tokens'] ?? 0
        ]);
    }
}
