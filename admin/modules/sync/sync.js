/**
 * Sync Module JS
 */

// Cargar datos iniciales
loadStats();
loadSyncs();

// Cargar estadísticas
async function loadStats() {
    const data = await apiRequest('modules/sync/ajax.php?action=stats');
    
    if (data.success) {
        const stats = data.data;
        document.getElementById('last-sync').textContent = stats.last_sync ? formatDate(stats.last_sync) : 'Never';
        document.getElementById('webhooks-today').textContent = formatNumber(stats.webhooks_today);
        document.getElementById('discrepancies').textContent = formatNumber(stats.discrepancies);
        document.getElementById('failed-syncs').textContent = formatNumber(stats.failed_syncs);
        
        // Mostrar alertas si hay problemas
        if (stats.failed_syncs > 5) {
            showSyncAlert('warning', `⚠️ ${stats.failed_syncs} failed syncs in the last 24 hours`);
        }
        
        if (stats.discrepancies > 0) {
            showSyncAlert('info', `ℹ️ ${stats.discrepancies} discrepancies detected`);
        }
    }
}

// Cargar tabla de syncs
async function loadSyncs() {
    const data = await apiRequest('modules/sync/ajax.php?action=recent_syncs');
    
    if (data.success && data.data.length > 0) {
        let html = `
            <table>
                <thead>
                    <tr>
                        <th>License Key</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Changes</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.data.forEach(sync => {
            const statusClass = sync.status === 'success' ? 'status-active' : 
                               sync.status === 'failed' ? 'status-expired' : 'status-suspended';
            
            let changes = '-';
            if (sync.changes_detected) {
                try {
                    const changesObj = JSON.parse(sync.changes_detected);
                    changes = Object.keys(changesObj).join(', ');
                } catch (e) {
                    changes = 'Error parsing';
                }
            }
            
            html += `
                <tr>
                    <td>${sync.license_key || 'Unknown'}</td>
                    <td>${sync.sync_type}</td>
                    <td><span class="status ${statusClass}">${sync.status}</span></td>
                    <td>${changes}</td>
                    <td>${formatDate(sync.created_at)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        document.getElementById('sync-table').innerHTML = html;
    } else {
        document.getElementById('sync-table').innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">No synchronizations found</p>';
    }
}

// Sincronizar todas las licencias
async function syncAll() {
    const resultDiv = document.getElementById('action-result');
    resultDiv.innerHTML = '<div class="alert alert-info">⏳ Synchronizing all licenses... This may take a while.</div>';
    
    const data = await apiRequest('modules/sync/ajax.php?action=sync_all', {
        method: 'POST'
    });
    
    if (data.success) {
        resultDiv.innerHTML = `
            <div class="alert alert-success">
                ✅ Synchronization completed!<br>
                Success: ${data.data.success}<br>
                Failed: ${data.data.failed}<br>
                No changes: ${data.data.no_changes}
            </div>
        `;
        loadStats();
        loadSyncs();
    } else {
        resultDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${data.error || 'Unknown error'}</div>`;
    }
}

// Test conexión WooCommerce
async function testWooCommerce() {
    const resultDiv = document.getElementById('action-result');
    resultDiv.innerHTML = '<div class="alert alert-info">Testing WooCommerce connection...</div>';
    
    const data = await apiRequest('modules/sync/ajax.php?action=test_woocommerce');
    
    if (data.success && data.data.success) {
        resultDiv.innerHTML = `<div class="alert alert-success">✅ ${data.data.message}</div>`;
    } else {
        resultDiv.innerHTML = `<div class="alert alert-danger">❌ Connection failed: ${data.data.message || 'Unknown error'}</div>`;
    }
}

// Mostrar alerta en contenedor
function showSyncAlert(type, message) {
    const container = document.getElementById('alerts-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = message;
    container.appendChild(alert);
}
