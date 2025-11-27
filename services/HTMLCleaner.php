<?php
/**
 * HTMLCleaner
 * 
 * Limpia HTML extrayendo solo contenido útil:
 * - Textos principales
 * - Títulos y encabezados
 * - Meta descripciones
 * - Links internos relevantes
 * 
 * Descarta:
 * - Scripts, estilos, iframes
 * - Navegación, footers, sidebars
 * - Formularios, comentarios
 */

class HTMLCleaner {
    
    /**
     * NUEVO: Extrae mapa estructurado (menús, secciones, listas)
     * Para que la IA vea estructura, no texto plano
     * 
     * @param string $html HTML completo
     * @param string $baseUrl URL base
     * @return array Mapa estructurado
     */
    public function extractStructuredMap($html, $baseUrl) {
        $dom = $this->createDOM($html);
        if (!$dom) {
            return [
                'navigation_menus' => [],
                'sections' => [],
                'lists' => []
            ];
        }
        
        $xpath = new DOMXPath($dom);
        
        return [
            'navigation_menus' => $this->extractNavigationMenus($xpath, $baseUrl),
            'sections' => $this->extractSections($xpath),
            'lists' => $this->extractStructuredLists($xpath)
        ];
    }
    
    /**
     * Extraer menús de navegación con sus links
     */
    private function extractNavigationMenus($xpath, $baseUrl) {
        $menus = [];
        
        // Buscar <nav> elements
        $navs = $xpath->query('//nav');
        
        foreach ($navs as $idx => $nav) {
            $menuItems = [];
            $links = $xpath->query('.//a[@href]', $nav);
            
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                
                if (strlen($text) > 2 && strlen($text) < 100) {
                    $fullUrl = $this->resolveUrl($href, $baseUrl);
                    
                    if ($this->isInternalUrl($fullUrl, $baseUrl)) {
                        $menuItems[] = [
                            'text' => $text,
                            'url' => $fullUrl
                        ];
                    }
                }
            }
            
            if (!empty($menuItems)) {
                $menus[] = [
                    'name' => 'Menu ' . ($idx + 1),
                    'items' => $menuItems
                ];
            }
        }
        
        return $menus;
    }
    
    /**
     * Extraer secciones con headers (h2, h3) y su contenido
     */
    private function extractSections($xpath) {
        $sections = [];
        
        // Buscar H2 y H3
        $headers = $xpath->query('//h2 | //h3');
        
        foreach ($headers as $header) {
            $title = trim($header->textContent);
            
            if (strlen($title) < 3 || strlen($title) > 150) {
                continue;
            }
            
            // Obtener siguiente hermano (contenido de la sección)
            $content = '';
            $nextSibling = $header->nextSibling;
            $gathered = 0;
            
            while ($nextSibling && $gathered < 500) {
                if ($nextSibling->nodeType === XML_ELEMENT_NODE) {
                    $tagName = strtolower($nextSibling->nodeName);
                    
                    // Parar si encuentra otro header
                    if (in_array($tagName, ['h1', 'h2', 'h3', 'h4'])) {
                        break;
                    }
                    
                    $text = trim($nextSibling->textContent);
                    if (strlen($text) > 10) {
                        $content .= $text . ' ';
                        $gathered += strlen($text);
                    }
                }
                
                $nextSibling = $nextSibling->nextSibling;
            }
            
            if (!empty($content)) {
                $sections[] = [
                    'title' => $title,
                    'content' => substr(trim($content), 0, 500)
                ];
            }
        }
        
        return $sections;
    }
    
    /**
     * Extraer listas estructuradas (ul, ol con >3 items)
     */
    private function extractStructuredLists($xpath) {
        $lists = [];
        
        $uls = $xpath->query('//ul | //ol');
        
        foreach ($uls as $idx => $list) {
            $items = $xpath->query('.//li', $list);
            
            if ($items->length < 3) {
                continue; // Ignorar listas muy cortas
            }
            
            $listItems = [];
            foreach ($items as $item) {
                $text = trim($item->textContent);
                
                if (strlen($text) > 3 && strlen($text) < 200) {
                    $listItems[] = $text;
                }
                
                if (count($listItems) >= 15) {
                    break; // Max 15 items por lista
                }
            }
            
            if (count($listItems) >= 3) {
                $lists[] = [
                    'type' => 'list_' . ($idx + 1),
                    'items' => $listItems
                ];
            }
        }
        
        return $lists;
    }
    
    /**
     * Resolver URL relativa a absoluta
     */
    private function resolveUrl($href, $baseUrl) {
        if (preg_match('#^https?://#', $href)) {
            return $href;
        }
        
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        
        if (strpos($href, '/') === 0) {
            return "{$scheme}://{$host}{$href}";
        }
        
        $path = dirname($base['path'] ?? '/');
        return "{$scheme}://{$host}{$path}/{$href}";
    }
    
    /**
     * Verificar si URL es interna
     */
    private function isInternalUrl($url, $baseUrl) {
        $urlHost = parse_url($url, PHP_URL_HOST);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        
        return $urlHost === $baseHost;
    }
    
    /**
     * Extrae contenido limpio de HTML
     * 
     * @param string $html HTML completo
     * @return string Contenido limpio y legible
     */
    public function extractContent($html) {
        if (empty($html)) {
            return '';
        }
        
        // Crear DOMDocument
        $dom = $this->createDOM($html);
        if (!$dom) {
            return $this->fallbackTextExtraction($html);
        }
        
        // Extraer componentes
        $content = [];
        
        // 1. Meta descripción
        $metaDesc = $this->extractMetaDescription($dom);
        if ($metaDesc) {
            $content[] = "META DESCRIPCIÓN: {$metaDesc}";
        }
        
        // 2. Título de la página
        $title = $this->extractTitle($dom);
        if ($title) {
            $content[] = "TÍTULO: {$title}";
        }
        
        // 3. Encabezados H1-H3
        $headings = $this->extractHeadings($dom);
        if (!empty($headings)) {
            $content[] = "ENCABEZADOS:\n" . implode("\n", $headings);
        }
        
        // 4. Contenido principal
        $mainContent = $this->extractMainContent($dom);
        if ($mainContent) {
            $content[] = "CONTENIDO:\n{$mainContent}";
        }
        
        return implode("\n\n", $content);
    }
    
    /**
     * Extrae links internos con su texto ancla
     * 
     * @param string $html HTML completo
     * @param string $baseUrl URL base para resolver links relativos
     * @return array [['url' => string, 'text' => string], ...]
     */
    public function extractInternalLinks($html, $baseUrl) {
        $dom = $this->createDOM($html);
        if (!$dom) {
            return [];
        }
        
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[@href]');
        
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
        $internalLinks = [];
        $seen = [];
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            
            // Resolver URL relativa
            $absoluteUrl = $this->resolveUrl($href, $baseUrl);
            
            // Filtrar solo links internos
            if ($this->isInternalLink($absoluteUrl, $baseDomain)) {
                // Normalizar (sin fragmentos ni query params para deduplicar)
                $normalizedUrl = $this->normalizeUrl($absoluteUrl);
                
                // Evitar duplicados
                if (!isset($seen[$normalizedUrl]) && !$this->isIgnorableLink($normalizedUrl)) {
                    $seen[$normalizedUrl] = true;
                    $internalLinks[] = [
                        'url' => $absoluteUrl,
                        'text' => $text ?: '(sin texto)'
                    ];
                }
            }
        }
        
        return $internalLinks;
    }
    
    /**
     * Crear DOMDocument desde HTML
     */
    private function createDOM($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        // Silenciar warnings deprecation
        @$dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $dom;
        
        libxml_clear_errors();
        return null;
    }
    
    /**
     * Extraer meta descripción
     */
    private function extractMetaDescription($dom) {
        $xpath = new DOMXPath($dom);
        $metas = $xpath->query('//meta[@name="description"]/@content');
        
        if ($metas->length > 0) {
            return trim($metas->item(0)->nodeValue);
        }
        
        // Intentar og:description
        $ogMetas = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogMetas->length > 0) {
            return trim($ogMetas->item(0)->nodeValue);
        }
        
        return '';
    }
    
    /**
     * Extraer título
     */
    private function extractTitle($dom) {
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            return trim($titles->item(0)->textContent);
        }
        return '';
    }
    
    /**
     * Extraer encabezados H1-H3
     */
    private function extractHeadings($dom) {
        $headings = [];
        
        foreach (['h1', 'h2', 'h3'] as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $text = trim($element->textContent);
                if ($text && strlen($text) > 3) {
                    $headings[] = strtoupper($tag) . ": {$text}";
                }
            }
        }
        
        return $headings;
    }
    
    /**
     * Extraer contenido principal (heurístico)
     */
    private function extractMainContent($dom) {
        $xpath = new DOMXPath($dom);
        
        // Remover elementos inútiles
        $this->removeUnwantedElements($dom);
        
        // Buscar contenedor principal por selectores comunes
        $mainSelectors = [
            '//main',
            '//article',
            '//*[@id="main"]',
            '//*[@id="content"]',
            '//*[@class="content"]',
            '//body'
        ];
        
        foreach ($mainSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = $this->extractTextFromNode($nodes->item(0));
                if (strlen($text) > 100) {
                    return $this->cleanText($text);
                }
            }
        }
        
        // Fallback: todo el body
        $body = $dom->getElementsByTagName('body');
        if ($body->length > 0) {
            $text = $this->extractTextFromNode($body->item(0));
            return $this->cleanText($text);
        }
        
        return '';
    }
    
    /**
     * Remover elementos no deseados
     */
    private function removeUnwantedElements($dom) {
        $xpath = new DOMXPath($dom);
        
        $unwantedSelectors = [
            '//script',
            '//style',
            '//iframe',
            '//nav',
            '//header',
            '//footer',
            '//aside',
            '//*[contains(@class, "cookie")]',
            '//*[contains(@class, "popup")]',
            '//*[contains(@class, "modal")]',
            '//*[contains(@id, "cookie")]',
            '//form',
            '//*[@role="navigation"]'
        ];
        
        foreach ($unwantedSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }
    
    /**
     * Extraer texto de un nodo DOM
     */
    private function extractTextFromNode($node) {
        if (!$node) {
            return '';
        }
        
        $text = $node->textContent;
        return $text;
    }
    
    /**
     * Limpiar texto (espacios, saltos de línea excesivos)
     */
    private function cleanText($text) {
        // Normalizar espacios
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Eliminar espacios al inicio/fin
        $text = trim($text);
        
        // Limitar longitud para no exceder tokens
        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000) . '...';
        }
        
        return $text;
    }
    
    /**
     * Fallback: extracción simple sin DOM
     */
    private function fallbackTextExtraction($html) {
        // Quitar scripts y styles
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        
        // Quitar tags HTML
        $text = strip_tags($html);
        
        // Limpiar
        return $this->cleanText($text);
    }
}
