<?php
if (!defined('API_ACCESS')) die('Access denied');

$db = Database::getInstance();

// Mapeo de operation_type a endpoint y descripción
$operationTypeMap = [
    'queue' => [
        'endpoint' => 'Agrupación',
        'description' => 'Cola generada (Title + Keywords de Imagen de Post)'
    ],
    'title' => [
        'endpoint' => '/generate/title',
        'description' => 'Títulos'
    ],
    'keywords' => [
        'endpoint' => '/generate/keywords',
        'description' => 'Generación de palabras clave'
    ],
    'keywords_images' => [
        'endpoint' => '/generate/keywords-images',
        'description' => 'Set de Keywords de Imagen de Post'
    ],
    'keywords_seo' => [
        'endpoint' => '/generate/keywords-seo',
        'description' => 'Set de Keywords SEO'
    ],
    'content' => [
        'endpoint' => '/generate/content',
        'description' => 'Generación de contenido completo'
    ],
    'meta' => [
        'endpoint' => '/generate/meta',
        'description' => 'Meta descripciones para SEO'
    ],
    'company_description' => [
        'endpoint' => '/generate/company-description',
        'description' => 'Descripción de Empresa'
    ],
    'content_prompt' => [
        'endpoint' => '/generate/content-prompt',
        'description' => 'Prompt de Contenido'
    ],
    'title_prompt' => [
        'endpoint' => '/generate/title-prompt',
        'description' => 'Prompt para Títulos'
    ],
    'campaign_image_keywords' => [
        'endpoint' => '/generate/campaign-image-keywords',
        'description' => 'Set de Keywords de Imagen de Campaña'
    ]
];

function getOperationInfo($operationType) {
    global $operationTypeMap;
    return $operationTypeMap[$operationType] ?? [
        'endpoint' => '/generate/' . $operationType,
        'description' => ucfirst(str_replace('_', ' ', $operationType))
    ];
}

// Obtener filtros
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$licenseFilter = $_GET['license'] ?? 'all';

// Obtener todas las licencias para el filtro
$licenses = $db->query("SELECT id, license_key, user_email FROM " . DB_PREFIX . "licenses ORDER BY license_key");

// Construir query de estadísticas
$where = ["DATE(created_at) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($licenseFilter !== 'all') {
    $where[] = "license_id = ?";
    $params[] = $licenseFilter;
}

$whereSQL = implode(' AND ', $where);

// Obtener estadísticas generales
$totals = $db->fetchOne("
    SELECT 
        COUNT(*) as total_operations,
        SUM(tokens_total) as total_tokens,
        COUNT(DISTINCT license_id) as unique_licenses
    FROM " . DB_PREFIX . "usage_tracking
    WHERE {$whereSQL}
", $params);

// Estadísticas por tipo de operación (con agrupación de colas)
$rawByOperation = $db->query("
    SELECT 
        operation_type,
        batch_type,
        batch_id,
        COUNT(*) as count,
        SUM(tokens_total) as tokens
    FROM " . DB_PREFIX . "usage_tracking
    WHERE {$whereSQL}
    GROUP BY operation_type, batch_type, batch_id
    ORDER BY tokens DESC
", $params);

// Procesar y agrupar colas
$byOperation = [];
$queues = [];

foreach ($rawByOperation as $row) {
    $opType = $row['operation_type'];
    $batchType = $row['batch_type'];
    $batchId = $row['batch_id'];
    
    // Si es parte de una cola
    if ($batchType === 'queue' && !empty($batchId)) {
        if (!isset($queues[$batchId])) {
            $queues[$batchId] = [
                'tokens' => 0,
                'items' => []
            ];
        }
        $queues[$batchId]['tokens'] += intval($row['tokens']);
        $queues[$batchId]['items'][] = $opType;
    } else {
        // Operaciones individuales
        if (!isset($byOperation[$opType])) {
            $byOperation[$opType] = [
                'operation_type' => $opType,
                'count' => 0,
                'tokens' => 0
            ];
        }
        $byOperation[$opType]['count'] += intval($row['count']);
        $byOperation[$opType]['tokens'] += intval($row['tokens']);
    }
}

// Añadir "Colas Generadas" como primer elemento si existen
if (count($queues) > 0) {
    $queuesTotalTokens = array_sum(array_column($queues, 'tokens'));
    $byOperation = array_merge([
        'queue' => [
            'operation_type' => 'queue',
            'count' => count($queues),
            'tokens' => $queuesTotalTokens
        ]
    ], $byOperation);
}

// Ordenar por tokens
uasort($byOperation, function($a, $b) {
    if ($a['operation_type'] === 'queue') return -1;
    if ($b['operation_type'] === 'queue') return 1;
    return $b['tokens'] - $a['tokens'];
});

// Estadísticas por licencia (top 10)
$byLicense = $db->query("
    SELECT 
        l.license_key,
        l.user_email,
        COUNT(ut.id) as operations,
        SUM(ut.tokens_total) as tokens
    FROM " . DB_PREFIX . "usage_tracking ut
    JOIN " . DB_PREFIX . "licenses l ON ut.license_id = l.id
    WHERE {$whereSQL}
    GROUP BY ut.license_id
    ORDER BY tokens DESC
    LIMIT 10
", $params);

// Estadísticas por día (últimos 30 días)
$byDay = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as operations,
        SUM(tokens_total) as tokens
    FROM " . DB_PREFIX . "usage_tracking
    WHERE {$whereSQL}
    GROUP BY DATE(created_at)
    ORDER BY date ASC
", $params);
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
    margin: 10px 0;
}
.stat-label {
    color: #666;
    font-size: 14px;
}
.chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
canvas {
    max-height: 300px;
}
</style>

<div class="page-header">
    <h1>📊 Estadísticas de Uso</h1>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 15px; align-items: end;">
            <input type="hidden" name="module" value="stats">
            
            <div class="form-group" style="margin: 0;">
                <label>Desde</label>
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Hasta</label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            
            <div class="form-group" style="margin: 0; flex: 1;">
                <label>Licencia</label>
                <select name="license" class="form-control">
                    <option value="all">Todas las licencias</option>
                    <?php foreach ($licenses as $lic): ?>
                        <option value="<?= $lic['id'] ?>" <?= $licenseFilter == $lic['id'] ? 'selected' : '' ?>>
                            <?= $lic['license_key'] ?> (<?= $lic['user_email'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </form>
    </div>
</div>

<!-- Resumen General -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Operaciones</div>
        <div class="stat-value"><?= number_format($totals['total_operations'] ?? 0) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Total Tokens</div>
        <div class="stat-value"><?= number_format($totals['total_tokens'] ?? 0) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Licencias Activas</div>
        <div class="stat-value"><?= $totals['unique_licenses'] ?? 0 ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Promedio por Operación</div>
        <div class="stat-value">
            <?php 
            $avg = ($totals['total_operations'] ?? 0) > 0 
                ? ($totals['total_tokens'] ?? 0) / $totals['total_operations'] 
                : 0;
            echo number_format($avg, 0);
            ?>
        </div>
        <div class="stat-label">tokens/op</div>
    </div>
</div>

<!-- Por Tipo de Operación -->
<div class="card">
    <div class="card-header">
        <h3>📈 Por Tipo de Operación</h3>
    </div>
    <div class="card-body">
        <?php if (empty($byOperation)): ?>
            <p style="text-align: center; color: #999; padding: 40px;">
                No hay datos para el periodo seleccionado
            </p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Operaciones</th>
                        <th>Tokens</th>
                        <th>% del Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byOperation as $op): ?>
                        <?php 
                        $percent = ($totals['total_tokens'] ?? 0) > 0 
                            ? ($op['tokens'] / $totals['total_tokens']) * 100 
                            : 0;
                        $opInfo = getOperationInfo($op['operation_type']);
                        $displayName = $opInfo['description'];
                        $isQueue = $op['operation_type'] === 'queue';
                        $icon = $isQueue ? '📦 ' : '';
                        $style = $isQueue ? 'font-weight: bold; color: #667eea;' : '';
                        ?>
                        <tr>
                            <td>
                                <strong 
                                    title="<?= htmlspecialchars($opInfo['endpoint']) ?>&#10;<?= htmlspecialchars($opInfo['description']) ?>" 
                                    style="cursor: help; text-decoration: underline dotted; <?= $style ?>">
                                    <?= $icon ?><?= $displayName ?>
                                </strong>
                            </td>
                            <td><?= number_format($op['count']) ?></td>
                            <td><?= number_format($op['tokens']) ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="flex: 1; background: #eee; height: 20px; border-radius: 10px; overflow: hidden;">
                                        <div style="background: #667eea; width: <?= $percent ?>%; height: 100%;"></div>
                                    </div>
                                    <span><?= number_format($percent, 1) ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Top Licencias -->
<div class="card">
    <div class="card-header">
        <h3>🏆 Top 10 Licencias</h3>
    </div>
    <div class="card-body">
        <?php if (empty($byLicense)): ?>
            <p style="text-align: center; color: #999; padding: 40px;">
                No hay datos para el periodo seleccionado
            </p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>License Key</th>
                        <th>Email</th>
                        <th>Operaciones</th>
                        <th>Tokens</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byLicense as $lic): ?>
                        <tr>
                            <td><code><?= $lic['license_key'] ?></code></td>
                            <td><?= $lic['user_email'] ?></td>
                            <td><?= number_format($lic['operations']) ?></td>
                            <td><?= number_format($lic['tokens']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Gráfico por Día -->
<?php if (!empty($byDay)): ?>
<div class="card">
    <div class="card-header">
        <h3>📅 Uso por Día</h3>
    </div>
    <div class="card-body">
        <canvas id="dailyChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const ctx = document.getElementById('dailyChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($byDay, 'date')) ?>,
        datasets: [{
            label: 'Tokens Usados',
            data: <?= json_encode(array_column($byDay, 'tokens')) ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
<?php endif; ?>
