# 🎯 Sistema de Scraping Inteligente

## Descripción

Sistema avanzado de análisis web en 3 capas que usa IA para decidir qué páginas visitar y cómo extraer información relevante sobre empresas.

## Arquitectura

### Capa 1: Extracción HTML Limpia
**Archivo**: `services/HTMLCleaner.php`

- Scraping de homepage
- Extracción de contenido útil (títulos, encabezados, texto principal)
- Detección de links internos
- Limpieza de scripts, estilos, navegación

**Extrae**:
- Meta descripción
- Título de página
- Encabezados H1-H3
- Contenido principal
- Lista de links internos con texto ancla

### Capa 2: Decisión con IA (Nivel 1)
**Archivo**: `services/WebIntelligentScraper.php`

La IA analiza:
- Contenido de homepage
- Lista de URLs disponibles
- Texto ancla de cada link

Y decide:
- Qué 8-10 páginas visitar (nivel 1)
- Por qué son relevantes
- Cuáles evitar (blog, contacto, legal)

**Prompt a IA**:
```
Selecciona las URLs más relevantes para entender servicios/productos.
NO selecciones: blog, noticias, contacto, privacidad, cookies, términos
Prioriza: servicios, soluciones, productos, "qué hacemos", "sobre nosotros"
```

### Capa 3: Scraping Profundo + Nivel 2
**Archivo**: `services/WebIntelligentScraper.php`

**Nivel 1 (8-10 páginas)**:
1. Scrapea páginas seleccionadas por IA
2. Extrae links internos de cada página
3. Limpia el HTML de cada una

**Nivel 2 (hasta 5 páginas adicionales)**:
1. Recopila links de páginas nivel 1
2. Filtra por relevancia (heurísticas)
3. Scrapea las mejores (detalles de servicios, casos, metodología)
4. Evita duplicados

**Total**: 13-15 páginas analizadas (1 homepage + 8-10 nivel 1 + 0-5 nivel 2)
   - Descripción de la empresa (2-3 párrafos)
   - Lista de servicios principales
   - Industria/sector
   - Audiencia objetivo

## Uso

### En el Endpoint
```php
// Automático (recomendado)
POST /generate-meta
{
    "type": "company_description",
    "domain": "example.com"
}

// Forzar método antiguo (solo homepage)
POST /generate-meta
{
    "type": "company_description",
    "domain": "example.com",
    "intelligent_scraper": false
}
```

### Programático
```php
require_once 'services/WebIntelligentScraper.php';

$scraper = new WebIntelligentScraper($openaiService);
$result = $scraper->analyze('example.com');

// Resultado:
[
    'success' => true,
    'description' => 'Agencia de marketing digital...',
    'services' => ['SEO', 'SEM', 'Social Media'],
    'industry' => 'Marketing Digital',
    'target_audience' => 'Empresas B2B',
    'pages_analyzed' => [
        'https://example.com',
        'https://example.com/servicios',
        'https://example.com/soluciones'
    ],
    'tokens_used' => 1250
]
```

## Ventajas vs Método Anterior

| Aspecto | Método Anterior | Scraping Inteligente |
|---------|----------------|----------------------|
| Páginas analizadas | 1 (homepage) | 13-15 (multinivel) |
| Niveles de profundidad | 0 | 2 niveles |
| Calidad info | Limitada | Muy completa |
| Detecta servicios | No | Sí, estructurado |
| Adaptable | No (asume rutas) | Sí (IA + heurísticas) |
| Llamadas IA | 1 | 2 (eficiente) |
| Tokens usados | ~800 | ~2000-2500 |

## Ejemplos Reales

### Ejemplo 1: Agencia Web
```
Domain: miagencia.com

├─ Homepage (nivel 0)
│
├─ IA selecciona nivel 1:
│  ├─ /nuestros-servicios
│  ├─ /casos-exito  
│  ├─ /metodologia
│  ├─ /sobre-nosotros
│  └─ /equipo
│
├─ Nivel 2 (links dentro de servicios):
│  ├─ /servicios/diseno-web
│  ├─ /servicios/seo
│  ├─ /casos-exito/proyecto-ecommerce
│  └─ /metodologia/proceso-trabajo
│
└─ Resultado (13 páginas analizadas):
   "Agencia especializada en diseño web y marketing digital
    con +10 años experiencia. Metodología ágil con enfoque
    en resultados medibles. Servicios: diseño UX/UI, desarrollo
    web responsive, SEO técnico, SEM, social media y branding.
    Casos destacados en e-commerce y empresas B2B."
```

### Ejemplo 2: E-commerce
```
Domain: tiendaonline.com
├─ Homepage: Venta productos
├─ IA selecciona:
│  ├─ /sobre-nosotros
│  ├─ /que-vendemos
│  └─ /envios-devoluciones
└─ Resultado:
   "E-commerce especializado en productos eco-friendly.
    Catálogo: moda sostenible, cosmética natural, hogar.
    Envíos España y Europa."
```

## Fallbacks

Si algo falla, el sistema tiene múltiples fallbacks:

1. **IA no responde** → Usa heurísticas (keywords en URLs)
2. **No hay links** → Analiza solo homepage
3. **Páginas no accesibles** → Usa las que funcionan
4. **DOM corrupto** → Extracción de texto simple

## Configuración

En `WebIntelligentScraper.php`:

```php
private $maxPagesToScrape = 10;   // Páginas nivel 1 (8-10 típico)
private $maxLevel2Pages = 5;       // Páginas nivel 2 (0-5 adicionales)
private $timeout = 10;             // Timeout por request (segundos)
private $enableLevel2 = true;      // Habilitar exploración nivel 2
```

**Estrategia adaptativa:**
- Si nivel 1 tiene muchas páginas relevantes → Nivel 2 encuentra menos
- Si nivel 1 tiene pocas páginas → Nivel 2 compensa explorando más
- Total oscila entre 10-15 páginas según estructura del sitio

## Optimizaciones Futuras

- [ ] Caché de análisis por dominio (24h)
- [ ] Detección de idioma y traducción
- [ ] Análisis de imágenes/logos
- [ ] Scraping de redes sociales
- [ ] Comparación con competidores
- [ ] Score de calidad del análisis

## Notas Técnicas

- **Librería HTML**: DOMDocument (nativa PHP)
- **HTTP**: cURL con timeout y User-Agent
- **Encoding**: UTF-8 con conversión automática
- **Límite tokens**: Contenido truncado a 8KB por página
- **Deduplicación**: URLs normalizadas (sin query/fragment)

## Testing

Para probar manualmente:

```bash
# Crear archivo test.php en raíz:
<?php
require_once 'config.php';
require_once 'services/OpenAIService.php';
require_once 'services/WebIntelligentScraper.php';

$openai = new OpenAIService();
$scraper = new WebIntelligentScraper($openai);

$result = $scraper->analyze('ejemplo.com');
print_r($result);
```
