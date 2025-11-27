/**
 * Dashboard Module JS
 */

(async function() {
    // Cargar estadísticas
    const stats = await apiRequest('modules/dashboard/stats.php');
    
    if (stats.success) {
        document.getElementById('stat-active-licenses').textContent = formatNumber(stats.data.active_licenses);
        document.getElementById('stat-total-licenses').textContent = formatNumber(stats.data.total_licenses);
        document.getElementById('stat-tokens-today').textContent = formatNumber(stats.data.tokens_today);
        document.getElementById('stat-webhooks-24h').textContent = formatNumber(stats.data.webhooks_24h);
    }
    
    // Cargar syncs recientes
    const syncs = await apiRequest('modules/dashboard/stats.php?action=recent_syncs');
    
    if (syncs.success && syncs.data.length > 0) {
        let html = '<table><thead><tr><th>License</th><th>Type</th><th>Status</th><th>Date</th></tr></thead><tbody>';
        
        syncs.data.forEach(sync => {
            const statusClass = sync.status === 'success' ? 'status-active' : 'status-expired';
            html += `
                <tr>
                    <td>${sync.license_key || 'N/A'}</td>
                    <td>${sync.sync_type}</td>
                    <td><span class="status ${statusClass}">${sync.status}</span></td>
                    <td>${formatDate(sync.created_at)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        document.getElementById('recent-syncs').innerHTML = html;
    } else {
        document.getElementById('recent-syncs').innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">No recent synchronizations</p>';
    }
    
    // Cargar licencias por expirar
    const expiring = await apiRequest('modules/dashboard/stats.php?action=expiring_licenses');
    
    if (expiring.success && expiring.data.length > 0) {
        let html = '<table><thead><tr><th>License Key</th><th>Plan</th><th>Expires At</th><th>Days Left</th></tr></thead><tbody>';
        
        expiring.data.forEach(license => {
            const expiresAt = new Date(license.period_ends_at);
            const daysLeft = Math.ceil((expiresAt - new Date()) / (1000 * 60 * 60 * 24));
            const daysClass = daysLeft <= 3 ? 'status-expired' : 'status-suspended';
            
            html += `
                <tr>
                    <td>${license.license_key}</td>
                    <td>${license.plan_id}</td>
                    <td>${formatDate(license.period_ends_at)}</td>
                    <td><span class="status ${daysClass}">${daysLeft} days</span></td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        document.getElementById('expiring-licenses').innerHTML = html;
    } else {
        document.getElementById('expiring-licenses').innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">No licenses expiring soon</p>';
    }
})();
