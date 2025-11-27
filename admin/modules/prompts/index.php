<?php
/**
 * Módulo Prompts - Router Principal
 */

if (!defined('ADMIN_ACCESS') && !defined('API_ACCESS')) {
    die('Access denied');
}

// Obtener acción
$action = $_GET['action'] ?? 'list';

// Routing de acciones
switch ($action) {
    case 'edit':
        require_once __DIR__ . '/edit.php';
        break;
    
    case 'save':
        require_once __DIR__ . '/ajax.php';
        break;
    
    case 'list':
    default:
        // Lista de prompts
        $prompts_dir = API_BASE_DIR . '/prompts/';
        $prompts = [];

        if (is_dir($prompts_dir)) {
            $files = scandir($prompts_dir);
            foreach ($files as $file) {
                if (substr($file, -3) === '.md') {
                    $slug = substr($file, 0, -3);
                    $prompts[] = [
                        'slug' => $slug,
                        'name' => ucwords(str_replace('-', ' ', $slug)),
                        'file' => $file,
                        'size' => filesize($prompts_dir . $file)
                    ];
                }
            }
        }
        ?>

        <div class="prompt-manager">
            <div class="page-header">
                <h1>📝 Gestión de Prompts (.md)</h1>
                <p class="subtitle">Edita los prompts de la API directamente desde archivos Markdown</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($prompts); ?></div>
                    <div class="stat-label">Prompts Totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format(array_sum(array_column($prompts, 'size')) / 1024, 1); ?> KB</div>
                    <div class="stat-label">Tamaño Total</div>
                </div>
            </div>

            <div class="prompts-list">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Archivo</th>
                            <th>Tamaño</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prompts as $prompt): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($prompt['name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($prompt['file']); ?></code></td>
                            <td><?php echo round($prompt['size'] / 1024, 1); ?> KB</td>
                            <td>
                                <a href="?module=prompts&action=edit&slug=<?php echo urlencode($prompt['slug']); ?>" 
                                   class="btn-small btn-primary">✏️ Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .prompt-manager {
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            color: #2563eb;
        }

        .subtitle {
            color: #666;
            margin: 0;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .data-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .data-table thead {
            background: #f3f4f6;
        }

        .data-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }

        .data-table td {
            padding: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 4px;
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

        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 13px;
        }
        </style>
        <?php
        break;
}
