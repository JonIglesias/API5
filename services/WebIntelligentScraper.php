<?php
/**
 * WebIntelligentScraper - OPTIMIZADO
 * 
 * Sistema de scraping inteligente con scraping paralelo
 * Tiempo: 2-3 minutos (vs 8-10 min anterior)
 * 
 * Uso:
 * $scraper = new WebIntelligentScraper($openaiService);
 * $result = $scraper->analyze('example.com');
 */

class WebIntelligentScraper {
    
    private $openai;
    private $htmlCleaner;
    private $maxPagesToScrape = 3;        // ULTRA AGRESIVO: solo 3 páginas
    private $maxLevel2Pages = 0;          // DESACTIVAR nivel 2
    private $timeout = 5;                 // 5 segundos timeout
    private $enableLevel2 = false;        // DESACTIVADO
    
    private $totalTokensUsed = 0;
    private $totalPromptTokens = 0;
    private $totalCompletionTokens = 0;
    
    public function __construct($openaiService) {
        $this->openai = $openaiService;
        
        require_once API_BASE_DIR . '/services/HTMLCleaner.php';
        $this->htmlCleaner = new HTMLCleaner();
        
        $this->totalTokensUsed = 0;
        $this->totalPromptTokens = 0;
        $this->totalCompletionTokens = 0;
    }
    
    /**
     * Analiza un dominio completo de forma inteligente
     * 
     * @param string $domain Dominio a analizar (sin http://)
     * @return array ['success' => bool, 'description' => string, 'services' => array, 'pages_analyzed' => array]
     */
    public function analyze($domain) {
        try {
            $this->totalTokensUsed = 0;
            $this->totalPromptTokens = 0;
            $this->totalCompletionTokens = 0;
            
            $domain = $this->normalizeDomain($domain);
            $baseUrl = 'https://' . $domain;
            
            $homepageData = $this->scrapeHomepage($baseUrl);
            if (!$homepageData['success']) {
                return [
                    'success' => false,
                    'error' => 'No se pudo acceder al dominio',
                    'description' => '',
                    'pages_analyzed' => [],
                    'tokens_used' => 0,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0
                ];
            }
            
            $selectedUrls = $this->askAIWhichPagesToVisit(
                $domain,
                $homepageData['clean_content'],
                $homepageData['found_links']
            );
            
            $level1Content = $this->scrapeSelectedPagesParallel($selectedUrls);
            
            $level2Content = [];
            if ($this->enableLevel2 && count($level1Content) > 0) {
                $level2Urls = $this->selectLevel2Urls($level1Content, $baseUrl);
                $level2Content = $this->scrapeSelectedPagesParallel($level2Urls);
            }
            
            $allPagesContent = array_merge($level1Content, $level2Content);
            
            $finalAnalysis = $this->analyzeWithAI(
                $domain,
                $homepageData['clean_content'],
                $allPagesContent
            );
            
            $allUrls = array_merge(
                [$baseUrl],
                array_column($level1Content, 'url'),
                array_column($level2Content, 'url')
            );
            
            return [
                'success' => true,
                'description' => $finalAnalysis['description'],
                'services' => $finalAnalysis['services'],
                'pages_analyzed' => $allUrls,
                'tokens_used' => $this->totalTokensUsed,
                'prompt_tokens' => $this->totalPromptTokens,
                'completion_tokens' => $this->totalCompletionTokens,
                'level1_pages' => count($level1Content),
                'level2_pages' => count($level2Content)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'description' => '',
                'pages_analyzed' => [],
                'tokens_used' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0
            ];
        }
    }
    
    /**
     * CAPA 1: Scraping de la homepage
     */
    private function scrapeHomepage($url) {
        $html = $this->fetchURL($url);
        if (!$html) {
            return ['success' => false];
        }
        
        // Limpiar HTML
        $cleanContent = $this->htmlCleaner->extractContent($html);
        
        // Extraer links internos
        $foundLinks = $this->htmlCleaner->extractInternalLinks($html, $url);
        
        return [
            'success' => true,
            'clean_content' => $cleanContent,
            'found_links' => $foundLinks,
            'raw_html' => $html
        ];
    }
    
    /**
     * CAPA 2: IA decide qué páginas visitar
     */
    private function askAIWhichPagesToVisit($domain, $homepageContent, $foundLinks) {
        $linksToShow = array_slice($foundLinks, 0, 50);
        
        $linksText = '';
        foreach ($linksToShow as $idx => $link) {
            $linksText .= ($idx + 1) . ". " . $link['url'] . " - \"" . substr($link['text'], 0, 50) . "\"\n";
        }
        
        $prompt = <<<PROMPT
Eres un analista web experto. Tu tarea es decidir qué páginas de un sitio web debo visitar para entender completamente a qué se dedica la empresa y qué servicios ofrece.

DOMINIO: {$domain}

CONTENIDO DE LA HOMEPAGE:
{$homepageContent}

LINKS ENCONTRADOS EN LA HOMEPAGE:
{$linksText}

IMPORTANTE:
- Selecciona las 12 URLs más relevantes para entender TODOS los servicios/productos
- Prioriza páginas que parezcan listar múltiples servicios (ej: "servicios", "productos", "modalidades")
- Incluye páginas individuales de servicios específicos si las ves
- NO selecciones páginas de: blog, noticias, contacto, privacidad, cookies, términos
- Prioriza: servicios, soluciones, productos, "qué hacemos", "modalidades", especialidades

Responde SOLO con un JSON en este formato exacto:
{
  "selected_urls": ["URL1", "URL2", "URL3", "URL4", "URL5", "URL6", "URL7", "URL8", "URL9", "URL10", "URL11", "URL12"]
}

NO incluyas markdown, solo el JSON puro.
PROMPT;

        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 400,
            'temperature' => 0.3
        ]);
        
        if ($result['success'] && isset($result['usage'])) {
            $this->totalTokensUsed += $result['usage']['total_tokens'] ?? 0;
            $this->totalPromptTokens += $result['usage']['prompt_tokens'] ?? 0;
            $this->totalCompletionTokens += $result['usage']['completion_tokens'] ?? 0;
        }
        
        if (!$result['success']) {
            return $this->selectUrlsByHeuristics($foundLinks);
        }
        
        $response = $this->parseJSONResponse($result['content']);
        
        if (isset($response['selected_urls']) && is_array($response['selected_urls'])) {
            return array_slice($response['selected_urls'], 0, $this->maxPagesToScrape);
        }
        
        return $this->selectUrlsByHeuristics($foundLinks);
    }
    
    /**
     * CAPA 3: Scraping PARALELO de páginas seleccionadas
     */
    private function scrapeSelectedPagesParallel($urls) {
        if (empty($urls)) {
            return [];
        }
        
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = [];
        
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AutoPostBot/2.0)'
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$url] = $ch;
        }
        
        $running = null;
        $maxWait = 30; // Máximo 30 segundos para todo el batch
        $start = time();
        
        do {
            $status = curl_multi_exec($multiHandle, $running);
            
            // Timeout de seguridad
            if ((time() - $start) > $maxWait) {
                break;
            }
            
            if ($running) {
                curl_multi_select($multiHandle, 0.5);
            }
        } while ($running > 0 && $status == CURLM_OK);
        
        foreach ($curlHandles as $url => $ch) {
            $html = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode === 200 && $html) {
                $cleanContent = $this->htmlCleaner->extractContent($html);
                
                $results[] = [
                    'url' => $url,
                    'content' => $cleanContent,
                    'raw_html' => $html,
                    'level' => 1
                ];
            }
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multiHandle);
        
        return $results;
    }
    
    /**
     * Seleccionar URLs nivel 2 por heurísticas
     */
    private function selectLevel2Urls($level1Pages, $baseUrl) {
        $allLevel2Links = [];
        $seenUrls = [];
        
        foreach ($level1Pages as $page) {
            $html = $page['raw_html'] ?? '';
            if (!$html) continue;
            
            $links = $this->htmlCleaner->extractInternalLinks($html, $page['url']);
            
            foreach ($links as $link) {
                $normalized = $this->normalizeUrlForDedup($link['url']);
                
                if (!isset($seenUrls[$normalized])) {
                    $seenUrls[$normalized] = true;
                    $allLevel2Links[] = $link;
                }
            }
        }
        
        if (empty($allLevel2Links)) {
            return [];
        }
        
        $selected = $this->selectUrlsByHeuristics($allLevel2Links, $this->maxLevel2Pages);
        
        return $selected;
    }
    
    /**
     * SÍNTESIS FINAL: Análisis combinado con IA
     */
    private function analyzeWithAI($domain, $homepageContent, $pagesContent) {
        // CONTEXTO ULTRA REDUCIDO
        $context = "DOMINIO: {$domain}\n\n";
        $context .= "=== HOME ===\n" . substr($homepageContent, 0, 1500) . "\n\n";
        
        // Solo primeras 3 páginas con 1500 chars cada una
        $maxPages = min(3, count($pagesContent));
        for ($i = 0; $i < $maxPages; $i++) {
            $page = $pagesContent[$i];
            $urlPath = parse_url($page['url'], PHP_URL_PATH) ?: '/p' . ($i + 1);
            $context .= "=== {$urlPath} ===\n";
            $context .= substr($page['content'], 0, 1500) . "\n\n";
        }
        
        $prompt = <<<PROMPT
Analiza este sitio web y extrae:

{$context}

Responde SOLO JSON:
{
  "description": "Descripción empresa 2 párrafos",
  "services": [{"name": "Servicio", "url": "/url/"}],
  "industry": "Sector",
  "target_audience": "Audiencia"
}

Mínimo 10 servicios. Solo JSON, sin markdown.
PROMPT;

        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 1500,  // Reducido de 3000
            'temperature' => 0.2
        ]);
        
        if ($result['success'] && isset($result['usage'])) {
            $this->totalTokensUsed += $result['usage']['total_tokens'] ?? 0;
            $this->totalPromptTokens += $result['usage']['prompt_tokens'] ?? 0;
            $this->totalCompletionTokens += $result['usage']['completion_tokens'] ?? 0;
        }
        
        if (!$result['success']) {
            return [
                'description' => "Empresa en el sector de {$domain}",
                'services' => [],
                'industry' => '',
                'target_audience' => ''
            ];
        }
        
        $response = $this->parseJSONResponse($result['content']);
        
        $description = $response['description'] ?? "Información no disponible";
        $services = $response['services'] ?? [];
        
        if (!empty($services)) {
            $description .= "\n\n=== SERVICIOS/PRODUCTOS ===\n";
            foreach ($services as $service) {
                $name = $service['name'] ?? '';
                $url = $service['url'] ?? '';
                
                if ($name) {
                    $description .= "• {$name}";
                    if ($url) $description .= " ({$url})";
                    $description .= "\n";
                }
            }
        }
        
        return [
            'description' => $description,
            'services' => $services,
            'industry' => $response['industry'] ?? '',
            'target_audience' => $response['target_audience'] ?? ''
        ];
    }
    
    /**
     * Fetch URL con timeout y user agent
     */
    private function fetchURL($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200) ? $html : false;
    }
    
    /**
     * Normalizar dominio
     */
    private function normalizeDomain($domain) {
        $domain = preg_replace('#^https?://(www\.)?#i', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }
    
    /**
     * Parsear respuesta JSON de OpenAI (elimina markdown si existe)
     */
    private function parseJSONResponse($content) {
        // Eliminar markdown code blocks si existen
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        $content = trim($content);
        
        $decoded = json_decode($content, true);
        return $decoded ?: [];
    }
    
    /**
     * Normalizar URL para deduplicación (más estricto)
     */
    private function normalizeUrlForDedup($url) {
        $parts = parse_url($url);
        $normalized = ($parts['scheme'] ?? 'https') . '://';
        $normalized .= $parts['host'] ?? '';
        $normalized .= $parts['path'] ?? '/';
        return rtrim(strtolower($normalized), '/');
    }
    
    /**
     * Seleccionar URLs por heurísticas
     */
    private function selectUrlsByHeuristics($foundLinks, $limit = null) {
        if ($limit === null) {
            $limit = $this->maxPagesToScrape;
        }
        
        $keywords = [
            'servicio', 'service', 'producto', 'product', 'solucio', 'solution',
            'oferta', 'offer', 'especialidad', 'specialty', 'qué-hacemos', 'what-we-do',
            'nosotros', 'about', 'quienes-somos', 'metodologia', 'methodology',
            'equipo', 'team', 'caso', 'case', 'proyecto', 'project',
            'industria', 'industry', 'sector', 'tecnologia', 'technology',
            'modalidad', 'modalidades', 'experiencia', 'experiencias',
            'caza', 'hunting', 'monteria', 'rececho', 'batida',
            'ceremonia', 'cata', 'taller', 'curso', 'actividad'
        ];
        
        $scored = [];
        foreach ($foundLinks as $link) {
            $score = 0;
            $urlLower = strtolower($link['url']);
            $textLower = strtolower($link['text']);
            
            foreach ($keywords as $keyword) {
                if (strpos($urlLower, $keyword) !== false) $score += 3;
                if (strpos($textLower, $keyword) !== false) $score += 2;
            }
            
            // Bonus por URLs cortas (suelen ser páginas principales)
            $path = parse_url($link['url'], PHP_URL_PATH);
            if ($path && substr_count($path, '/') <= 3) {
                $score += 1;
            }
            
            if ($score > 0) {
                $scored[] = ['url' => $link['url'], 'score' => $score];
            }
        }
        
        // Ordenar por score
        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Devolver top URLs
        $selected = array_slice($scored, 0, $limit);
        return array_column($selected, 'url');
    }
}
