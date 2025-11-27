<?php
/**
 * Script de inicialización de prompts
 * Migra los prompts actuales del código a la BD
 * 
 * Ejecutar: php migrations/seed_prompts.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/PromptManager.php';

define('API_ACCESS', true);

echo "=== INICIALIZANDO PROMPTS V4.2 ===\n\n";

$manager = new PromptManager();

// ========================================================================
// 1. COMPANY_DESCRIPTION - Prompt simple (migración fácil)
// ========================================================================

echo "1. Creando prompt: company_description...\n";

$manager->savePrompt([
    'slug' => 'company_description',
    'name' => 'Descripción de Empresa',
    'description' => 'Genera una descripción completa de una empresa basándose en su dominio web',
    'category' => 'meta',
    'template' => 'Eres un analista de contenido web experto. Tu tarea es analizar un sitio web completo y crear una descripción detallada que será usada por otra IA para generar títulos de blog relevantes.

CONTENIDO EXTRAÍDO DEL SITIO {{domain}}:
```
{{web_content}}
```

Basándote en el contenido REAL de las páginas del sitio web anteriores, genera una descripción completa y detallada (200-300 palabras) que incluya:

1. TEMÁTICA PRINCIPAL: ¿De qué trata el sitio? ¿Cuál es su enfoque y especialización?
2. PRODUCTOS/SERVICIOS ESPECÍFICOS: Lista los productos/servicios concretos que ofrece (no genéricos)
3. AUDIENCIA OBJETIVO: ¿A quién se dirige? ¿Perfil de clientes ideales?
4. PROPUESTA DE VALOR ÚNICA: ¿Qué hace diferente a este sitio/empresa? ¿Ventajas competitivas?
5. TONO Y ESTILO: ¿Profesional, casual, técnico, creativo, educativo?
6. PALABRAS CLAVE DEL SECTOR: Identifica términos importantes del nicho

{{#if additional_info}}
INFORMACIÓN ADICIONAL DEL USUARIO:
{{additional_info}}
{{/if}}

IMPORTANTE:
- Esta descripción será usada por una IA para generar títulos de artículos de blog coherentes
- Debe ser MUY específica: incluye nombres de servicios/productos reales que has visto
- Incluye el contexto del sector/industria en el que opera
- Menciona características distintivas que has encontrado en el contenido
- NO inventes información: si no has visto algo en el contenido, no lo menciones
- Si el contenido es escaso, indica que la información es limitada

Genera SOLO la descripción en español, en un solo párrafo cohesivo, sin títulos ni secciones.',
    'plugin_context' => null,
    'variables' => [
        [
            'key' => 'domain',
            'type' => 'string',
            'description' => 'Dominio del sitio web',
            'required' => true,
            'example' => 'example.com'
        ],
        [
            'key' => 'web_content',
            'type' => 'text',
            'description' => 'Contenido extraído del sitio web',
            'required' => true
        ],
        [
            'key' => 'additional_info',
            'type' => 'text',
            'description' => 'Información adicional proporcionada por el usuario',
            'required' => false
        ]
    ],
    'estimated_tokens_input' => 600,
    'estimated_tokens_output' => 400,
    'updated_by' => 'system'
]);

echo "   ✓ company_description creado\n\n";

// ========================================================================
// 2. TITLE_GENERATE - Generación de títulos (SIN meta-prompt)
// ========================================================================

echo "2. Creando prompt: title_generate...\n";

$manager->savePrompt([
    'slug' => 'title_generate',
    'name' => 'Generación de Títulos',
    'description' => 'Genera títulos únicos para artículos de blog',
    'category' => 'generation',
    'template' => 'Eres un experto en SEO y copywriting. Genera títulos atractivos y optimizados para blog.

CONTEXTO DEL NEGOCIO:
{{company_desc}}

{{#if domain}}
Dominio web: {{domain}}
{{/if}}

{{#if keywords_seo}}
KEYWORDS SEO (úsalas como GUÍA, no forzarlas):
{{keywords_seo}}
{{/if}}

{{#if user_prompt}}
INSTRUCCIONES ESPECÍFICAS DEL USUARIO:
{{user_prompt}}
{{/if}}

OBJETIVO:
Crea títulos que:
✓ Reflejen el negocio y su propuesta de valor
✓ Incorporen keywords SEO de forma NATURAL (si encajan)
✓ Sean comerciales y generen clics
✓ Sean específicos y claros sobre el beneficio
✓ Usen números o palabras de poder cuando sea apropiado
✓ Tengan entre 50-70 caracteres

VARIEDAD REQUERIDA:
Alterna entre estos 5 tipos de títulos:
- Guías prácticas: "Cómo...", "Guía de...", "X pasos para..."
- Listas: "X mejores...", "X formas de..."
- Comparativas: "X vs Y", "Diferencias entre..."
- Preguntas: "¿Por qué...?", "¿Cuándo...?"
- Educativos: "Todo sobre...", "Descubre..."

IMPORTANTE:
- NO te limites solo a las keywords - sé inteligente
- Prioriza: Relevancia > SEO > Clicks
- El título debe tener sentido para el negocio descrito
- Si una keyword no encaja naturalmente, NO la fuerces
- Cada título debe ser DIFERENTE en estructura

Genera el título final, sin comillas ni explicaciones:',
    'plugin_context' => 'El plugin añade ANTES de este prompt:

"Genera {{num_titles}} títulos únicos para blog, numerados."

[Y puede añadir dinámicamente]:
"⚠️ IMPORTANTE: Ya generé estos títulos, NO los repitas ni crees similares:
- {{existing_titles}}"

"Ya generé en esta sesión:
- {{session_titles}}"',
    'variables' => [
        [
            'key' => 'company_desc',
            'type' => 'text',
            'description' => 'Descripción completa de la empresa',
            'required' => true,
            'source' => 'campaign.company_desc'
        ],
        [
            'key' => 'domain',
            'type' => 'string',
            'description' => 'Dominio del sitio web',
            'required' => false,
            'source' => 'campaign.domain'
        ],
        [
            'key' => 'keywords_seo',
            'type' => 'array',
            'description' => 'Keywords SEO de la campaña',
            'required' => false,
            'source' => 'campaign.keywords_seo'
        ],
        [
            'key' => 'user_prompt',
            'type' => 'text',
            'description' => 'Prompt personalizado del usuario desde campaign.prompt_titles',
            'required' => false,
            'source' => 'campaign.prompt_titles'
        ],
        [
            'key' => 'num_titles',
            'type' => 'integer',
            'description' => '[PLUGIN] Número de títulos a generar',
            'required' => false,
            'source' => 'plugin'
        ],
        [
            'key' => 'existing_titles',
            'type' => 'array',
            'description' => '[PLUGIN] Títulos ya generados para evitar duplicados',
            'required' => false,
            'source' => 'plugin'
        ],
        [
            'key' => 'session_titles',
            'type' => 'array',
            'description' => '[PLUGIN] Títulos generados en esta sesión',
            'required' => false,
            'source' => 'plugin'
        ]
    ],
    'estimated_tokens_input' => 400,
    'estimated_tokens_output' => 150,
    'updated_by' => 'system'
]);

echo "   ✓ title_generate creado\n\n";

// ========================================================================
// 3. KEYWORDS_SEO - Keywords para SEO
// ========================================================================

echo "3. Creando prompt: keywords_seo...\n";

$manager->savePrompt([
    'slug' => 'keywords_seo',
    'name' => 'Keywords SEO',
    'description' => 'Genera keywords estratégicas para posicionamiento orgánico',
    'category' => 'keywords',
    'template' => 'Eres un experto en SEO y keyword research. Tu tarea es generar una lista estratégica de keywords para posicionamiento orgánico.

CONTEXTO DEL NEGOCIO:
{{company_desc}}

{{#if niche}}
Categoría general: {{niche}} (solo orientación, céntrate en la descripción)
{{/if}}

ESTRATEGIA DE KEYWORDS:
Genera 15-20 keywords en español siguiendo esta distribución estratégica:

1. LONG-TAIL (50% de las keywords) - Baja competencia, alta intención:
   - 3-4 palabras MÁXIMO por keyword
   - Específicas pero no frases completas
   - Incluir modificadores clave: mejor, barato, online, cerca de mí, para principiantes
   - Estructura CORRECTA: [servicio] [modificador] [ubicación/cualidad]
   - Estructura INCORRECTA: preguntas completas con verbos (más de 5 palabras)

2. MEDIUM-TAIL (35% de las keywords) - Competencia media, buen tráfico:
   - 2-3 palabras
   - Términos con modificador
   - Estructura: [servicio principal] [modificador/ubicación]

3. SHORT-TAIL (15% de las keywords) - Mayor volumen:
   - 1-2 palabras
   - Términos principales del sector según la descripción
   - Estructura: [servicio] o [producto] más relevante

REGLAS ESTRICTAS:
✓ TODAS las keywords deben estar DIRECTAMENTE relacionadas con los servicios/productos mencionados en la descripción
✓ NO inventar servicios que no aparecen en la descripción
✓ NO usar ejemplos genéricos si no aplican
✓ Priorizar keywords con intención de búsqueda comercial/transaccional
✓ Incluir variaciones locales si la descripción menciona ubicación
✓ Usar lenguaje natural que los clientes realmente buscarían

FORMATO:
Devuelve SOLO las keywords separadas por comas, sin numeración ni explicaciones.
Ejemplo: keyword 1, keyword 2, keyword 3

Genera las keywords ahora:',
    'plugin_context' => null,
    'variables' => [
        [
            'key' => 'company_desc',
            'type' => 'text',
            'description' => 'Descripción completa de la empresa',
            'required' => true,
            'source' => 'campaign.company_desc'
        ],
        [
            'key' => 'niche',
            'type' => 'string',
            'description' => 'Nicho o sector general',
            'required' => false,
            'source' => 'campaign.niche'
        ]
    ],
    'estimated_tokens_input' => 350,
    'estimated_tokens_output' => 250,
    'updated_by' => 'system'
]);

echo "   ✓ keywords_seo creado\n\n";

echo "\n=== ✓ PROMPTS INICIALIZADOS CORRECTAMENTE ===\n";
echo "Total prompts creados: 3\n";
echo "\nAccede al panel admin para gestionarlos:\n";
echo "/admin/?module=prompts\n\n";
