<?php
/**
 * Endpoint: Keywords de Imágenes Específicas
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class KeywordsImagenesEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $title = $this->params['title'] ?? null;
        $companyDesc = $this->params['company_description'] ?? '';
        $baseImageKeywords = $this->params['keywords_images_base'] ?? '';
        
        if (!$title) {
            Response::error('title es requerido', 400);
        }
        
        // Cargar template
        $template = $this->loadPrompt('keywords-imagenes');
        if (!$template) {
            Response::error('Error cargando template', 500);
        }
        
        // Construir secciones dinámicas
        $titleSection = $title ? "Title: {$title}" : '';
        
        // Sección adapt (si hay keywords base)
        $adaptSection = '';
        if ($baseImageKeywords) {
            $adaptSection = "2. ADAPT BASE KEYWORDS to match this title's visuals (20-30%):\n";
            $adaptSection .= "   - You have base keywords from the campaign\n";
            $adaptSection .= "   - Keep 30-40% that are relevant to THIS specific title\n";
            $adaptSection .= "   - Modify/adapt 20-30% to match THIS title\n";
            $adaptSection .= "   - Add 30-50% NEW keywords unique to THIS title\n";
        }
        
        // Sección maintain (reglas para mantener coherencia)
        $maintainSection = '';
        if ($baseImageKeywords) {
            $maintainSection = "4. MAINTAIN VISUAL CONSISTENCY (20% of keywords):\n";
            $maintainSection .= "   - Select base keywords that work for this specific article\n";
            $maintainSection .= "   - Ensures visual coherence across the campaign\n";
        }
        
        // Reglas mandatorias
        $rulesSection = "✓ Focus on VISUAL CONCEPTS (objects, scenes, actions)\n";
        $rulesSection .= "✓ DO NOT translate title literally\n";
        $rulesSection .= "✓ Think: 'What would I search in a stock photo site?'\n";
        $rulesSection .= "✓ Each article must have UNIQUE keywords\n";
        $rulesSection .= "✓ Generate 8-12 keywords total\n";
        
        // Reemplazar variables en template
        $prompt = $this->replaceVariables($template, [
            'title_section' => $titleSection,
            'company_description' => $companyDesc,
            'keywords_images_base' => $baseImageKeywords,
            'adapt_section' => $adaptSection,
            'maintain_section' => $maintainSection,
            'rules_section' => $rulesSection
        ]);
        
        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 400,
            'temperature' => 0.8
        ]);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        $this->trackUsage('keywords_images', $result);
        
        Response::success(['keywords' => trim($result['content'])]);
    }
}
