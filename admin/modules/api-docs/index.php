<?php
if (!defined('API_ACCESS')) exit;

// Definir todos los endpoints con sus especificaciones
$endpoints = [
    [
        'category' => 'Autenticación',
        'endpoints' => [
            [
                'name' => 'Verificar Licencia',
                'method' => 'POST',
                'path' => '/verify',
                'description' => 'Verifica la validez de una licencia y dominio',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'domain' => ['type' => 'string', 'required' => true, 'description' => 'Dominio a validar']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'valid' => true,
                        'license' => [
                            'key' => 'DEMO-PRO-2025-ABCD1234',
                            'status' => 'active',
                            'plan_id' => 'pro',
                            'plan_name' => 'Plan Pro',
                            'expires_at' => '2025-12-05',
                            'tokens_available' => 45000
                        ]
                    ]
                ],
                'response_error' => [
                    'success' => false,
                    'error' => 'License key is required',
                    'code' => 'VALIDATION_ERROR'
                ]
            ]
        ]
    ],
    [
        'category' => 'Información de Uso',
        'endpoints' => [
            [
                'name' => 'Obtener Uso de Licencia',
                'method' => 'GET',
                'path' => '/usage?license_key=XXX',
                'description' => 'Obtiene información de uso y límites de una licencia',
                'headers' => [],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia (query param)']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'plan_name' => 'Plan Pro',
                        'tokens_limit' => 200000,
                        'tokens_used' => 155000,
                        'tokens_remaining' => 45000,
                        'period_ends_at' => '2025-12-05'
                    ]
                ]
            ],
            [
                'name' => 'Obtener Plan Activo (V3)',
                'method' => 'GET',
                'path' => '/get-active-plan?license_key=XXX',
                'description' => 'Endpoint de compatibilidad V3 para obtener plan activo',
                'headers' => [],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia']
                ],
                'response_success' => [
                    'success' => true,
                    'plan' => [
                        'name' => 'Plan Pro',
                        'tokens_limit' => 200000,
                        'billing_cycle' => 'monthly'
                    ]
                ]
            ]
        ]
    ],
    [
        'category' => 'Generación de Contenido',
        'endpoints' => [
            [
                'name' => 'Generar Contenido',
                'method' => 'POST',
                'path' => '/generate/content',
                'description' => 'Genera contenido completo para un post. NOTA: El plugin usa /generate-post que es un alias de este endpoint (ambos hacen lo mismo).',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'title' => ['type' => 'string', 'required' => true, 'description' => 'Título del post'],
                    'prompt' => ['type' => 'string', 'required' => false, 'description' => 'Prompt personalizado'],
                    'keywords' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO'],
                    'length' => ['type' => 'string', 'required' => false, 'description' => 'corto/medio/largo (default: medio)']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'content' => 'Contenido generado...',
                        'tokens_used' => 1500,
                        'tokens_remaining' => 43500
                    ]
                ]
            ],
            [
                'name' => 'Generar Post (Alias) ✅',
                'method' => 'POST',
                'path' => '/generate-post',
                'description' => '✅ USADO ACTIVAMENTE por el plugin. Es un alias de /generate/content. El plugin usa este endpoint para generar todo el contenido de los posts.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'title' => ['type' => 'string', 'required' => true, 'description' => 'Título del post'],
                    'niche' => ['type' => 'string', 'required' => false, 'description' => 'Nicho o keywords'],
                    'company_description' => ['type' => 'string', 'required' => false, 'description' => 'Descripción de la empresa'],
                    'keywords_seo' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO'],
                    'custom_prompt' => ['type' => 'string', 'required' => false, 'description' => 'Prompt personalizado de la campaña']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'content' => 'Contenido HTML generado con IA...',
                        'usage' => [
                            'total_tokens' => 1500
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Generar Título',
                'method' => 'POST',
                'path' => '/generate/title',
                'description' => 'Genera títulos optimizados para SEO',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'topic' => ['type' => 'string', 'required' => true, 'description' => 'Tema del post'],
                    'keywords' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO'],
                    'num_suggestions' => ['type' => 'integer', 'required' => false, 'description' => 'Número de sugerencias (default: 5)']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'titles' => [
                            '10 Consejos para Mejorar tu SEO',
                            'Guía Completa de SEO en 2025',
                            'SEO para Principiantes: Todo lo que Necesitas Saber'
                        ],
                        'tokens_used' => 200
                    ]
                ]
            ],
            [
                'name' => 'Generar Keywords',
                'method' => 'POST',
                'path' => '/generate/keywords',
                'description' => 'Genera keywords SEO para un tema',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'topic' => ['type' => 'string', 'required' => true, 'description' => 'Tema o nicho'],
                    'num_keywords' => ['type' => 'integer', 'required' => false, 'description' => 'Número de keywords (default: 10)']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'keywords' => 'SEO, marketing digital, optimización web, posicionamiento, google',
                        'tokens_used' => 150
                    ]
                ]
            ],
            [
                'name' => 'Generar Meta (Multi-propósito)',
                'method' => 'POST',
                'path' => '/generate/meta',
                'description' => '✅ USADO por el plugin. IMPORTANTE: NO genera meta descriptions de posts. Se usa para: 1) Generar descripción de empresa (type=company_description), 2) Generar prompts de títulos (type=title_prompt). Las meta descriptions de posts se generan localmente en el plugin.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'type' => ['type' => 'string', 'required' => true, 'description' => 'company_description o title_prompt'],
                    'domain' => ['type' => 'string', 'required' => false, 'description' => 'Dominio (si type=company_description)'],
                    'niche' => ['type' => 'string', 'required' => false, 'description' => 'Nicho (si type=title_prompt)'],
                    'company_description' => ['type' => 'string', 'required' => false, 'description' => 'Descripción (si type=title_prompt)'],
                    'keywords_seo' => ['type' => 'string', 'required' => false, 'description' => 'Keywords (si type=title_prompt)']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'description' => 'Descripción de empresa generada...',
                        'prompt' => 'Prompt de títulos generado...',
                        'tokens_used' => 200
                    ]
                ]
            ],
            [
                'name' => 'Generar Excerpt',
                'method' => 'POST',
                'path' => '/generate-excerpt',
                'description' => '⚠️ NO USADO por el plugin. Genera resumen/excerpt usando IA. El plugin genera excerpts localmente (extracto de primeras 30 palabras) sin consumir tokens.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'content' => ['type' => 'string', 'required' => true, 'description' => 'Contenido completo']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'excerpt' => 'Resumen generado por IA (150 palabras)...',
                        'tokens_used' => 120
                    ]
                ]
            ],
            [
                'name' => 'Mejorar Contenido',
                'method' => 'POST',
                'path' => '/improve-content',
                'description' => '⚠️ NO USADO por el plugin. Mejora un contenido existente haciéndolo más claro y profesional usando IA.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'content' => ['type' => 'string', 'required' => true, 'description' => 'Contenido a mejorar'],
                    'instructions' => ['type' => 'string', 'required' => false, 'description' => 'Instrucciones específicas']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'improved_content' => 'Contenido mejorado por IA...',
                        'tokens_used' => 800
                    ]
                ]
            ]
        ]
    ],
    [
        'category' => 'Estadísticas',
        'endpoints' => [
            [
                'name' => 'Obtener Estadísticas',
                'method' => 'POST',
                'path' => '/get-stats',
                'description' => 'Obtiene estadísticas detalladas de uso',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'total_operations' => 150,
                        'total_tokens' => 155000,
                        'by_operation' => [
                            'content' => ['count' => 50, 'tokens' => 100000],
                            'title' => ['count' => 80, 'tokens' => 40000],
                            'keywords' => ['count' => 20, 'tokens' => 15000]
                        ]
                    ]
                ]
            ]
        ]
    ]
];
?>

<style>
.api-docs-wrapper {
    max-width: 1400px;
}

.api-category {
    background: white;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.category-header {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #ecf0f1;
}

.endpoint-card {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 16px;
    border-left: 4px solid #3498db;
}

.endpoint-card:last-child {
    margin-bottom: 0;
}

.endpoint-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.method-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
}

.method-post {
    background: #2ecc71;
    color: white;
}

.method-get {
    background: #3498db;
    color: white;
}

.endpoint-name {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.endpoint-path {
    background: #34495e;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    margin-bottom: 12px;
    display: inline-block;
}

.endpoint-description {
    color: #7f8c8d;
    margin-bottom: 16px;
}

.params-section, .response-section {
    margin-top: 16px;
}

.section-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 14px;
}

.params-table {
    width: 100%;
    background: white;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #ecf0f1;
}

.params-table th {
    background: #ecf0f1;
    padding: 10px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #2c3e50;
}

.params-table td {
    padding: 10px;
    border-top: 1px solid #ecf0f1;
    font-size: 13px;
}

.required-badge {
    background: #e74c3c;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.optional-badge {
    background: #95a5a6;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.code-block {
    background: #2c3e50;
    color: #ecf0f1;
    padding: 16px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    margin-top: 8px;
}

.code-block pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.toggle-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-left: 8px;
}

.toggle-btn:hover {
    background: #2980b9;
}

.response-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.response-tab {
    padding: 6px 16px;
    background: #ecf0f1;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
}

.response-tab.active {
    background: #3498db;
    color: white;
}

.response-content {
    display: none;
}

.response-content.active {
    display: block;
}

.search-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.search-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #ecf0f1;
    border-radius: 6px;
    font-size: 15px;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
}
</style>

<div class="api-docs-wrapper">
    <div class="search-box">
        <input type="text" 
               class="search-input" 
               id="endpoint-search" 
               placeholder="🔍 Buscar endpoint por nombre o ruta...">
    </div>
    
    <div class="api-category" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-left: none;">
        <h2 style="color: white; border-bottom-color: rgba(255,255,255,0.3);">ℹ️ Información Importante</h2>
        <div style="font-size: 14px; line-height: 1.6;">
            <p style="margin-bottom: 12px;"><strong>Endpoints marcados con:</strong></p>
            <ul style="list-style: none; padding-left: 0;">
                <li style="margin-bottom: 8px;">✅ <strong>USADO por el plugin</strong> - Endpoints activamente utilizados</li>
                <li style="margin-bottom: 8px;">⚠️ <strong>NO USADO por el plugin</strong> - Endpoints disponibles pero no utilizados actualmente</li>
            </ul>
            <p style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.3);">
                <strong>Nota sobre Meta Descriptions y Excerpts:</strong><br>
                El plugin genera las meta descriptions y excerpts de los posts <strong>localmente con PHP</strong> (extractos del contenido), 
                no mediante llamadas a la API. Esto ahorra tokens y mejora la velocidad.
            </p>
        </div>
    </div>

    <?php foreach ($endpoints as $category): ?>
    <div class="api-category">
        <h2 class="category-header">📁 <?= htmlspecialchars($category['category']) ?></h2>
        
        <?php foreach ($category['endpoints'] as $endpoint): ?>
        <div class="endpoint-card" data-search="<?= strtolower($endpoint['name'] . ' ' . $endpoint['path']) ?>">
            <div class="endpoint-header">
                <span class="method-badge method-<?= strtolower($endpoint['method']) ?>">
                    <?= $endpoint['method'] ?>
                </span>
                <span class="endpoint-name"><?= htmlspecialchars($endpoint['name']) ?></span>
            </div>
            
            <code class="endpoint-path"><?= htmlspecialchars(API_BASE_URL . $endpoint['path']) ?></code>
            
            <p class="endpoint-description"><?= htmlspecialchars($endpoint['description']) ?></p>
            
            <!-- Headers -->
            <?php if (!empty($endpoint['headers'])): ?>
            <div class="params-section">
                <div class="section-title">📋 Headers</div>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoint['headers'] as $header => $value): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($header) ?></code></td>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Parámetros -->
            <?php if (!empty($endpoint['params'])): ?>
            <div class="params-section">
                <div class="section-title">📝 Parámetros</div>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Requerido</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoint['params'] as $param => $details): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($param) ?></code></td>
                            <td><?= htmlspecialchars($details['type']) ?></td>
                            <td>
                                <?php if ($details['required']): ?>
                                    <span class="required-badge">REQUERIDO</span>
                                <?php else: ?>
                                    <span class="optional-badge">OPCIONAL</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($details['description']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Respuesta -->
            <div class="response-section">
                <div class="section-title">📤 Ejemplo de Respuesta</div>
                
                <div class="response-tabs">
                    <button class="response-tab active" onclick="showResponse(this, 'success')">✅ Success</button>
                    <?php if (isset($endpoint['response_error'])): ?>
                    <button class="response-tab" onclick="showResponse(this, 'error')">❌ Error</button>
                    <?php endif; ?>
                </div>
                
                <div class="response-content active" data-type="success">
                    <div class="code-block">
                        <pre><?= json_encode($endpoint['response_success'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    </div>
                </div>
                
                <?php if (isset($endpoint['response_error'])): ?>
                <div class="response-content" data-type="error">
                    <div class="code-block">
                        <pre><?= json_encode($endpoint['response_error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Ejemplo de petición cURL -->
            <div class="params-section" style="margin-top: 16px;">
                <div class="section-title">🔧 Ejemplo cURL</div>
                <div class="code-block">
                    <pre><?= generateCurlExample($endpoint) ?></pre>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
// Búsqueda de endpoints
document.getElementById('endpoint-search').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.endpoint-card');
    
    cards.forEach(card => {
        const searchText = card.getAttribute('data-search');
        if (searchText.includes(search)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Cambiar entre respuestas success/error
function showResponse(button, type) {
    const parent = button.closest('.response-section');
    
    // Actualizar tabs
    parent.querySelectorAll('.response-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    button.classList.add('active');
    
    // Actualizar contenido
    parent.querySelectorAll('.response-content').forEach(content => {
        content.classList.remove('active');
        if (content.getAttribute('data-type') === type) {
            content.classList.add('active');
        }
    });
}
</script>

<?php
function generateCurlExample($endpoint) {
    $curl = "curl -X {$endpoint['method']} '{API_BASE_URL}{$endpoint['path']}'";
    
    // Headers
    if (!empty($endpoint['headers'])) {
        foreach ($endpoint['headers'] as $header => $value) {
            $curl .= " \\\n  -H '{$header}: {$value}'";
        }
    }
    
    // Body (solo para POST)
    if ($endpoint['method'] === 'POST' && !empty($endpoint['params'])) {
        $body = [];
        foreach ($endpoint['params'] as $param => $details) {
            $body[$param] = "valor_ejemplo";
        }
        $curl .= " \\\n  -d '" . json_encode($body, JSON_UNESCAPED_UNICODE) . "'";
    }
    
    return $curl;
}
?>
