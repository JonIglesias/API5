<?php
/**
 * Admin Panel - Main Dashboard
 * 
 * @version 4.0
 */

// IMPORTANTE: Definir constante ANTES de cualquier require
define('API_ACCESS', true);

// Iniciar sesión ANTES de cualquier output
session_start();

// Cargar dependencias
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

// Requiere autenticación
Auth::require();

$user = Auth::user();
$module = $_GET['module'] ?? 'dashboard';

// Módulos válidos
$validModules = ['dashboard', 'licenses', 'sync', 'webhooks', 'plans', 'prompts', 'settings', 'license-stats', 'api-docs', 'models'];
if (!in_array($module, $validModules)) {
    $module = 'dashboard';
}

// Obtener estadísticas básicas para el dashboard
$db = Database::getInstance();

$stats = [
    'total_licenses' => 0,
    'active_licenses' => 0,
    'total_plans' => 0,
    'recent_syncs' => 0
];

try {
    // Contar licencias
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "licenses");
    $stats['total_licenses'] = $result[0]['total'] ?? 0;
    
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "licenses WHERE status = 'active'");
    $stats['active_licenses'] = $result[0]['total'] ?? 0;
    
    // Contar planes
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "plans WHERE is_active = 1");
    $stats['total_plans'] = $result[0]['total'] ?? 0;
    
    // Contar syncs recientes (últimas 24h)
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "sync_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['recent_syncs'] = $result[0]['total'] ?? 0;
    
} catch (Exception $e) {
    // Silenciar errores en producción
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - API Claude V4</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .logo h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .logo .version {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        
        .menu {
            list-style: none;
        }
        
        .menu-item {
            display: block;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .menu-item.active {
            background: #3498db;
            color: white;
        }
        
        .menu-item .icon {
            margin-right: 10px;
        }
        
        /* Main content */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .btn-logout {
            padding: 8px 16px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: #c0392b;
        }
        
        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-card.blue { border-left: 4px solid #3498db; }
        .stat-card.green { border-left: 4px solid #2ecc71; }
        .stat-card.orange { border-left: 4px solid #e67e22; }
        .stat-card.purple { border-left: 4px solid #9b59b6; }
        
        /* Content section */
        .content-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .content-section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h2>API Claude V4.2</h2>
                <p class="version">v<?= API_VERSION ?></p>
            </div>
            
            <nav>
                <a href="?module=dashboard" class="menu-item <?= $module === 'dashboard' ? 'active' : '' ?>">
                    <span class="icon">📊</span> Dashboard
                </a>
                <a href="?module=licenses" class="menu-item <?= $module === 'licenses' ? 'active' : '' ?>">
                    <span class="icon">🔑</span> Licencias
                </a>
                <a href="?module=license-stats" class="menu-item <?= $module === 'license-stats' ? 'active' : '' ?>">
                    <span class="icon">📈</span> Estadísticas
                </a>
                <a href="?module=sync" class="menu-item <?= $module === 'sync' ? 'active' : '' ?>">
                    <span class="icon">🔄</span> Sincronización
                </a>
                <a href="?module=webhooks" class="menu-item <?= $module === 'webhooks' ? 'active' : '' ?>">
                    <span class="icon">🔔</span> Webhooks
                </a>
                <a href="?module=plans" class="menu-item <?= $module === 'plans' ? 'active' : '' ?>">
                    <span class="icon">📦</span> Planes
                </a>
                <a href="?module=prompts" class="menu-item <?= $module === 'prompts' ? 'active' : '' ?>">
                    <span class="icon">📝</span> Prompts
                </a>
                <a href="?module=models" class="menu-item <?= $module === 'models' ? 'active' : '' ?>">
                    <span class="icon">🤖</span> Modelos OpenAI
                </a>
                <a href="?module=api-docs" class="menu-item <?= $module === 'api-docs' ? 'active' : '' ?>">
                    <span class="icon">📚</span> Documentación API
                </a>
                <a href="?module=settings" class="menu-item <?= $module === 'settings' ? 'active' : '' ?>">
                    <span class="icon">⚙️</span> Configuración
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>
                    <?php
                    $titles = [
                        'dashboard' => 'Dashboard',
                        'licenses' => 'Gestión de Licencias',
                        'license-stats' => 'Estadísticas por Licencia',
                        'sync' => 'Monitor de Sincronización',
                        'webhooks' => 'Monitor de Webhooks',
                        'plans' => 'Gestión de Planes',
                        'prompts' => 'Gestión de Prompts',
                        'models' => 'Modelos OpenAI',
                        'settings' => 'Configuración',
                        'api-docs' => 'Documentación API'
                    ];
                    echo $titles[$module] ?? 'Dashboard';
                    ?>
                </h1>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
                    <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
            </div>
            
            <?php if ($module === 'dashboard'): ?>
                <!-- Dashboard Stats -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="label">Total Licencias</div>
                        <div class="value"><?= $stats['total_licenses'] ?></div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="label">Licencias Activas</div>
                        <div class="value"><?= $stats['active_licenses'] ?></div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="label">Planes Activos</div>
                        <div class="value"><?= $stats['total_plans'] ?></div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="label">Syncs (24h)</div>
                        <div class="value"><?= $stats['recent_syncs'] ?></div>
                    </div>
                </div>
                
                <div class="content-section">
                    <h2>Bienvenido al Panel de Administración</h2>
                    <p>Sistema de gestión de licencias API Claude V4</p>
                </div>
                
            <?php else: ?>
                <!-- Cargar módulo -->
                <?php
                $modulePath = __DIR__ . '/modules/' . $module . '/index.php';
                if (file_exists($modulePath)) {
                    include $modulePath;
                } else {
                    ?>
                    <div class="content-section">
                        <div class="empty-state">
                            <div class="icon">🚧</div>
                            <h3>Módulo no encontrado</h3>
                            <p>El archivo <?= htmlspecialchars($modulePath) ?> no existe</p>
                        </div>
                    </div>
                    <?php
                }
                ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>