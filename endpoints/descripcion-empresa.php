<?php
/**
 * Endpoint: Descripción de Empresa desde Dominio
 * 
 * Usa WebIntelligentScraper para análisis inteligente del dominio
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class DescripcionEmpresaEndpoint extends BaseEndpoint {
    
    public function handle() {
        $this->validateLicense();
        
        $domain = $this->params['domain'] ?? null;
        if (!$domain) {
            Response::error('domain es requerido', 400);
        }
        
        $additionalInfo = $this->params['additional_info'] ?? '';
        
        // USAR SIEMPRE método básico (más rápido y usa prompt .md)
        $result = $this->analyzeWithBasicScraper($domain, $additionalInfo);
        
        if (!$result['success']) {
            Response::error($result['error'], 500);
        }
        
        // FORMATO COMPATIBILIDAD V4: Solo description y content
        Response::success([
            'description' => $result['description'],
            'content' => $result['description']  // Duplicado para compatibilidad
        ]);
    }
    
    /**
     * Método NUEVO: Scraping inteligente con IA
     */
    private function analyzeWithIntelligentScraper($domain, $additionalInfo) {
        require_once API_BASE_DIR . '/services/WebIntelligentScraper.php';
        
        $scraper = new WebIntelligentScraper($this->openai);
        $scraperResult = $scraper->analyze($domain);
        
        if (!$scraperResult['success']) {
            return $scraperResult;
        }
        
        // Si hay info adicional, agregarla al contexto
        if ($additionalInfo) {
            $description = $scraperResult['description'] . "\n\n" 
                         . "INFORMACIÓN ADICIONAL PROPORCIONADA: " . $additionalInfo;
        } else {
            $description = $scraperResult['description'];
        }
        
        // Trackear uso
        $this->trackUsage('company_description', [
            'usage' => [
                'total_tokens' => $scraperResult['tokens_used'],
                'prompt_tokens' => $scraperResult['prompt_tokens'] ?? 0,
                'completion_tokens' => $scraperResult['completion_tokens'] ?? 0
            ]
        ]);
        
        return [
            'success' => true,
            'description' => $description,
            'services' => $scraperResult['services'],
            'pages_analyzed' => $scraperResult['pages_analyzed'],
            'tokens_used' => $scraperResult['tokens_used']
        ];
    }
    
    /**
     * MÉTODO HÍBRIDO OPTIMIZADO (2 llamadas máximo)
     * 
     * PASO 1: Scraping estructurado homepage
     * PASO 2: IA decide si necesita más páginas Y extrae servicios (1 llamada)
     * PASO 3: Scraping condicional (solo si IA lo pide)
     * PASO 4: Descripción (1 llamada)
     */
    private function analyzeWithBasicScraper($domain, $additionalInfo) {
        $domain = preg_replace('#^https?://(www\.)?#i', '', $domain);
        $domain = rtrim($domain, '/');
        $url = 'https://' . $domain;
        
        // === PASO 1: SCRAPING HOMEPAGE ===
        $homepageHtml = $this->fetchHTML($url);
        
        if (!$homepageHtml) {
            return ['success' => false, 'error' => 'No se pudo acceder al sitio'];
        }
        
        require_once API_BASE_DIR . '/services/HTMLCleaner.php';
        $cleaner = new HTMLCleaner();
        
        // Extraer estructura Y contenido
        $structuredMap = $cleaner->extractStructuredMap($homepageHtml, $url);
        $homepageContent = $cleaner->extractContent($homepageHtml);
        
        $mapText = $this->formatStructuredMap($structuredMap);
        
        // === PASO 2: IA EXTRAE SERVICIOS + DECIDE SI NECESITA MÁS PÁGINAS ===
        $promptCombined = <<<PROMPT
Analiza este sitio web y extrae TODOS los servicios/productos visibles.

MAPA ESTRUCTURADO (menús, secciones, listas):
{$mapText}

CONTENIDO:
{$homepageContent}

TAREAS:
1. **Extrae servicios/productos EXPLÍCITOS** que veas ahora
2. **Recomienda máximo 2 URLs** si crees que hay más servicios en otras páginas específicas

REGLAS EXTRACCIÓN:
- Solo servicios que VEAS NOMBRADOS
- NO inventes, NO asumas
- Nombres exactos

FORMATO JSON:
{
  "services": [
    {"name": "Servicio exacto", "url": "/url/"}
  ],
  "needs_more_pages": true/false,
  "recommended_urls": ["/catas/", "/especies/"],
  "confidence": "high/medium/low"
}

Si confidence es "high", needs_more_pages debe ser false.
Solo JSON, sin markdown.
PROMPT;

        $resultCombined = $this->openai->generateContent([
            'prompt' => $promptCombined,
            'max_tokens' => 1200,
            'temperature' => 0,
            'model' => 'gpt-4o'
        ]);
        
        $services = [];
        $needsMore = false;
        $recommendedUrls = [];
        
        if ($resultCombined['success']) {
            $parsed = $this->parseJSON($resultCombined['content']);
            $services = $parsed['services'] ?? [];
            $needsMore = $parsed['needs_more_pages'] ?? false;
            $recommendedUrls = $parsed['recommended_urls'] ?? [];
            $confidence = $parsed['confidence'] ?? 'low';
            
            // Solo scrapear si confidence es low/medium Y recomienda URLs
            if ($confidence !== 'high' && $needsMore && !empty($recommendedUrls)) {
                $needsMore = true;
            } else {
                $needsMore = false;
            }
        }
        
        // === PASO 3: SCRAPING CONDICIONAL ===
        $additionalContent = '';
        $scrapedUrls = [];
        
        if ($needsMore && !empty($recommendedUrls)) {
            // Limitar a 2 URLs
            $recommendedUrls = array_slice($recommendedUrls, 0, 2);
            
            foreach ($recommendedUrls as $relUrl) {
                $fullUrl = rtrim($url, '/') . '/' . ltrim($relUrl, '/');
                $html = $this->fetchHTML($fullUrl);
                
                if ($html) {
                    $content = $cleaner->extractContent($html);
                    $additionalContent .= "\n\n=== {$relUrl} ===\n{$content}";
                    $scrapedUrls[] = $fullUrl;
                }
            }
            
            // Si scrapeamos más páginas, extraer servicios adicionales
            if (!empty($additionalContent)) {
                $promptMoreServices = $this->loadPrompt('extraer-servicios');
                $promptMoreServices = str_replace('{{web_content}}', $additionalContent, $promptMoreServices);
                
                $resultMore = $this->openai->generateContent([
                    'prompt' => $promptMoreServices,
                    'max_tokens' => 1000,
                    'temperature' => 0,
                    'model' => 'gpt-4o'
                ]);
                
                if ($resultMore['success']) {
                    $parsedMore = $this->parseJSON($resultMore['content']);
                    $moreServices = $parsedMore['services'] ?? [];
                    
                    // Deduplicar y agregar
                    foreach ($moreServices as $service) {
                        $isDupe = false;
                        foreach ($services as $existing) {
                            if (strtolower($existing['name']) === strtolower($service['name'])) {
                                $isDupe = true;
                                break;
                            }
                        }
                        if (!$isDupe) {
                            $services[] = $service;
                        }
                    }
                }
            }
        }
        
        // === PASO 4: DESCRIPCIÓN ===
        $fullContent = $homepageContent . $additionalContent;
        
        $promptDescription = $this->loadPrompt('descripcion-empresa');
        if (!$promptDescription) {
            return ['success' => false, 'error' => 'Error cargando prompt'];
        }
        
        $promptDescription = $this->replaceVariables($promptDescription, [
            'web_content_section' => "CONTENIDO:\n{$fullContent}\n\n",
            'additional_info_section' => $additionalInfo ? "INFO ADICIONAL:\n{$additionalInfo}\n\n" : ''
        ]);
        
        $resultDescription = $this->openai->generateContent([
            'prompt' => $promptDescription,
            'max_tokens' => 600,
            'temperature' => 0.2
        ]);
        
        $description = 'Información no disponible';
        $industry = '';
        $targetAudience = '';
        
        if ($resultDescription['success']) {
            $parsed = $this->parseJSON($resultDescription['content']);
            $description = $parsed['description'] ?? $description;
            $industry = $parsed['industry'] ?? '';
            $targetAudience = $parsed['target_audience'] ?? '';
        }
        
        // Agregar servicios
        if (!empty($services)) {
            $description .= "\n\n=== SERVICIOS/PRODUCTOS ===\n";
            foreach ($services as $service) {
                $name = $service['name'] ?? '';
                $sUrl = $service['url'] ?? '';
                if ($name) {
                    $description .= "• {$name}";
                    if ($sUrl) $description .= " ({$sUrl})";
                    $description .= "\n";
                }
            }
        }
        
        // Tracking (2-3 llamadas máximo)
        $totalTokens = 0;
        $promptTokens = 0;
        $completionTokens = 0;
        
        foreach ([$resultCombined, $resultDescription] as $result) {
            if ($result['success'] && isset($result['usage'])) {
                $totalTokens += $result['usage']['total_tokens'] ?? 0;
                $promptTokens += $result['usage']['prompt_tokens'] ?? 0;
                $completionTokens += $result['usage']['completion_tokens'] ?? 0;
            }
        }
        
        if (isset($resultMore) && $resultMore['success'] && isset($resultMore['usage'])) {
            $totalTokens += $resultMore['usage']['total_tokens'] ?? 0;
            $promptTokens += $resultMore['usage']['prompt_tokens'] ?? 0;
            $completionTokens += $resultMore['usage']['completion_tokens'] ?? 0;
        }
        
        $this->trackUsage('company_description', [
            'usage' => [
                'total_tokens' => $totalTokens,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens
            ]
        ]);
        
        $pagesAnalyzed = array_merge([$url], $scrapedUrls);
        
        return [
            'success' => true,
            'description' => trim($description),
            'services' => $services,
            'industry' => $industry,
            'target_audience' => $targetAudience,
            'pages_analyzed' => $pagesAnalyzed,
            'tokens_used' => $totalTokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens
        ];
    }
    
    /**
     * Formatear mapa estructurado para prompt
     */
    private function formatStructuredMap($map) {
        $text = "";
        
        // Menús
        if (!empty($map['navigation_menus'])) {
            $text .= "=== MENÚS DE NAVEGACIÓN ===\n";
            foreach ($map['navigation_menus'] as $menu) {
                $text .= "{$menu['name']}:\n";
                foreach ($menu['items'] as $item) {
                    $text .= "  - \"{$item['text']}\" → {$item['url']}\n";
                }
                $text .= "\n";
            }
        }
        
        // Secciones
        if (!empty($map['sections'])) {
            $text .= "=== SECCIONES CON HEADERS ===\n";
            foreach ($map['sections'] as $section) {
                $text .= "Título: \"{$section['title']}\"\n";
                $text .= "Contenido: " . substr($section['content'], 0, 200) . "...\n\n";
            }
        }
        
        // Listas
        if (!empty($map['lists'])) {
            $text .= "=== LISTAS ESTRUCTURADAS ===\n";
            foreach ($map['lists'] as $list) {
                $text .= "{$list['type']}:\n";
                foreach ($list['items'] as $item) {
                    $text .= "  • {$item}\n";
                }
                $text .= "\n";
            }
        }
        
        return $text;
    }
    
    /**
     * Scraping múltiple paralelo
     */
    private function scrapeMultipleUrls($urls) {
        $results = [];
        
        foreach ($urls as $url) {
            $html = $this->fetchHTML($url);
            if ($html) {
                $results[] = [
                    'url' => $url,
                    'html' => $html
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Fetch HTML simple
     */
    private function fetchHTML($url) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AutoPostBot/1.0)'
            ]);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return ($httpCode === 200) ? $html : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Parsear JSON de respuesta IA
     */
    private function parseJSON($content) {
        $content = trim($content);
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        $content = trim($content);
        
        $decoded = json_decode($content, true);
        return $decoded ?: [];
    }
    
    /**
     * Obtener contenido web extendido
     */
    private function fetchWebContentExtended($url) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AutoPostsBot/1.0)'
            ]);
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$content) {
                return null;
            }
            
            $content = strip_tags($content);
            $content = preg_replace('/\s+/', ' ', $content);
            
            return substr(trim($content), 0, 8000);
            
        } catch (Exception $e) {
            return null;
        }
    }
}
