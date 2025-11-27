<?php
/**
 * ServiceExtractor
 * 
 * Extrae servicios/productos de páginas web con sus URLs
 * Usa tanto análisis de HTML estructurado como IA para mayor precisión
 */

defined('API_ACCESS') or die('Direct access not permitted');

class ServiceExtractor {
    
    private $openai;
    
    public function __construct($openaiService) {
        $this->openai = $openaiService;
    }
    
    /**
     * Extrae servicios de múltiples páginas
     * 
     * @param string $domain Dominio base
     * @param array $pagesContent Array de páginas con ['url', 'content', 'raw_html']
     * @return array ['services' => [...], 'tokens_used' => int]
     */
    public function extractServices($domain, $pagesContent) {
        // 1. Extracción estructural (sin IA) - como referencia
        $structuralServices = $this->extractStructuralServices($pagesContent);
        
        // 2. Análisis con IA (PRIORIDAD) - más exhaustivo
        $aiServices = $this->extractWithAI($domain, $pagesContent, $structuralServices);
        
        // 3. Combinar dando prioridad a IA (más confiable)
        $finalServices = $this->mergeAndDeduplicate($aiServices['services'], $structuralServices);
        
        return [
            'services' => $finalServices,
            'tokens_used' => $aiServices['tokens_used']
        ];
    }
    
    /**
     * Extracción estructural (basada en patrones HTML)
     * Busca menús, listas, secciones de servicios
     */
    private function extractStructuralServices($pagesContent) {
        $services = [];
        
        foreach ($pagesContent as $page) {
            if (!isset($page['raw_html']) || empty($page['raw_html'])) continue;
            
            $html = $page['raw_html'];
            $baseUrl = $page['url'];
            
            // Buscar en menús de navegación
            $navServices = $this->extractFromNavigation($html, $baseUrl);
            $services = array_merge($services, $navServices);
            
            // Buscar en secciones específicas (servicios, productos)
            $sectionServices = $this->extractFromServicesSections($html, $baseUrl);
            $services = array_merge($services, $sectionServices);
            
            // Buscar en listas específicas
            $listServices = $this->extractFromLists($html, $baseUrl);
            $services = array_merge($services, $listServices);
        }
        
        return $services;
    }
    
    /**
     * Extrae servicios de menús de navegación
     */
    private function extractFromNavigation($html, $baseUrl) {
        $services = [];
        $doc = $this->loadHTML($html);
        if (!$doc) return $services;
        
        $xpath = new DOMXPath($doc);
        
        // Buscar en nav que contenga "servicios" o "productos"
        $navs = $xpath->query("//nav[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'servic') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'product')]");
        
        foreach ($navs as $nav) {
            $links = $xpath->query('.//a', $nav);
            foreach ($links as $link) {
                $service = $this->extractServiceFromLink($link, $baseUrl);
                if ($service) $services[] = $service;
            }
        }
        
        return $services;
    }
    
    /**
     * Extrae servicios de secciones específicas del HTML
     */
    private function extractFromServicesSections($html, $baseUrl) {
        $services = [];
        $doc = $this->loadHTML($html);
        if (!$doc) return $services;
        
        $xpath = new DOMXPath($doc);
        
        // Patrones de secciones de servicios/productos
        $patterns = [
            "//section[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'servic')]",
            "//section[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'product')]",
            "//div[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'servic')]",
            "//div[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'product')]",
            "//div[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'servic')]",
            "//div[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'product')]"
        ];
        
        foreach ($patterns as $pattern) {
            $sections = $xpath->query($pattern);
            
            foreach ($sections as $section) {
                // Buscar links dentro de la sección
                $links = $xpath->query('.//a[@href]', $section);
                
                foreach ($links as $link) {
                    $service = $this->extractServiceFromLink($link, $baseUrl);
                    if ($service) $services[] = $service;
                }
                
                // Si no hay links, buscar h2/h3 con texto
                if ($links->length === 0) {
                    $headings = $xpath->query('.//h2 | .//h3 | .//h4', $section);
                    foreach ($headings as $heading) {
                        $text = trim($heading->textContent);
                        if (strlen($text) > 3 && strlen($text) < 100) {
                            $services[] = [
                                'name' => $text,
                                'url' => '',
                                'source' => 'heading'
                            ];
                        }
                    }
                }
            }
        }
        
        return $services;
    }
    
    /**
     * Extrae servicios de listas ul/ol
     */
    private function extractFromLists($html, $baseUrl) {
        $services = [];
        $doc = $this->loadHTML($html);
        if (!$doc) return $services;
        
        $xpath = new DOMXPath($doc);
        
        // Buscar listas que tengan clase o estén cerca de headings con "servicios"
        $lists = $xpath->query("//ul[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'servic')] | //ol[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'servic')]");
        
        foreach ($lists as $list) {
            $items = $xpath->query('.//li', $list);
            
            foreach ($items as $item) {
                // Buscar link dentro del item
                $link = $xpath->query('.//a[@href]', $item)->item(0);
                
                if ($link) {
                    $service = $this->extractServiceFromLink($link, $baseUrl);
                    if ($service) $services[] = $service;
                } else {
                    // Si no hay link, usar el texto del item
                    $text = trim($item->textContent);
                    if (strlen($text) > 3 && strlen($text) < 100) {
                        $services[] = [
                            'name' => $text,
                            'url' => '',
                            'source' => 'list_item'
                        ];
                    }
                }
            }
        }
        
        return $services;
    }
    
    /**
     * Extrae información de un link
     */
    private function extractServiceFromLink($link, $baseUrl) {
        $href = $link->getAttribute('href');
        $text = trim($link->textContent);
        
        // Filtrar links no válidos
        if (empty($text) || strlen($text) > 100 || strlen($text) < 3) {
            return null;
        }
        
        // Filtrar URLs no deseadas
        $lowerHref = strtolower($href);
        $blacklist = ['javascript:', 'mailto:', 'tel:', '#', 'blog', 'contacto', 'contact', 'privacy', 'terms', 'cookie'];
        foreach ($blacklist as $bad) {
            if (strpos($lowerHref, $bad) !== false) {
                return null;
            }
        }
        
        // Normalizar URL
        $fullUrl = $this->normalizeUrl($href, $baseUrl);
        
        return [
            'name' => $text,
            'url' => $fullUrl,
            'source' => 'link'
        ];
    }
    
    /**
     * Análisis con IA para refinamiento
     */
    private function extractWithAI($domain, $pagesContent, $structuralServices) {
        // Construir contexto de TODAS las páginas con MÁS contenido
        $context = "DOMINIO: {$domain}\n\n";
        
        foreach ($pagesContent as $page) {
            $urlPath = parse_url($page['url'], PHP_URL_PATH) ?: '/';
            $context .= "=== PÁGINA: {$urlPath} ===\n";
            $context .= substr($page['content'], 0, 5000) . "\n\n"; // Aumentado de 4000 a 5000
        }
        
        // Si hay servicios estructurales, mencionarlos pero pedir MÁS
        $structuralHint = '';
        if (!empty($structuralServices)) {
            $structuralHint = "\n=== SERVICIOS DETECTADOS AUTOMÁTICAMENTE (INCOMPLETO - busca más) ===\n";
            foreach ($structuralServices as $s) { // Todos los detectados
                $structuralHint .= "- {$s['name']}\n";
            }
            $structuralHint .= "\nCRÍTICO: Esta lista está INCOMPLETA. Debes encontrar MÁS servicios en el contenido.\n";
        }
        
        $prompt = <<<PROMPT
Analiza exhaustivamente el siguiente sitio web y extrae TODOS los servicios, productos, modalidades, especialidades o experiencias que ofrece la empresa.

{$context}
{$structuralHint}

INSTRUCCIONES CRÍTICAS:
1. Lee TODO el contenido cuidadosamente
2. Identifica CADA servicio/producto mencionado - MÍNIMO 25, máximo 50
3. Incluye TODAS las variantes, modalidades, especialidades (ej: "Macho Montés de Gredos", "Macho Montés de Ronda")
4. Si ves listas de servicios, inclúyelos TODOS sin excepción
5. Si detectaste servicios automáticamente, valídalos y AÑADE TODOS los que falten
6. Para cada servicio, busca exhaustivamente su URL en el contenido
7. Usa nombres EXACTOS como aparecen en el sitio
8. NO inventes servicios que no veas
9. Prioriza servicios principales pero incluye también variantes y especializaciones

EJEMPLOS de lo que debes detectar:
- Servicios principales: "Montería", "Rececho", "Batida"
- Especies específicas: "Ciervo", "Jabalí", "Corzo", "Gamo", "Muflón", "Rebeco"
- Variantes geográficas: "Macho Montés de Gredos", "Macho Montés de Ronda"
- Modalidades: "Caza tradicional", "Caza y turismo", "Caza y familia"
- Experiencias: "Cata con Maridaje", "Ceremonia del té"
- Cada producto/experiencia individual

EXCLUYE SOLO:
- Blog, noticias, contacto, sobre nosotros, política de privacidad
- Enlaces externos no relacionados

CRÍTICO: Debes encontrar un MÍNIMO de 25 servicios. Si encuentras menos de 25, sigue buscando en el contenido.

Responde SOLO con un JSON en este formato:
{
  "services": [
    {"name": "Nombre exacto del servicio 1", "url": "/ruta-servicio-1/"},
    {"name": "Nombre exacto del servicio 2", "url": "/ruta-servicio-2/"},
    {"name": "Nombre exacto del servicio 3", "url": ""}
  ]
}

Si no encuentras la URL, usa cadena vacía "".
OBJETIVO: Mínimo 25 servicios, máximo 50.
NO incluyas markdown, solo JSON puro.
PROMPT;

        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 4000, // Aumentado de 3000 a 4000 para hasta ~60 servicios
            'temperature' => 0.05  // Bajado de 0.1 a 0.05 para máxima precisión
        ]);
        
        if (!$result['success']) {
            return [
                'services' => [],
                'tokens_used' => 0
            ];
        }
        
        $response = $this->parseJSONResponse($result['content']);
        
        return [
            'services' => $response['services'] ?? [],
            'tokens_used' => $result['usage']['total_tokens'] ?? 0
        ];
    }
    
    /**
     * Combinar y deduplicar servicios
     * Prioriza servicios con URL sobre los que no tienen
     */
    private function mergeAndDeduplicate($aiServices, $structuralServices) {
        $all = array_merge($aiServices, $structuralServices);
        $deduplicated = [];
        $seen = [];
        
        foreach ($all as $service) {
            // Normalizar nombre para comparación
            $normalized = strtolower(trim($service['name']));
            $normalized = preg_replace('/\s+/', ' ', $normalized);
            
            if (!isset($seen[$normalized])) {
                $seen[$normalized] = true;
                
                $url = !empty($service['url']) ? $service['url'] : '';
                
                $deduplicated[] = [
                    'name' => trim($service['name']),
                    'url' => $url
                ];
            } else {
                // Si ya existe pero este tiene URL y el anterior no, actualizar
                foreach ($deduplicated as &$existing) {
                    $existingNormalized = strtolower(trim($existing['name']));
                    $existingNormalized = preg_replace('/\s+/', ' ', $existingNormalized);
                    
                    if ($existingNormalized === $normalized) {
                        // Si el nuevo tiene URL y el existente no, actualizar
                        if (empty($existing['url']) && !empty($service['url'])) {
                            $existing['url'] = $service['url'];
                        }
                        break;
                    }
                }
            }
        }
        
        return $deduplicated;
    }
    
    /**
     * Formatear servicios para texto legible (para company_description)
     */
    public static function formatServicesForDescription($services, $domain = '') {
        if (empty($services)) {
            return '';
        }
        
        $text = "\n\n=== SERVICIOS/PRODUCTOS ===\n";
        
        foreach ($services as $service) {
            $name = $service['name'];
            $url = $service['url'] ?? '';
            
            if (!empty($url)) {
                $text .= "• {$name} ({$url})\n";
            } else {
                $text .= "• {$name}\n";
            }
        }
        
        return $text;
    }
    
    // === UTILIDADES ===
    
    private function loadHTML($html) {
        if (empty($html)) {
            return null;
        }
        
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        return $doc;
    }
    
    private function normalizeUrl($href, $baseUrl) {
        // Si ya es absoluta
        if (preg_match('#^https?://#', $href)) {
            return $href;
        }
        
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        
        // Relativa al dominio
        if (strpos($href, '/') === 0) {
            return "{$scheme}://{$host}{$href}";
        }
        
        // Relativa a la página actual
        $path = dirname($base['path'] ?? '/');
        return "{$scheme}://{$host}{$path}/{$href}";
    }
    
    private function parseJSONResponse($content) {
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        $content = trim($content);
        
        $decoded = json_decode($content, true);
        return $decoded ?: [];
    }
}
