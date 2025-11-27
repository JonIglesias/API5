<?php
// Cargar estado del cron
require_once API_BASE_DIR . '/services/AutoSyncService.php';
$cronStatus = AutoSyncService::getCronStatus();

$syncLogs = $db->query("
    SELECT sl.*, l.license_key
    FROM " . DB_PREFIX . "sync_logs sl
    LEFT JOIN " . DB_PREFIX . "licenses l ON sl.license_id = l.id
    ORDER BY sl.created_at DESC
    LIMIT 50
");

$result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "sync_logs WHERE DATE(created_at) = CURDATE()");
$today = $result[0]['total'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "sync_logs WHERE status = 'success' AND DATE(created_at) = CURDATE()");
$success = $result[0]['total'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "sync_logs WHERE status = 'failed' AND DATE(created_at) = CURDATE()");
$failed = $result[0]['total'] ?? 0;
?>
<style>
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.stat-card .label { font-size: 14px; color: #6c757d; }
.stat-card .value { font-size: 32px; font-weight: 700; margin-top: 10px; }
.actions { margin-bottom: 20px; display: flex; gap: 10px; }
.btn { padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-primary { background: #007bff; color: white; }
.btn-success { background: #28a745; color: white; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.logs-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; }
th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; }
td { padding: 12px; border-bottom: 1px solid #dee2e6; }
.badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-failed { background: #f8d7da; color: #721c24; }
.info-box { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin-bottom: 20px; }

/* Estilos del indicador del cron */
.cron-status-panel {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
}
.cron-indicator {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}
.cron-indicator.ok { background: #d4edda; color: #155724; }
.cron-indicator.warning { background: #fff3cd; color: #856404; }
.cron-indicator.error { background: #f8d7da; color: #721c24; }
.cron-indicator.never { background: #e2e3e5; color: #383d41; }
.cron-info { flex: 1; }
.cron-info h4 { margin: 0 0 5px 0; font-size: 16px; }
.cron-info p { margin: 0; color: #6c757d; font-size: 14px; }
.cron-results {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #6c757d;
}
.cron-results span { display: flex; align-items: center; gap: 4px; }
</style>

<!-- Panel de Estado del Cron -->
<div class="cron-status-panel">
    <div class="cron-indicator <?= $cronStatus['status'] ?>">
        <?php
        switch ($cronStatus['status']) {
            case 'ok': echo '✓'; break;
            case 'warning': echo '⚠'; break;
            case 'error': echo '✗'; break;
            default: echo '?'; break;
        }
        ?>
    </div>
    <div class="cron-info">
        <h4>Estado del Cron Auto-Sync</h4>
        <p><?= $cronStatus['message'] ?></p>
        <?php if ($cronStatus['last_run']): ?>
            <p style="font-size: 12px; margin-top: 5px;">
                Última ejecución: <?= date('d/m/Y H:i:s', strtotime($cronStatus['last_run'])) ?>
                (hace <?= $cronStatus['last_run_relative'] ?>)
            </p>
        <?php endif; ?>
    </div>
    <?php if (!empty($cronStatus['results'])): ?>
        <div class="cron-results">
            <span title="Licencias creadas">➕ <?= $cronStatus['results']['created'] ?></span>
            <span title="Licencias actualizadas">🔄 <?= $cronStatus['results']['updated'] ?></span>
            <span title="Sin cambios">➖ <?= $cronStatus['results']['unchanged'] ?></span>
            <?php if ($cronStatus['results']['errors'] > 0): ?>
                <span title="Errores" style="color: #dc3545;">❌ <?= $cronStatus['results']['errors'] ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="info-box">
    <strong>💡 Información:</strong> La sincronización mantiene actualizadas las licencias con los pedidos de WooCommerce (Flexible Subscriptions).
    El cron se ejecuta cada 5 minutos y sincroniza pedidos de las últimas 2 horas. Los logs se limpian automáticamente (BD: 30 días, archivos: 7 días o >10MB).
</div>

<div class="stats-row">
    <div class="stat-card">
        <div class="label">Syncs Hoy</div>
        <div class="value"><?= $today ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Exitosos</div>
        <div class="value" style="color: #28a745;"><?= $success ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Fallidos</div>
        <div class="value" style="color: #dc3545;"><?= $failed ?></div>
    </div>
</div>

<div class="actions">
    <button onclick="testConnection()" class="btn btn-success" id="testBtn">🔍 Test WooCommerce</button>
    <button onclick="forceSyncAll()" class="btn btn-primary" id="syncBtn">🔄 Sync Ordenes</button>
    <button onclick="autoSyncRecent()" class="btn btn-primary" id="autoSyncBtn" style="background: #17a2b8;">🔄 Auto-Sync (2h)</button>
    <button onclick="autoSyncFull()" class="btn btn-warning" id="autoSyncFullBtn">🔄 Auto-Sync Completo</button>
    <button onclick="cleanLogs()" class="btn" id="cleanLogsBtn" style="background: #6c757d; color: white;">🗑️ Limpiar Logs</button>
</div>

<!-- Info sobre Auto-Sync -->
<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
    <strong>🚀 Auto-Sync de Pedidos (Flexible Subscriptions):</strong><br>
    El Auto-Sync obtiene los pedidos completados/procesados de WooCommerce y crea/actualiza las licencias automáticamente.
    <ul style="margin: 10px 0 0 20px; padding: 0;">
        <li><strong>2h:</strong> Solo sincroniza pedidos modificados en las últimas 2 horas (rápido y eficiente)</li>
        <li><strong>Completo:</strong> Sincroniza TODOS los pedidos (usar solo ocasionalmente)</li>
    </ul>
</div>

<!-- Cron Job Info -->
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
    <strong>⏰ Configurar Sincronización Automática (Cron):</strong><br>
    Para que las licencias se generen rápido tras un pedido, añade esta línea al crontab de cPanel:<br>
    <code style="display: block; background: #f8f9fa; padding: 10px; margin-top: 10px; border-radius: 4px; font-size: 12px; word-break: break-all;">
        */5 * * * * curl -s "https://tudominio.com/api_claude_5/cron/auto-sync" >> <?= API_BASE_DIR ?>/logs/cron.log 2>&1
    </code>
    <small style="color: #6c757d;">Esto ejecutará el auto-sync cada 5 minutos. Limpieza automática: BD >30 días, archivos >7 días o >10MB</small>
</div>

<div class="logs-table">
    <h3 style="padding: 20px; margin: 0; border-bottom: 1px solid #dee2e6;">Últimas Sincronizaciones</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Licencia</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Cambios</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($syncLogs)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 40px; color: #6c757d;">
                    No hay logs de sincronización aún
                </td></tr>
            <?php else: ?>
                <?php foreach ($syncLogs as $log): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                        <td>
                            <?php if (empty($log['license_key'])): ?>
                                <span style="color: #6c757d;">— Resumen —</span>
                            <?php else: ?>
                                <code><?= htmlspecialchars($log['license_key']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($log['sync_type']) ?></td>
                        <td><span class="badge badge-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></span></td>
                        <td><?= !empty($log['details']) ? htmlspecialchars($log['details']) : (!empty($log['changes_detected']) ? htmlspecialchars($log['changes_detected']) : '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
async function forceSyncAll() {
    if (!confirm('¿Sincronizar todas las licencias activas con WooCommerce?')) return;
    
    const btn = document.getElementById('syncBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Sincronizando...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'sync_all');
        
        const response = await fetch('modules/sync/ajax.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        const result = JSON.parse(text);
        
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch(e) {
        alert('❌ Error: ' + e.message);
        console.error('Sync error:', e);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function testConnection() {
    const btn = document.getElementById('testBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Probando conexión...';

    try {
        const formData = new FormData();
        formData.append('action', 'test_connection');

        const response = await fetch('modules/sync/ajax.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Raw response:', text);

        const result = JSON.parse(text);

        if (result.success) {
            alert(result.message);
        } else {
            alert('❌ ' + result.error);
        }
    } catch(e) {
        alert('❌ Error: ' + e.message + '\nRevisa la consola del navegador (F12)');
        console.error('Test error:', e);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Auto-Sync Reciente (últimas 2h)
async function autoSyncRecent() {
    if (!confirm('¿Sincronizar suscripciones de las últimas 2 horas?')) return;

    const btn = document.getElementById('autoSyncBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Sincronizando...';

    try {
        const formData = new FormData();
        formData.append('action', 'auto_sync');
        formData.append('sync_type', 'recent');

        const response = await fetch('modules/sync/ajax.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Raw response:', text);

        const result = JSON.parse(text);

        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch(e) {
        alert('❌ Error: ' + e.message);
        console.error('Auto-sync error:', e);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Auto-Sync Completo (todos los pedidos)
async function autoSyncFull() {
    if (!confirm('⚠️ Esto sincronizará TODOS los pedidos de WooCommerce.\nPuede tardar varios minutos. ¿Continuar?')) return;

    const btn = document.getElementById('autoSyncFullBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Sincronizando todo...';

    try {
        const formData = new FormData();
        formData.append('action', 'auto_sync');
        formData.append('sync_type', 'full');

        const response = await fetch('modules/sync/ajax.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Raw response:', text);

        const result = JSON.parse(text);

        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch(e) {
        alert('❌ Error: ' + e.message);
        console.error('Auto-sync full error:', e);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Limpiar todos los logs (BD y archivos)
async function cleanLogs() {
    if (!confirm('⚠️ Esto eliminará TODOS los logs:\n- Registros de sync_logs en la BD\n- Archivos .log (sync.log, api.log, etc.)\n\n¿Continuar?')) return;

    const btn = document.getElementById('cleanLogsBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Limpiando...';

    try {
        const formData = new FormData();
        formData.append('action', 'clean_logs');

        const response = await fetch('modules/sync/ajax.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Raw response:', text);

        const result = JSON.parse(text);

        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch(e) {
        alert('❌ Error: ' + e.message);
        console.error('Clean logs error:', e);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}
</script>
