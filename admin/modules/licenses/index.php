<?php
if (!defined('API_ACCESS')) die('Access denied');

$db = Database::getInstance();
?>
<style>
.page-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 30px;
}
.page-header h1 { font-size: 28px; margin: 0; }
.card { 
    background: white; 
    border-radius: 8px; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
    margin-bottom: 20px; 
    overflow: hidden;
}
.card-header { 
    padding: 20px; 
    border-bottom: 1px solid #eee; 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
}
.card-header h3 { margin: 0; font-size: 20px; }
.card-body { padding: 20px; }
.form-group { margin-bottom: 20px; }
.form-group label { 
    display: block; 
    margin-bottom: 5px; 
    font-weight: 500; 
    color: #333;
}
.form-group small { 
    display: block; 
    margin-top: 5px; 
    color: #666; 
    font-size: 13px;
}
.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
}
.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-primary { background: #3498db; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-warning { background: #ffc107; color: #333; }
.btn-danger { background: #dc3545; color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn:hover { opacity: 0.9; transform: translateY(-1px); }
.alert {
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 20px;
    font-weight: 500;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.table {
    width: 100%;
    border-collapse: collapse;
}
.table thead tr { background: #f8f9fa; }
.table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    font-size: 14px;
}
.table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
}
.table tr:hover { background: #f8f9fa; }
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
.badge-active { background: #d4edda; color: #155724; }
.badge-suspended { background: #fff3cd; color: #856404; }
.badge-expired { background: #f8d7da; color: #721c24; }
.badge-cancelled { background: #e2e3e5; color: #383d41; }
.actions {
    display: flex;
    gap: 5px;
    align-items: center;
}
.actions form { display: inline; }
code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
hr {
    border: none;
    border-top: 1px solid #eee;
    margin: 20px 0;
}
h4 {
    margin: 20px 0 10px;
    font-size: 18px;
    color: #333;
}
</style>
<?php

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $license_key = strtoupper($_POST['license_key'] ?? '');
        $user_email = $_POST['user_email'] ?? '';
        $plan_id = $_POST['plan_id'] ?? '';
        $woo_subscription_id = intval($_POST['woo_subscription_id'] ?? 0);
        $woo_user_id = intval($_POST['woo_user_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $domain = $_POST['domain'] ?? '';
        
        try {
            // Obtener límite de tokens del plan
            $plan = $db->fetchOne("SELECT tokens_per_month FROM " . DB_PREFIX . "plans WHERE id=?", [$plan_id]);
            $tokens_limit = $plan['tokens_per_month'] ?? 0;
            
            // Calcular periodo
            $period_starts_at = date('Y-m-d H:i:s');
            $period_ends_at = date('Y-m-d H:i:s', strtotime('+1 month'));
            
            $db->insert('licenses', [
                'license_key' => $license_key,
                'user_email' => $user_email,
                'woo_subscription_id' => $woo_subscription_id,
                'woo_user_id' => $woo_user_id,
                'plan_id' => $plan_id,
                'status' => $status,
                'domain' => $domain,
                'tokens_limit' => $tokens_limit,
                'tokens_used_this_period' => 0,
                'period_starts_at' => $period_starts_at,
                'period_ends_at' => $period_ends_at,
                'last_synced_at' => date('Y-m-d H:i:s'),
                'sync_status' => 'fresh'
            ]);
            $success = true;
            $message = "Licencia creada correctamente";
        } catch (Exception $e) {
            $success = false;
            $message = "Error al crear licencia: " . $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        $id = intval($_POST['id']);
        $user_email = $_POST['user_email'] ?? '';
        $plan_id = $_POST['plan_id'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $domain = $_POST['domain'] ?? '';
        $woo_subscription_id = intval($_POST['woo_subscription_id'] ?? 0);
        $woo_user_id = intval($_POST['woo_user_id'] ?? 0);
        
        try {
            // Obtener límite de tokens del plan
            $plan = $db->fetchOne("SELECT tokens_per_month FROM " . DB_PREFIX . "plans WHERE id=?", [$plan_id]);
            $tokens_limit = $plan['tokens_per_month'] ?? 0;
            
            $stmt = $db->prepare("
                UPDATE " . DB_PREFIX . "licenses 
                SET user_email=?, plan_id=?, status=?, domain=?, 
                    woo_subscription_id=?, woo_user_id=?, tokens_limit=?
                WHERE id=?
            ");
            $stmt->execute([
                $user_email, $plan_id, $status, $domain, 
                $woo_subscription_id, $woo_user_id, $tokens_limit, $id
            ]);
            $success = true;
            $message = "Licencia actualizada correctamente";
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'suspend' || $action === 'activate') {
        $id = intval($_POST['license_id']);
        $newStatus = $action === 'suspend' ? 'suspended' : 'active';
        try {
            $stmt = $db->prepare("UPDATE " . DB_PREFIX . "licenses SET status=? WHERE id=?");
            $stmt->execute([$newStatus, $id]);
            $success = true;
            $message = "Estado actualizado";
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['license_id']);
        try {
            $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "licenses WHERE id=?");
            $stmt->execute([$id]);
            $success = true;
            $message = "Licencia eliminada";
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'reset_tokens') {
        $id = intval($_POST['license_id']);
        try {
            $stmt = $db->prepare("UPDATE " . DB_PREFIX . "licenses SET tokens_used_this_period=0 WHERE id=?");
            $stmt->execute([$id]);
            $success = true;
            $message = "Tokens reseteados";
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Obtener licencias con filtros
$where = [];
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== 'all') {
    $where[] = "l.status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['plan']) && $_GET['plan'] !== 'all') {
    $where[] = "l.plan_id = ?";
    $params[] = $_GET['plan'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = "(l.license_key LIKE ? OR l.user_email LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "
    SELECT l.*, p.name as plan_name 
    FROM " . DB_PREFIX . "licenses l
    LEFT JOIN " . DB_PREFIX . "plans p ON l.plan_id = p.id
    $whereSQL
    ORDER BY l.created_at DESC
";

$licenses = $db->query($query, $params);

// Obtener planes para filtros
$plans = $db->query("SELECT * FROM " . DB_PREFIX . "plans ORDER BY name");

// Si estamos viendo/editando una licencia
$viewLicense = null;
if (isset($_GET['view'])) {
    $viewId = intval($_GET['view']);
    $viewLicense = $db->fetchOne("
        SELECT l.*, p.name as plan_name 
        FROM " . DB_PREFIX . "licenses l
        LEFT JOIN " . DB_PREFIX . "plans p ON l.plan_id = p.id
        WHERE l.id=?
    ", [$viewId]);
}

$editLicense = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editLicense = $db->fetchOne("SELECT * FROM " . DB_PREFIX . "licenses WHERE id=?", [$editId]);
}
?>

<div class="page-header">
    <h1>🔑 Gestión de Licencias</h1>
    <?php if (!isset($_GET['new']) && !isset($_GET['edit']) && !isset($_GET['view'])): ?>
        <a href="?module=licenses&new=1" class="btn btn-primary">+ Nueva Licencia</a>
    <?php endif; ?>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['view']) && $viewLicense): ?>
    <!-- VISTA DETALLADA COMPLETA -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Detalle de Licencia</h3>
            <div class="actions">
                <a href="?module=licenses&edit=<?= $viewLicense['id'] ?>" class="btn btn-primary">Editar</a>
                <a href="?module=licenses" class="btn btn-secondary">Volver</a>
            </div>
        </div>
        <div class="card-body">
            <!-- INFORMACIÓN DEL CLIENTE -->
            <h4>👤 Información del Cliente</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div>
                    <p><strong>Nombre:</strong> <?= $viewLicense['customer_name'] ?: 'No disponible' ?></p>
                    <p><strong>Email:</strong> <?= $viewLicense['user_email'] ?></p>
                    <p><strong>País:</strong> <?= $viewLicense['customer_country'] ?: 'No disponible' ?></p>
                </div>
                <div>
                    <p><strong>WooCommerce User ID:</strong> #<?= $viewLicense['woo_user_id'] ?: 'N/A' ?></p>
                    <p><strong>WooCommerce Subscription ID:</strong>
                        <?php if ($viewLicense['woo_subscription_id']): ?>
                            <a href="https://bocetosmarketing.com/wp-admin/post.php?post=<?= $viewLicense['woo_subscription_id'] ?>&action=edit"
                               target="_blank"
                               style="color: #3498db; text-decoration: none; font-weight: 500;">
                                #<?= $viewLicense['woo_subscription_id'] ?> 🔗
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </p>
                    <p><strong>Última Order ID:</strong>
                        <?php if ($viewLicense['last_order_id']): ?>
                            <a href="https://bocetosmarketing.com/wp-admin/post.php?post=<?= $viewLicense['last_order_id'] ?>&action=edit"
                               target="_blank"
                               style="color: #3498db; text-decoration: none; font-weight: 500;">
                                #<?= $viewLicense['last_order_id'] ?> 🔗
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <!-- INFORMACIÓN DE LA LICENCIA -->
            <h4>🔑 Información de la Licencia</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div>
                    <p><strong>License Key:</strong> <code style="font-size: 16px;"><?= $viewLicense['license_key'] ?></code></p>
                    <p><strong>Plan:</strong> <?= $viewLicense['plan_name'] ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge badge-<?= $viewLicense['status'] ?>">
                            <?= ucfirst($viewLicense['status']) ?>
                        </span>
                    </p>
                    <p><strong>Dominio:</strong> <?= $viewLicense['domain'] ?: 'No asignado' ?></p>
                </div>
                <div>
                    <p><strong>Fecha de Creación:</strong> <?= date('d/m/Y H:i', strtotime($viewLicense['created_at'])) ?></p>
                    <p><strong>Última Actualización:</strong> <?= date('d/m/Y H:i', strtotime($viewLicense['updated_at'])) ?></p>
                    <p><strong>Última Sincronización:</strong> <?= date('d/m/Y H:i', strtotime($viewLicense['last_synced_at'])) ?></p>
                    <p><strong>Estado Sync:</strong> <code><?= $viewLicense['sync_status'] ?></code></p>
                </div>
            </div>
            
            <hr>
            
            <!-- INFORMACIÓN DE SUSCRIPCIÓN -->
            <h4>💳 Información de Suscripción</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div>
                    <p><strong>Producto WooCommerce:</strong> <?= $viewLicense['woo_product_name'] ?: 'No disponible' ?></p>
                    <p><strong>Precio:</strong> 
                        <?php if ($viewLicense['subscription_price']): ?>
                            <span style="font-size: 18px; color: #28a745; font-weight: bold;">
                                <?= number_format($viewLicense['subscription_price'], 2) ?> <?= $viewLicense['currency'] ?>
                            </span>
                        <?php else: ?>
                            No disponible
                        <?php endif; ?>
                    </p>
                    <p><strong>Ciclo de Facturación:</strong> <?= $viewLicense['billing_cycle_text'] ?: 'No disponible' ?></p>
                </div>
                <div>
                    <p><strong>Fecha de Compra:</strong> 
                        <?= $viewLicense['order_date'] ? date('d/m/Y H:i', strtotime($viewLicense['order_date'])) : 'No disponible' ?>
                    </p>
                    <p><strong>Método de Pago:</strong> <?= $viewLicense['payment_method'] ?: 'No disponible' ?></p>
                </div>
            </div>
            
            <hr>
            
            <!-- USO DE TOKENS -->
            <h4>📊 Uso de Tokens</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div>
                    <p><strong>Límite Mensual:</strong> <?= number_format($viewLicense['tokens_limit']) ?> tokens</p>
                    <p><strong>Usados este Periodo:</strong> <?= number_format($viewLicense['tokens_used_this_period']) ?> tokens</p>
                    <p><strong>Disponibles:</strong> 
                        <span style="color: <?= ($viewLicense['tokens_limit'] - $viewLicense['tokens_used_this_period']) < 10000 ? '#dc3545' : '#28a745' ?>; font-weight: bold;">
                            <?= number_format($viewLicense['tokens_limit'] - $viewLicense['tokens_used_this_period']) ?> tokens
                        </span>
                    </p>
                    <div style="background: #eee; height: 30px; border-radius: 5px; overflow: hidden; margin-top: 10px;">
                        <?php 
                        $percent = ($viewLicense['tokens_used_this_period'] / $viewLicense['tokens_limit']) * 100;
                        $color = $percent > 90 ? '#dc3545' : ($percent > 70 ? '#ffc107' : '#28a745');
                        ?>
                        <div style="background: <?= $color ?>; width: <?= min($percent, 100) ?>%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                            <?= round($percent, 1) ?>%
                        </div>
                    </div>
                </div>
                <div>
                    <p><strong>Inicio del Periodo:</strong> <?= date('d/m/Y H:i', strtotime($viewLicense['period_starts_at'])) ?></p>
                    <p><strong>Fin del Periodo:</strong> <?= date('d/m/Y H:i', strtotime($viewLicense['period_ends_at'])) ?></p>
                    <p><strong>Días Restantes:</strong> 
                        <?php
                        $days = ceil((strtotime($viewLicense['period_ends_at']) - time()) / 86400);
                        if ($days > 0) {
                            echo "<span style='color: " . ($days < 7 ? '#dc3545' : '#28a745') . "; font-weight: bold;'>{$days} días</span>";
                        } else {
                            echo "<span style='color: #dc3545; font-weight: bold;'>EXPIRADO</span>";
                        }
                        ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <h4>⚡ Acciones Rápidas</h4>
            <div class="actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset_tokens">
                    <input type="hidden" name="license_id" value="<?= $viewLicense['id'] ?>">
                    <button type="submit" class="btn btn-warning" 
                            onclick="return confirm('¿Resetear tokens a 0?')">
                        🔄 Resetear Tokens
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="<?= $viewLicense['status'] === 'active' ? 'suspend' : 'activate' ?>">
                    <input type="hidden" name="license_id" value="<?= $viewLicense['id'] ?>">
                    <button type="submit" class="btn btn-<?= $viewLicense['status'] === 'active' ? 'danger' : 'success' ?>">
                        <?= $viewLicense['status'] === 'active' ? '⏸ Suspender' : '▶️ Activar' ?>
                    </button>
                </form>
                
                <form method="POST" style="display: inline;" onsubmit="return confirm('¿ELIMINAR esta licencia permanentemente?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="license_id" value="<?= $viewLicense['id'] ?>">
                    <button type="submit" class="btn btn-danger">
                        🗑️ Eliminar Licencia
                    </button>
                </form>
            </div>
        </div>
    </div>

<?php elseif (isset($_GET['new']) || isset($_GET['edit'])): ?>
    <!-- FORMULARIO CREAR/EDITAR -->
    <div class="card">
        <div class="card-header">
            <h3><?= isset($_GET['edit']) ? 'Editar Licencia' : 'Nueva Licencia' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="<?= isset($_GET['edit']) ? 'update' : 'create' ?>">
                <?php if (isset($_GET['edit'])): ?>
                    <input type="hidden" name="id" value="<?= $editLicense['id'] ?>">
                <?php endif; ?>
                
                <?php if (!isset($_GET['edit'])): ?>
                    <div class="form-group">
                        <label>License Key *</label>
                        <input type="text" name="license_key" class="form-control" 
                               value="<?= $editLicense['license_key'] ?? '' ?>" required
                               placeholder="DEMO-PRO-2025-ABCD1234">
                        <small>Debe ser único. Sugerido: TIPO-PLAN-AÑO-RANDOM</small>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>License Key</label>
                        <input type="text" class="form-control" 
                               value="<?= $editLicense['license_key'] ?>" readonly>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Email Usuario *</label>
                    <input type="email" name="user_email" class="form-control" 
                           value="<?= $editLicense['user_email'] ?? '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Plan *</label>
                    <select name="plan_id" class="form-control" required>
                        <option value="">Seleccionar plan</option>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= $plan['id'] ?>" 
                                    <?= ($editLicense['plan_id'] ?? '') === $plan['id'] ? 'selected' : '' ?>>
                                <?= $plan['name'] ?> (<?= number_format($plan['tokens_per_month']) ?> tokens/mes)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Estado</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= ($editLicense['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activa</option>
                        <option value="suspended" <?= ($editLicense['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspendida</option>
                        <option value="expired" <?= ($editLicense['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expirada</option>
                        <option value="cancelled" <?= ($editLicense['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Dominio</label>
                    <input type="text" name="domain" class="form-control" 
                           value="<?= $editLicense['domain'] ?? '' ?>"
                           placeholder="www.ejemplo.com">
                </div>
                
                <div class="form-group">
                    <label>WooCommerce Subscription ID</label>
                    <input type="number" name="woo_subscription_id" class="form-control" 
                           value="<?= $editLicense['woo_subscription_id'] ?? '' ?>"
                           placeholder="12345">
                    <small>Opcional. ID de la suscripción en WooCommerce</small>
                </div>
                
                <div class="form-group">
                    <label>WooCommerce User ID</label>
                    <input type="number" name="woo_user_id" class="form-control" 
                           value="<?= $editLicense['woo_user_id'] ?? '' ?>"
                           placeholder="67890">
                    <small>Opcional. ID del usuario en WooCommerce</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <?= isset($_GET['edit']) ? 'Actualizar' : 'Crear' ?> Licencia
                    </button>
                    <a href="?module=licenses" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- LISTA DE LICENCIAS -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" style="display: flex; gap: 10px; align-items: end;">
                <input type="hidden" name="module" value="licenses">
                
                <div class="form-group" style="margin: 0; flex: 1;">
                    <label>Buscar</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?= $_GET['search'] ?? '' ?>"
                           placeholder="License key o email">
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label>Estado</label>
                    <select name="status" class="form-control">
                        <option value="all">Todos</option>
                        <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Activas</option>
                        <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspendidas</option>
                        <option value="expired" <?= ($_GET['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expiradas</option>
                        <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Canceladas</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label>Plan</label>
                    <select name="plan" class="form-control">
                        <option value="all">Todos</option>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= $plan['id'] ?>" 
                                    <?= ($_GET['plan'] ?? '') === $plan['id'] ? 'selected' : '' ?>>
                                <?= $plan['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="?module=licenses" class="btn btn-secondary">Limpiar</a>
            </form>
        </div>
    </div>
    
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>License Key</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Order ID</th>
                    <th>Estado</th>
                    <th>Tokens</th>
                    <th>Vence</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licenses)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            No hay licencias que mostrar
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($licenses as $license): ?>
                        <tr>
                            <td><code><?= $license['license_key'] ?></code></td>
                            <td><?= htmlspecialchars($license['user_email']) ?></td>
                            <td><?= $license['plan_name'] ?></td>
                            <td>
                                <?php if ($license['last_order_id']): ?>
                                    <a href="https://bocetosmarketing.com/wp-admin/post.php?post=<?= $license['last_order_id'] ?>&action=edit"
                                       target="_blank"
                                       style="color: #3498db; text-decoration: none; font-weight: 500;"
                                       title="Ver pedido en WooCommerce">
                                        #<?= $license['last_order_id'] ?> 🔗
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $license['status'] ?>">
                                    <?= ucfirst($license['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?= number_format($license['tokens_used_this_period']) ?> / 
                                <?= number_format($license['tokens_limit']) ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($license['period_ends_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?module=licenses&view=<?= $license['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                                    <a href="?module=licenses&edit=<?= $license['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="<?= $license['status'] === 'active' ? 'suspend' : 'activate' ?>">
                                        <input type="hidden" name="license_id" value="<?= $license['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $license['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" 
                                                onclick="return confirm('¿Confirmar?')">
                                            <?= $license['status'] === 'active' ? 'Suspender' : 'Activar' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿ELIMINAR esta licencia?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="license_id" value="<?= $license['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
