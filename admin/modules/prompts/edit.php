<?php
/**
 * Editor de Prompts .md
 */

if (!defined('ADMIN_ACCESS') && !defined('API_ACCESS')) {
    die('Access denied');
}

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: ?module=prompts');
    exit;
}

$prompts_dir = API_BASE_DIR . '/prompts/';
$file_path = $prompts_dir . $slug . '.md';

if (!file_exists($file_path)) {
    echo '<div class="error-message">❌ Prompt no encontrado: ' . htmlspecialchars($slug) . '</div>';
    echo '<a href="?module=prompts">← Volver</a>';
    exit;
}

$content = file_get_contents($file_path);
$name = ucwords(str_replace('-', ' ', $slug));

// Catálogo completo de variables con descripciones, ejemplos REALES y origen
$all_variables = [
    // Variables de análisis web
    'web_content_section' => [
        'desc' => 'Contenido extraído y parseado del sitio web (múltiples páginas con scraping inteligente)',
        'example' => "CONTENIDO DEL SITIO example.com:\n```\nServicios de marketing digital: SEO, SEM, redes sociales.\nEquipo experto con 10+ años experiencia...\n```",
        'origin' => 'Scraper inteligente analiza homepage + 8-10 páginas nivel 1 + hasta 5 páginas nivel 2 (servicios, soluciones, casos, etc)'
    ],
    
    // Variables de descripción de empresa
    'company_description' => [
        'desc' => 'Descripción completa de la empresa generada por IA tras analizar su web',
        'example' => 'Agencia de marketing digital especializada en SEO y SEM para empresas tecnológicas, con más de 10 años de experiencia en el sector.',
        'origin' => 'Generada por IA analizando el dominio. Se guarda en campaign_data tras paso 1 de Autopilot'
    ],
    'company_description_section' => [
        'desc' => 'Misma descripción pero formateada como sección de contexto',
        'example' => "DESCRIPCIÓN DE LA EMPRESA:\nAgencia de marketing digital especializada en SEO y SEM...",
        'origin' => 'Construida por API a partir de company_description'
    ],
    
    // Variables de nicho
    'niche' => [
        'desc' => 'Nicho o sector específico introducido por el usuario',
        'example' => 'Marketing Digital para E-commerce',
        'origin' => 'Campo "Nicho" del Wizard (paso 1), lista desplegable con opciones predefinidas'
    ],
    'niche_section' => [
        'desc' => 'Nicho formateado como sección de contexto',
        'example' => "NICHO: Marketing Digital para E-commerce",
        'origin' => 'Construida por API a partir del niche ingresado'
    ],
    
    // Variables de keywords SEO
    'keywords' => [
        'desc' => 'Lista de keywords SEO generadas por IA (string separado por comas)',
        'example' => 'seo, marketing digital, posicionamiento web, sem google ads, estrategia contenidos',
        'origin' => 'Generadas por IA en paso 2 de Autopilot usando niche + company_description'
    ],
    'keywords_seo' => [
        'desc' => 'Alias de keywords (mismo contenido)',
        'example' => 'seo, marketing digital, posicionamiento web, sem google ads, estrategia contenidos',
        'origin' => 'Mismo que keywords - usado indistintamente en la API'
    ],
    'keywords_section' => [
        'desc' => 'Keywords formateados como sección de contexto',
        'example' => "KEYWORDS SEO: seo, marketing digital, posicionamiento web, sem google ads...",
        'origin' => 'Construida por API a partir de keywords_seo'
    ],
    'keywords_seo_section' => [
        'desc' => 'Keywords SEO formateados como sección (mismo que keywords_section)',
        'example' => "KEYWORDS SEO: seo, marketing digital, posicionamiento web...",
        'origin' => 'Construida por API a partir de keywords_seo'
    ],
    
    // Variables de imágenes
    'keywords_images_base' => [
        'desc' => 'Keywords base para imágenes de toda la campaña (genéricos del nicho)',
        'example' => 'digital marketing, team meeting, laptop work, graphs analytics, social media',
        'origin' => 'Generados por IA en paso 4 de Autopilot para imágenes genéricas de la campaña'
    ],
    'image_keywords' => [
        'desc' => 'Keywords específicos para imágenes de un post concreto',
        'example' => 'seo audit, keyword research tools, google search results, rankings dashboard',
        'origin' => 'Generados por IA para cada post individual usando título + contexto'
    ],
    
    // Variables de títulos y prompts
    'title' => [
        'desc' => 'Título del post generado',
        'example' => 'Cómo Mejorar tu SEO en 2025: Guía Completa para E-commerce',
        'origin' => 'Generado por IA usando title_prompt + company_description + keywords'
    ],
    'title_prompt' => [
        'desc' => 'Prompt maestro generado para crear títulos coherentes con el nicho',
        'example' => 'Genera títulos de blog sobre marketing digital y e-commerce, enfocados en empresas tecnológicas, con tono profesional pero cercano...',
        'origin' => 'Generado por IA en paso 3 de Autopilot usando niche + company_description + keywords'
    ],
    'custom_prompt' => [
        'desc' => 'Prompt personalizado del plugin para generar contenido (PluginContentPrompt)',
        'example' => 'Escribe artículos de blog sobre marketing digital para e-commerce, dirigidos a responsables de marketing de empresas B2B...',
        'origin' => 'Generado por IA en paso 3 de Autopilot (prompt-contenido) y enviado por el plugin'
    ],
    'content_prompt' => [
        'desc' => 'OBSOLETO: Usar custom_prompt en su lugar',
        'example' => 'Escribe artículos de blog sobre marketing digital para e-commerce, dirigidos a responsables de marketing de empresas B2B...',
        'origin' => 'OBSOLETO - Nombre antiguo, ahora se llama custom_prompt'
    ],
    
    // Variables de campaña
    'campaign_name' => [
        'desc' => 'Nombre de la campaña en el plugin',
        'example' => 'Campaña SEO Q1 2025',
        'origin' => 'Campo "Nombre de Campaña" en Wizard (paso 1)'
    ],
    'num_posts' => [
        'desc' => 'Número de posts a generar en la campaña',
        'example' => '10',
        'origin' => 'Campo numérico en generación de cola (después de Autopilot)'
    ],
    
    // Variables de contexto combinado
    'context_sections' => [
        'desc' => 'Contexto completo combinando niche, company_desc y keywords',
        'example' => "NICHO: Marketing Digital\nEMPRESA: Agencia especializada...\nKEYWORDS: seo, sem, marketing...",
        'origin' => 'Construida por API combinando múltiples variables previas'
    ],
    
    // Variables de configuración
    'config_section' => [
        'desc' => 'Configuración del post: número de secciones, palabras, estilo, etc.',
        'example' => "CONFIGURACIÓN:\n- 5 secciones\n- 1500 palabras aprox\n- Tono: profesional\n- Incluir ejemplos prácticos",
        'origin' => 'Construida por API según parámetros de configuración del endpoint'
    ],
    'min_words' => [
        'desc' => 'Mínimo de palabras para el contenido del post',
        'example' => '1200',
        'origin' => 'Parámetro enviado en request (configuración del plugin o valor por defecto)'
    ],
    'max_words' => [
        'desc' => 'Máximo de palabras para el contenido del post',
        'example' => '1800',
        'origin' => 'Parámetro enviado en request (configuración del plugin o valor por defecto)'
    ]
];

// Mapeo de variables disponibles por cada MD
// SOLO incluye las variables que REALMENTE se usan en los templates
$md_available_vars = [
    'descripcion-empresa' => [
        'web_content_section'        // Contenido scrapeado del sitio web
    ],
    'keywords-seo' => [
        'company_description',       // Descripción de empresa
        'niche_section'              // Sección de nicho formateada
    ],
    'prompt-titulos' => [
        'company_description',       // Descripción de empresa
        'keywords_section',          // Sección de keywords formateada
        'niche_section'              // Sección de nicho formateada
    ],
    'prompt-contenido' => [
        'company_description_section', // Sección de descripción formateada
        'keywords_section',            // Sección de keywords formateada
        'niche_section'                // Sección de nicho formateada
    ],
    'generar-titulo' => [
        'company_description',       // Descripción de empresa
        'title_prompt',              // Prompt maestro del plugin para títulos
        'keywords_seo'               // Keywords SEO (string)
    ],
    'generar-contenido' => [
        'custom_prompt',             // Prompt maestro del plugin para contenido
        'title',                     // Título del post
        'niche',                     // Nicho/categoría
        'company_description',       // Descripción de empresa
        'min_words',                 // Mínimo de palabras
        'max_words'                  // Máximo de palabras
    ],
    'post-completo' => [
        'company_description',       // Descripción de empresa
        'niche_section',             // Sección de nicho formateada
        'keywords_section',          // Sección de keywords formateada
        'config_section'             // Sección de configuración
    ],
    'keywords-campana' => [
        'niche',                     // Nicho/categoría
        'company_description',       // Descripción de empresa
        'keywords_seo_section'       // Sección de keywords SEO formateada
    ],
    'keywords-imagenes' => [
        'title_section',             // Sección del título formateada
        'company_description',       // Descripción de empresa
        'keywords_images_base',      // Keywords base de imágenes de campaña
        'adapt_section',             // Sección de adaptación (si hay base keywords)
        'maintain_section',          // Sección de coherencia visual
        'rules_section'              // Reglas mandatorias
    ]
];

// Detectar qué variables usa este MD específico (para marcarlas como "en uso")
preg_match_all('/\{\{([a-z_]+)\}\}/', $content, $matches);
$used_variables = array_unique($matches[1]);

// Filtrar solo las variables DISPONIBLES para este MD específico
$available_for_this_md = $md_available_vars[$slug] ?? array_keys($all_variables);
$available_vars = array_filter($all_variables, function($key) use ($available_for_this_md) {
    return in_array($key, $available_for_this_md);
}, ARRAY_FILTER_USE_KEY);
?>

<div class="prompt-editor-layout">
    <!-- Sidebar de ayuda -->
    <div class="help-sidebar" id="help-sidebar">
        <div class="sidebar-header">
            <h3>📘 Ayuda de Variables</h3>
            <button type="button" class="close-sidebar" onclick="toggleSidebar()">✕</button>
        </div>
        <div class="sidebar-content" id="sidebar-content">
            <p class="sidebar-intro">Haz clic en una variable para ver su descripción y ejemplo</p>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="editor-main">
        <div class="page-header">
            <h1>✏️ Editar: <?php echo htmlspecialchars($name); ?></h1>
            <p class="subtitle"><code><?php echo htmlspecialchars($slug . '.md'); ?></code></p>
        </div>

        <form id="prompt-form">
            <div class="form-group">
                <label>Contenido del Prompt:</label>
                
                <?php if (!empty($available_vars)): ?>
                <!-- Botones dinámicos - TODAS las variables disponibles -->
                <div class="variable-buttons">
                    <div class="variables-header">
                        <strong>Variables disponibles para este endpoint (<?php echo count($available_vars); ?> total, <?php echo count($used_variables); ?> en uso):</strong>
                        <button type="button" class="btn-help-toggle" onclick="toggleSidebar()">
                            <?php echo empty($used_variables) ? '📘' : '💡'; ?> Ayuda
                        </button>
                    </div>
                    <div class="variables-grid">
                        <?php foreach ($available_vars as $var => $info): ?>
                        <?php $in_use = in_array($var, $used_variables); ?>
                        <button type="button" 
                                class="btn-variable <?php echo $in_use ? 'in-use' : ''; ?>" 
                                data-var="<?php echo $var; ?>"
                                onclick="insertVariable('{{<?php echo $var; ?>}}')"
                                onmouseenter="showHelp('<?php echo $var; ?>')"
                                title="<?php echo htmlspecialchars($info['desc']); ?>">
                            <?php if ($in_use): ?>
                            <span class="use-badge">✓ En uso</span>
                            <?php endif; ?>
                            <code>{{<?php echo $var; ?>}}</code>
                            <span class="var-desc"><?php echo htmlspecialchars($info['desc']); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-variables-info">
                    ℹ️ Este prompt no usa variables dinámicas
                </div>
                <?php endif; ?>
                
                <textarea id="prompt-content" name="content" rows="30"><?php echo htmlspecialchars($content); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">💾 Guardar Cambios</button>
                <a href="?module=prompts" class="btn-secondary">← Volver</a>
            </div>

            <div id="save-message" class="message" style="display:none;"></div>
        </form>
    </div>
</div>

<!-- Data para JavaScript -->
<script>
const VARIABLES_DATA = <?php echo json_encode($all_variables); ?>;
</script>

<style>
.prompt-editor-layout {
    display: flex;
    gap: 0;
    min-height: 100vh;
    margin: -20px;
}

.help-sidebar {
    width: 350px;
    background: #1e40af;
    color: white;
    padding: 20px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.help-sidebar[style*="display: none"] {
    display: none !important;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
}

.sidebar-header h3 {
    margin: 0;
    font-size: 18px;
}

.close-sidebar {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 20px;
    line-height: 1;
}

.close-sidebar:hover {
    background: rgba(255, 255, 255, 0.3);
}

.sidebar-content {
    color: rgba(255, 255, 255, 0.9);
}

.sidebar-intro {
    font-size: 14px;
    opacity: 0.8;
    font-style: italic;
}

.var-help-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 3px solid #60a5fa;
}

.var-help-item h4 {
    margin: 0 0 8px 0;
    font-family: 'Courier New', monospace;
    color: #60a5fa;
    font-size: 14px;
}

.var-help-desc {
    font-size: 13px;
    margin-bottom: 10px;
    line-height: 1.5;
}

.var-help-example {
    background: rgba(0, 0, 0, 0.2);
    padding: 10px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    color: #93c5fd;
    white-space: pre-wrap;
    word-break: break-word;
}

.var-help-example-label {
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.7;
    margin-bottom: 5px;
    font-weight: 600;
}

.editor-main {
    flex: 1;
    padding: 20px;
    background: white;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    margin: 0 0 5px 0;
    color: #2563eb;
}

.subtitle {
    color: #666;
    margin: 0;
}

.subtitle code {
    background: #f3f4f6;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.form-group small {
    display: block;
    margin-top: 8px;
    color: #6b7280;
}

.variable-buttons {
    margin-bottom: 15px;
    padding: 15px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.variables-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    color: #374151;
    font-size: 14px;
}

.btn-help-toggle {
    padding: 6px 12px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-help-toggle:hover {
    background: #1d4ed8;
}

.variables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
}

.btn-variable {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 10px 12px;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
    position: relative;
}

.btn-variable.in-use {
    background: #dbeafe;
    border-color: #3b82f6;
}

.use-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 9px;
    background: #10b981;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: 600;
}

.btn-variable code {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: #2563eb;
    font-weight: 600;
    margin-bottom: 4px;
}

.btn-variable.in-use code {
    color: #1d4ed8;
}

.btn-variable .var-desc {
    font-size: 11px;
    color: #6b7280;
    line-height: 1.3;
}

.btn-variable:hover {
    background: #eff6ff;
    border-color: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
}

.btn-variable:hover code {
    color: #1d4ed8;
}

.btn-variable.active {
    background: #dbeafe;
    border-color: #2563eb;
}

.var-help-origin {
    background: rgba(0, 0, 0, 0.15);
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    line-height: 1.5;
    margin: 10px 0;
    border-left: 3px solid #60a5fa;
}

.var-help-origin strong {
    color: #93c5fd;
    display: block;
    margin-bottom: 5px;
}

.no-variables-info {
    padding: 12px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    color: #6b7280;
    margin-bottom: 15px;
    font-size: 14px;
}

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.6;
    resize: vertical;
    box-sizing: border-box;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn-primary, .btn-secondary {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.message {
    padding: 12px;
    border-radius: 6px;
    margin-top: 20px;
}

.message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.error-message {
    padding: 20px;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    margin-bottom: 20px;
}

@media (max-width: 1024px) {
    .help-sidebar {
        position: fixed;
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    .variables-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Función para mostrar/ocultar sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('help-sidebar');
    const main = document.querySelector('.editor-main');
    
    if (sidebar.style.display === 'none') {
        sidebar.style.display = 'block';
        main.style.marginLeft = '0';
    } else {
        sidebar.style.display = 'none';
        main.style.marginLeft = '0';
    }
}

// Función para mostrar ayuda de una variable específica
function showHelp(varName) {
    const sidebar = document.getElementById('help-sidebar');
    const content = document.getElementById('sidebar-content');
    
    // Asegurar que el sidebar esté visible
    if (sidebar.style.display === 'none') {
        sidebar.style.display = 'block';
    }
    
    // Obtener datos de la variable
    const varData = VARIABLES_DATA[varName];
    if (!varData) return;
    
    // Mostrar información
    content.innerHTML = `
        <div class="var-help-item">
            <h4>{{${varName}}}</h4>
            
            <div class="var-help-desc">${varData.desc}</div>
            
            <div class="var-help-origin">
                <strong>📍 Origen:</strong><br>
                ${varData.origin}
            </div>
            
            <div class="var-help-example-label">💡 Ejemplo de contenido:</div>
            <div class="var-help-example">${escapeHtml(varData.example)}</div>
        </div>
    `;
}

// Función para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Función para insertar variable en la posición del cursor
function insertVariable(variable) {
    const textarea = document.getElementById('prompt-content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    // Insertar variable en la posición del cursor
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    
    // Mover cursor después de la variable insertada
    const newPos = start + variable.length;
    textarea.selectionStart = newPos;
    textarea.selectionEnd = newPos;
    
    // Focus en textarea
    textarea.focus();
}

document.getElementById('prompt-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const content = document.getElementById('prompt-content').value;
    const message = document.getElementById('save-message');
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Guardando...';
    
    try {
        // Ruta relativa al ajax.php del mismo módulo
        const ajaxUrl = window.location.pathname.replace(/\/admin\/.*/, '/admin/modules/prompts/ajax.php?action=save');
        
        const response = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                slug: '<?php echo addslashes($slug); ?>',
                content: content
            })
        });
        
        const result = await response.json();
        
        message.style.display = 'block';
        if (result.success) {
            message.className = 'message success';
            message.textContent = '✅ Prompt guardado correctamente (' + result.bytes + ' bytes)';
        } else {
            message.className = 'message error';
            message.textContent = '❌ Error: ' + result.error;
        }
    } catch (error) {
        message.style.display = 'block';
        message.className = 'message error';
        message.textContent = '❌ Error al guardar: ' + error.message;
    } finally {
        // Rehabilitar botón
        submitBtn.disabled = false;
        submitBtn.textContent = '💾 Guardar Cambios';
    }
});
</script>
