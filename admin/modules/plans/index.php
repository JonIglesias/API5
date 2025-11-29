<?php
if (!defined('API_ACCESS')) die('Access denied');

$db = Database::getInstance();

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $tokens_per_month = intval($_POST['tokens_per_month'] ?? 0);
        $woo_product_id = intval($_POST['woo_product_id'] ?? 0);
        $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
        $active = isset($_POST['active']) ? 1 : 0;
        
        // NUEVOS CAMPOS: TIMING
        $post_generation_delay = intval($_POST['post_generation_delay'] ?? 60);
        $api_timeout = intval($_POST['api_timeout'] ?? 120);
        $max_retries = intval($_POST['max_retries'] ?? 3);
        
        // NUEVOS CAMPOS: LIMITS
        $requests_per_day = intval($_POST['requests_per_day'] ?? -1);
        $requests_per_month = intval($_POST['requests_per_month'] ?? -1);
        $max_words_per_request = intval($_POST['max_words_per_request'] ?? 2000);
        $max_campaigns = intval($_POST['max_campaigns'] ?? -1);
        $max_posts_per_campaign = intval($_POST['max_posts_per_campaign'] ?? -1);
        
        // PRECIO
        $price = floatval($_POST['price'] ?? 0);
        $currency = $_POST['currency'] ?? 'EUR';
        
        try {
            $data = [
                'name' => $name,
                'tokens_per_month' => $tokens_per_month,
                'woo_product_id' => $woo_product_id,
                'billing_cycle' => $billing_cycle,
                'is_active' => $active,
                'post_generation_delay' => $post_generation_delay,
                'api_timeout' => $api_timeout,
                'max_retries' => $max_retries,
                'requests_per_day' => $requests_per_day,
                'requests_per_month' => $requests_per_month,
                'max_words_per_request' => $max_words_per_request,
                'max_campaigns' => $max_campaigns,
                'max_posts_per_campaign' => $max_posts_per_campaign,
                'price' => $price,
                'currency' => $currency
            ];
            
            if ($action === 'create') {
                $data['id'] = $id;
                $db->insert('plans', $data);
                $success = true;
                $message = "Plan creado correctamente";
            } else {
                $stmt = $db->prepare("
                    UPDATE " . DB_PREFIX . "plans
                    SET name=?, tokens_per_month=?, woo_product_id=?, billing_cycle=?, is_active=?,
                        post_generation_delay=?, api_timeout=?, max_retries=?,
                        requests_per_day=?, requests_per_month=?, max_words_per_request=?, max_campaigns=?,
                        max_posts_per_campaign=?, price=?, currency=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $name, $tokens_per_month, $woo_product_id, $billing_cycle, $active,
                    $post_generation_delay, $api_timeout, $max_retries,
                    $requests_per_day, $requests_per_month, $max_words_per_request, $max_campaigns,
                    $max_posts_per_campaign, $price, $currency,
                    $id
                ]);

                // ⭐ ACTUALIZAR AUTOMÁTICAMENTE todas las licencias que usan este plan
                $db->query("
                    UPDATE " . DB_PREFIX . "licenses
                    SET tokens_limit = ?,
                        updated_at = NOW()
                    WHERE plan_id = ?
                    AND status = 'active'
                ", [$tokens_per_month, $id]);

                // Contar licencias actualizadas
                $countResult = $db->fetchOne("
                    SELECT COUNT(*) as count
                    FROM " . DB_PREFIX . "licenses
                    WHERE plan_id = ?
                    AND status = 'active'
                ", [$id]);

                $licenseCount = $countResult['count'] ?? 0;

                $success = true;
                $message = "Plan actualizado correctamente. {$licenseCount} licencias activas actualizadas con el nuevo límite de tokens.";
            }
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'toggle_active') {
        $id = $_POST['plan_id'] ?? '';
        try {
            $stmt = $db->prepare("UPDATE " . DB_PREFIX . "plans SET is_active = IF(is_active = 1, 0, 1) WHERE id=?");
            $stmt->execute([$id]);
            $success = true;
            $message = "Estado del plan actualizado";
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['plan_id'] ?? '';
        try {
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses WHERE plan_id=?", [$id]);
            
            if ($count['count'] > 0) {
                $message = "No se puede eliminar: hay {$count['count']} licencias usando este plan";
                $success = false;
            } else {
                $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "plans WHERE id=?");
                $stmt->execute([$id]);
                $success = true;
                $message = "Plan eliminado correctamente";
            }
        } catch (Exception $e) {
            $success = false;
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Obtener planes
$plans = $db->query("SELECT * FROM " . DB_PREFIX . "plans ORDER BY name");

// Si estamos editando
$editPlan = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $editPlan = $db->fetchOne("SELECT * FROM " . DB_PREFIX . "plans WHERE id=?", [$editId]);
}
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
.badge-success { background: #d4edda; color: #155724; }
.badge-secondary { background: #e2e3e5; color: #383d41; }
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
</style>

<div class="page-header">
    <h1>📦 Gestión de Planes</h1>
    <?php if (!isset($_GET['new']) && !isset($_GET['edit'])): ?>
        <a href="?module=plans&new=1" class="btn btn-primary">+ Nuevo Plan</a>
    <?php endif; ?>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['new']) || isset($_GET['edit'])): ?>
    <div class="card">
        <div class="card-header">
            <h3><?= isset($_GET['edit']) ? 'Editar Plan' : 'Nuevo Plan' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="<?= isset($_GET['edit']) ? 'update' : 'create' ?>">
                
                <!-- INFORMACIÓN BÁSICA -->
                <h4 style="margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #eee;">📋 Información Básica</h4>
                
                <div class="form-group">
                    <label>ID del Plan *</label>
                    <input type="text" name="id" class="form-control" 
                           value="<?= $editPlan['id'] ?? '' ?>"
                           <?= isset($_GET['edit']) ? 'readonly' : 'required' ?>
                           placeholder="basic, pro, enterprise">
                    <small>Identificador único (letras minúsculas, sin espacios)</small>
                </div>
                
                <div class="form-group">
                    <label>Nombre del Plan *</label>
                    <input type="text" name="name" class="form-control" 
                           value="<?= $editPlan['name'] ?? '' ?>" required
                           placeholder="Plan Básico">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Precio</label>
                        <input type="number" step="0.01" name="price" class="form-control" 
                               value="<?= $editPlan['price'] ?? 0 ?>"
                               placeholder="9.99">
                    </div>
                    
                    <div class="form-group">
                        <label>Moneda</label>
                        <select name="currency" class="form-control">
                            <option value="EUR" <?= ($editPlan['currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                            <option value="USD" <?= ($editPlan['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                        </select>
                    </div>
                </div>
                
                <!-- TOKENS Y LÍMITES -->
                <h4 style="margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #eee;">🎯 Tokens y Límites</h4>
                
                <div class="form-group">
                    <label>Tokens por Mes *</label>
                    <input type="number" name="tokens_per_month" class="form-control" 
                           value="<?= $editPlan['tokens_per_month'] ?? '' ?>" required
                           placeholder="100000">
                    <small>Cantidad de tokens OpenAI disponibles al mes</small>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Requests por Día</label>
                        <input type="number" name="requests_per_day" class="form-control" 
                               value="<?= $editPlan['requests_per_day'] ?? -1 ?>"
                               placeholder="-1">
                        <small>-1 = ilimitado</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Requests por Mes</label>
                        <input type="number" name="requests_per_month" class="form-control" 
                               value="<?= $editPlan['requests_per_month'] ?? -1 ?>"
                               placeholder="-1">
                        <small>-1 = ilimitado</small>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Máximo Palabras por Request</label>
                        <input type="number" name="max_words_per_request" class="form-control"
                               value="<?= $editPlan['max_words_per_request'] ?? 2000 ?>"
                               placeholder="2000">
                    </div>

                    <div class="form-group">
                        <label>Máximo de Campañas</label>
                        <input type="number" name="max_campaigns" class="form-control"
                               value="<?= $editPlan['max_campaigns'] ?? -1 ?>"
                               placeholder="-1">
                        <small>-1 = ilimitado</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>📊 Máximo Posts por Campaña</label>
                    <input type="number" name="max_posts_per_campaign" class="form-control"
                           value="<?= $editPlan['max_posts_per_campaign'] ?? -1 ?>"
                           placeholder="-1">
                    <small><strong>NUEVO:</strong> Limita cuántos posts puede configurar el usuario en cada campaña. -1 = ilimitado. Ej: free=10, basic=50, pro=100, enterprise=-1</small>
                </div>
                
                <!-- TIMING (CRÍTICO PARA PLUGIN) -->
                <h4 style="margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #eee;">⏱️ Timing (Control de Velocidad del Plugin)</h4>
                
                <div class="form-group">
                    <label>⚠️ Delay entre Posts (segundos) *</label>
                    <input type="number" name="post_generation_delay" class="form-control" 
                           value="<?= $editPlan['post_generation_delay'] ?? 60 ?>" required
                           placeholder="60">
                    <small><strong>IMPORTANTE:</strong> Tiempo que el plugin espera entre generación de posts en cola. Recomendado: 60-120 seg para evitar sobrecarga</small>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Timeout API OpenAI (segundos)</label>
                        <input type="number" name="api_timeout" class="form-control" 
                               value="<?= $editPlan['api_timeout'] ?? 120 ?>"
                               placeholder="120">
                        <small>Tiempo máximo de espera para respuesta de OpenAI</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Máximo de Reintentos</label>
                        <input type="number" name="max_retries" class="form-control" 
                               value="<?= $editPlan['max_retries'] ?? 3 ?>"
                               placeholder="3">
                        <small>Reintentos en caso de error en OpenAI</small>
                    </div>
                </div>
                
                <!-- WOOCOMMERCE -->
                <h4 style="margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #eee;">🛒 Integración WooCommerce</h4>
                
                <div class="form-group">
                    <label>ID Producto WooCommerce *</label>
                    <input type="number" name="woo_product_id" class="form-control" 
                           value="<?= $editPlan['woo_product_id'] ?? '' ?>" required
                           placeholder="1234">
                    <small>👉 <strong>MAPEO:</strong> Ve a WooCommerce > Productos, edita tu producto/suscripción y copia su ID (lo ves en la URL: post=<strong>ID</strong>)</small>
                </div>
                
                <div class="form-group">
                    <label>Ciclo de Facturación</label>
                    <select name="billing_cycle" class="form-control">
                        <option value="monthly" <?= ($editPlan['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Mensual</option>
                        <option value="yearly" <?= ($editPlan['billing_cycle'] ?? '') === 'yearly' ? 'selected' : '' ?>>Anual</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active"
                               <?= ($editPlan['is_active'] ?? 1) ? 'checked' : '' ?>>
                        Plan activo
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <?= isset($_GET['edit']) ? 'Actualizar' : 'Crear' ?> Plan
                    </button>
                    <a href="?module=plans" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tokens/Mes</th>
                    <th>Max Posts/Camp</th>
                    <th>Delay Posts</th>
                    <th>Timeout API</th>
                    <th>WooCommerce</th>
                    <th>Estado</th>
                    <th>Licencias</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                            No hay planes creados. Haz clic en "+ Nuevo Plan" para crear uno.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                        $count = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses WHERE plan_id=?", [$plan['id']]);
                        $licenseCount = $count['count'];
                        ?>
                        <tr>
                            <td><code><?= $plan['id'] ?></code></td>
                            <td><strong><?= htmlspecialchars($plan['name']) ?></strong></td>
                            <td><?= number_format($plan['tokens_per_month']) ?></td>
                            <td>
                                <?php
                                $maxPosts = $plan['max_posts_per_campaign'] ?? -1;
                                echo $maxPosts === -1 ? '∞' : $maxPosts;
                                ?>
                            </td>
                            <td><?= ($plan['post_generation_delay'] ?? 60) ?> seg</td>
                            <td><?= ($plan['api_timeout'] ?? 120) ?> seg</td>
                            <td>#<?= $plan['woo_product_id'] ?></td>
                            <td>
                                <span class="badge badge-<?= $plan['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $plan['is_active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td><?= $licenseCount ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?module=plans&edit=<?= $plan['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <?= $plan['is_active'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirm('¿Eliminar este plan?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                <?= $licenseCount > 0 ? 'disabled title="Hay licencias usando este plan"' : '' ?>>
                                            Eliminar
                                        </button>
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
