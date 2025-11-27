<?php
if (!defined('API_ACCESS')) die('Access denied');
?>

<style>
.license-stats-container {
    max-width: 1400px;
}

.stats-search-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.search-header {
    margin-bottom: 20px;
}

.search-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1F2937;
    margin-bottom: 8px;
}

.search-header p {
    font-size: 14px;
    color: #6B7280;
}

.search-input-wrapper {
    position: relative;
    margin-bottom: 20px;
}

.search-input {
    width: 100%;
    padding: 14px 20px;
    padding-left: 45px;
    border: 2px solid #D1D5DB;
    border-radius: 10px;
    font-size: 15px;
    font-family: inherit;
    transition: all 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    color: #9CA3AF;
}

.licenses-list-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    max-height: 500px;
    overflow-y: auto;
}

.licenses-list-header {
    padding: 16px 20px;
    border-bottom: 2px solid #E5E7EB;
    background: #F9FAFB;
    position: sticky;
    top: 0;
    z-index: 10;
}

.licenses-list-header h4 {
    font-size: 14px;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.license-item {
    padding: 16px 20px;
    border-bottom: 1px solid #F3F4F6;
    cursor: pointer;
    transition: all 0.2s;
    display: grid;
    grid-template-columns: 2fr 2fr 1fr 1fr;
    gap: 12px;
    align-items: center;
}

.license-item:hover {
    background: #F9FAFB;
}

.license-item:active {
    background: #EEF2FF;
}

.license-item.selected {
    background: #EEF2FF;
    border-left: 4px solid #667eea;
}

.license-key {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    font-weight: 600;
    color: #1F2937;
}

.license-email {
    font-size: 13px;
    color: #6B7280;
}

.license-plan {
    font-size: 12px;
    font-weight: 600;
    color: #667eea;
    text-transform: uppercase;
}

.license-status {
    text-align: right;
}

.date-filters-card {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    display: none;
}

.date-filters-card.active {
    display: block;
}

.date-filters {
    display: flex;
    gap: 16px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input {
    padding: 10px 14px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-refresh {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-refresh:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.stats-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stats-kpi-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #667eea;
}

.stats-kpi-card.tokens {
    border-left-color: #F59E0B;
}

.stats-kpi-card.cost {
    border-left-color: #EF4444;
}

.stats-kpi-label {
    font-size: 13px;
    color: #6B7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stats-kpi-value {
    font-size: 36px;
    font-weight: 700;
    color: #1F2937;
}

.stats-table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 24px;
    overflow: hidden;
}

.stats-table-header {
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stats-table-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1F2937;
}

.btn-reset {
    background: #EF4444;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-reset:hover {
    background: #DC2626;
}

.stats-table-body {
    padding: 24px;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
}

.stats-table thead th {
    text-align: left;
    padding: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #E5E7EB;
}

.stats-table tbody td {
    padding: 14px 12px;
    border-bottom: 1px solid #F3F4F6;
    font-size: 14px;
    color: #374151;
}

.stats-table tbody tr:hover {
    background: #F9FAFB;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9CA3AF;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    color: #9CA3AF;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #E5E7EB;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.license-info-card {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.license-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.license-info-label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 600;
    text-transform: uppercase;
}

.license-info-value {
    font-size: 16px;
    color: #1F2937;
    font-weight: 500;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #D1FAE5;
    color: #065F46;
}

.badge-danger {
    background: #FEE2E2;
    color: #991B1B;
}

/* Estilos para colas expandibles */
.queue-row {
    background: #f9fafb;
    border-left: 3px solid #667eea;
}

.queue-row:hover {
    background: #f3f4f6;
}

.toggle-icon {
    display: inline-block;
    margin-left: 8px;
    font-size: 12px;
    color: #667eea;
    transition: transform 0.2s;
}

.queue-details table {
    font-size: 14px;
}

.queue-details table th {
    font-weight: 600;
    color: #4b5563;
}

.queue-details table td {
    color: #6b7280;
}
</style>

<div class="license-stats-container">
    <h2 style="margin-bottom: 24px; font-size: 24px; color: #1F2937;">📊 Estadísticas por Licencia</h2>
    
    <!-- Buscador -->
    <div class="stats-search-card">
        <div class="search-header">
            <h3>Buscar Licencia</h3>
            <p>Escribe para filtrar por License Key, Email o Dominio</p>
        </div>
        
        <div class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input 
                type="text" 
                id="search-input" 
                class="search-input" 
                placeholder="Ej: DEMO-PRO-2025, usuario@ejemplo.com, ejemplo.com"
                autocomplete="off"
            >
        </div>
    </div>
    
    <!-- Lista de Licencias (SIEMPRE VISIBLE) -->
    <div class="licenses-list-card" id="licenses-list">
        <div class="licenses-list-header">
            <h4><span id="licenses-count">0</span> Licencias</h4>
        </div>
        <div id="licenses-list-body">
            <!-- Se llena dinámicamente -->
        </div>
    </div>
    
    <!-- Filtros de Fecha (solo se muestran cuando hay una licencia seleccionada) -->
    <div class="date-filters-card" id="date-filters">
        <div class="date-filters">
            <div class="form-group">
                <label>Desde</label>
                <input type="date" id="date-from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
            </div>
            
            <div class="form-group">
                <label>Hasta</label>
                <input type="date" id="date-to" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn-refresh" onclick="refreshStats()">🔄 Actualizar</button>
            </div>
        </div>
    </div>
    
    <!-- Contenedor de Estadísticas -->
    <div id="stats-content">
        <div class="empty-state">
            <div class="empty-state-icon">👆</div>
            <p>Selecciona una licencia de la lista para ver sus estadísticas</p>
        </div>
    </div>
</div>

<script>
let currentLicenseId = null;
let currentLicenseKey = null;
let allLicenses = [];
let searchTimeout = null;

// Mapeo de operation_type a endpoint y descripción
const operationTypeMap = {
    'queue': {
        endpoint: 'Agrupación',
        description: 'Cola generada (Title + Keywords de Imagen de Post)'
    },
    'title': {
        endpoint: '/generate/title',
        description: 'Títulos'
    },
    'keywords': {
        endpoint: '/generate/keywords',
        description: 'Generación de palabras clave'
    },
    'keywords_images': {
        endpoint: '/generate/keywords-images',
        description: 'Set de Keywords de Imagen de Post'
    },
    'keywords_seo': {
        endpoint: '/generate/keywords-seo',
        description: 'Set de Keywords SEO'
    },
    'content': {
        endpoint: '/generate/content',
        description: 'Generación de contenido completo'
    },
    'meta': {
        endpoint: '/generate/meta',
        description: 'Meta descripciones para SEO'
    },
    'company_description': {
        endpoint: '/generate/company-description',
        description: 'Descripción de Empresa'
    },
    'content_prompt': {
        endpoint: '/generate/content-prompt',
        description: 'Prompt de Contenido'
    },
    'title_prompt': {
        endpoint: '/generate/title-prompt',
        description: 'Prompt para Títulos'
    },
    'campaign_image_keywords': {
        endpoint: '/generate/campaign-image-keywords',
        description: 'Set de Keywords de Imagen de Campaña'
    }
};

function getOperationInfo(operationType) {
    return operationTypeMap[operationType] || {
        endpoint: '/generate/' + operationType,
        description: operationType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    };
}

// Cargar todas las licencias al inicio
loadAllLicenses();

// Búsqueda en tiempo real
document.getElementById('search-input').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    searchTimeout = setTimeout(() => {
        if (query.length === 0) {
            // Si no hay búsqueda, mostrar TODAS
            renderLicensesList(allLicenses);
        } else {
            // Si hay búsqueda, filtrar
            filterLicenses(query);
        }
    }, 300);
});

function loadAllLicenses() {
    const listContainer = document.getElementById('licenses-list');
    const listBody = document.getElementById('licenses-list-body');
    
    // Mostrar loading
    listContainer.style.display = 'block';
    listBody.innerHTML = `
        <div style="padding: 40px; text-align: center; color: #9CA3AF;">
            <div class="spinner" style="margin: 0 auto 16px;"></div>
            <p>Cargando licencias...</p>
        </div>
    `;
    
    fetch('modules/license-stats/ajax.php?action=get_all_licenses')
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            }
            return r.text();
        })
        .then(text => {
            console.log('Respuesta del servidor:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    allLicenses = data.licenses;
                    // Mostrar TODAS las licencias al inicio
                    renderLicensesList(allLicenses);
                } else {
                    listBody.innerHTML = `
                        <div style="padding: 40px; text-align: center; color: #EF4444;">
                            <p>❌ ${data.error || 'Error desconocido'}</p>
                        </div>
                    `;
                }
            } catch (e) {
                console.error('Error al parsear JSON:', e);
                console.error('Texto recibido:', text);
                listBody.innerHTML = `
                    <div style="padding: 40px; text-align: center; color: #EF4444;">
                        <p>❌ Error al parsear respuesta</p>
                        <pre style="text-align: left; background: #FEE2E2; padding: 10px; margin-top: 10px; font-size: 12px; overflow: auto;">${text.substring(0, 500)}</pre>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Error al cargar licencias:', err);
            listBody.innerHTML = `
                <div style="padding: 40px; text-align: center; color: #EF4444;">
                    <p>❌ Error: ${err.message}</p>
                </div>
            `;
        });
}

function filterLicenses(query) {
    const lowerQuery = query.toLowerCase();
    
    const filtered = allLicenses.filter(license => {
        return license.license_key.toLowerCase().includes(lowerQuery) ||
               (license.user_email && license.user_email.toLowerCase().includes(lowerQuery)) ||
               (license.domains_text && license.domains_text.toLowerCase().includes(lowerQuery));
    });
    
    renderLicensesList(filtered);
}

function renderLicensesList(licenses) {
    const listBody = document.getElementById('licenses-list-body');
    const listContainer = document.getElementById('licenses-list');
    const countSpan = document.getElementById('licenses-count');
    
    if (licenses.length === 0) {
        listBody.innerHTML = `
            <div style="padding: 40px; text-align: center; color: #9CA3AF;">
                <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                <p>No se encontraron licencias</p>
            </div>
        `;
        countSpan.textContent = '0';
        return;
    }
    
    countSpan.textContent = licenses.length;
    listContainer.style.display = 'block';
    
    // Paginación: mostrar máximo 50 por página
    const itemsPerPage = 50;
    const totalPages = Math.ceil(licenses.length / itemsPerPage);
    const currentPage = 1; // Por ahora solo primera página
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, licenses.length);
    const licensesToShow = licenses.slice(startIndex, endIndex);
    
    listBody.innerHTML = licensesToShow.map(license => {
        const statusBadge = license.status === 'active' 
            ? '<span class="badge badge-success">ACTIVA</span>'
            : '<span class="badge badge-danger">INACTIVA</span>';
        
        return `
            <div class="license-item" onclick="selectLicense(${license.id})">
                <div class="license-key">${license.license_key}</div>
                <div class="license-email">${license.user_email || 'Sin email'}</div>
                <div class="license-plan">${license.plan_id || 'N/A'}</div>
                <div class="license-status">${statusBadge}</div>
            </div>
        `;
    }).join('');
    
    // Mostrar info de paginación si hay más de 50
    if (licenses.length > itemsPerPage) {
        listBody.innerHTML += `
            <div style="padding: 16px; text-align: center; color: #6B7280; border-top: 1px solid #E5E7EB; background: #F9FAFB;">
                Mostrando ${startIndex + 1}-${endIndex} de ${licenses.length} licencias
            </div>
        `;
    }
}

function selectLicense(licenseId) {
    // Destacar licencia seleccionada
    document.querySelectorAll('.license-item').forEach(item => {
        item.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Buscar datos completos de la licencia
    const license = allLicenses.find(l => l.id == licenseId);
    if (!license) return;
    
    currentLicenseId = license.id;
    currentLicenseKey = license.license_key;
    
    // Mostrar filtros de fecha
    document.getElementById('date-filters').classList.add('active');
    
    // Renderizar info y cargar estadísticas
    renderLicenseInfo(license);
    loadStats();
}

function renderLicenseInfo(license) {
    const statusBadge = license.status === 'active' 
        ? '<span class="badge badge-success">ACTIVA</span>'
        : '<span class="badge badge-danger">INACTIVA</span>';
    
    const html = `
        <div class="license-info-card">
            <div class="license-info-item">
                <div class="license-info-label">License Key</div>
                <div class="license-info-value"><code>${license.license_key}</code></div>
            </div>
            <div class="license-info-item">
                <div class="license-info-label">Email</div>
                <div class="license-info-value">${license.user_email || 'N/A'}</div>
            </div>
            <div class="license-info-item">
                <div class="license-info-label">Plan</div>
                <div class="license-info-value">${license.plan_id || 'N/A'}</div>
            </div>
            <div class="license-info-item">
                <div class="license-info-label">Estado</div>
                <div class="license-info-value">${statusBadge}</div>
            </div>
            <div class="license-info-item">
                <div class="license-info-label">Tokens Usados / Límite</div>
                <div class="license-info-value">${license.tokens_used_this_period.toLocaleString()} / ${license.tokens_limit.toLocaleString()}</div>
            </div>
            <div class="license-info-item">
                <div class="license-info-label">Periodo</div>
                <div class="license-info-value">${license.period_starts_at.substring(0, 10)} → ${license.period_ends_at.substring(0, 10)}</div>
            </div>
        </div>
        
        <div id="stats-data"></div>
    `;
    
    document.getElementById('stats-content').innerHTML = html;
}

function loadStats() {
    if (!currentLicenseId) return;
    
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    
    const statsContainer = document.getElementById('stats-data');
    statsContainer.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Cargando estadísticas...</p>
        </div>
    `;
    
    fetch(`modules/license-stats/ajax.php?action=get_stats&license_id=${currentLicenseId}&date_from=${dateFrom}&date_to=${dateTo}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderStats(data.stats);
            } else {
                statsContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <p>No hay datos para este periodo</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            statsContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">⚠️</div>
                    <p>Error al cargar estadísticas</p>
                </div>
            `;
        });
}

function renderStats(stats) {
    const html = `
        <!-- KPIs -->
        <div class="stats-kpi-grid">
            <div class="stats-kpi-card">
                <div class="stats-kpi-label">Total Operaciones</div>
                <div class="stats-kpi-value">${stats.general.total_operations.toLocaleString()}</div>
            </div>
            <div class="stats-kpi-card tokens">
                <div class="stats-kpi-label">Total Tokens</div>
                <div class="stats-kpi-value">${stats.general.total_tokens.toLocaleString()}</div>
            </div>
            <div class="stats-kpi-card cost">
                <div class="stats-kpi-label">Costo Total (EUR)</div>
                <div class="stats-kpi-value">€${(stats.general.total_cost * 0.92).toFixed(6)}</div>
            </div>
        </div>
        
        <!-- Por Tipo de Operación -->
        <div class="stats-table-card">
            <div class="stats-table-header">
                <h3>📋 Por Tipo de Operación</h3>
            </div>
            <div class="stats-table-body">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Campañas</th>
                            <th>Consultas API</th>
                            <th>Tokens</th>
                            <th>Costo (EUR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${stats.by_operation.map(op => {
                            const isCampaign = op.is_campaign || false;
                            const isQueue = (op.operation_type === 'queue' && op.is_group) || false;
                            const opInfo = getOperationInfo(op.operation_type || '');
                            
                            // Nombre a mostrar
                            let displayName, icon, style;
                            if (isCampaign) {
                                displayName = op.campaign_name;
                                icon = '🎯 ';
                                style = 'font-weight: bold; color: #667eea;';
                            } else if (isQueue) {
                                displayName = op.display_name || 'Colas Generadas';
                                icon = '📦 ';
                                style = 'font-weight: bold; color: #667eea;';
                            } else {
                                displayName = op.display_name || opInfo.description;
                                icon = '';
                                style = '';
                            }
                            
                            const isExpandable = isCampaign || isQueue;
                            const rowClass = isCampaign ? 'campaign-row' : (isQueue ? 'queue-row' : '');
                            const onClick = isExpandable ? (isCampaign ? 'toggleCampaignDetails(this)' : 'toggleQueueDetails(this)') : '';
                            
                            let html = `
                            <tr class="${rowClass}" ${isExpandable ? 'style="cursor: pointer;"' : ''} ${isExpandable ? `onclick="${onClick}"` : ''}>
                                <td>
                                    <strong 
                                        title="${isCampaign ? 'Campaña: ' + op.campaign_id : (opInfo.endpoint + '&#10;' + opInfo.description)}" 
                                        style="cursor: help; text-decoration: underline dotted; ${style}">
                                        ${icon}${displayName} ${isExpandable ? '<span class="toggle-icon">▼</span>' : ''}
                                    </strong>
                                </td>
                                <td>${isCampaign ? op.total_count.toLocaleString() : op.count.toLocaleString()}</td>
                                <td>${isCampaign ? op.total_tokens.toLocaleString() : op.tokens.toLocaleString()}</td>
                                <td>€${(isCampaign ? (op.total_cost * 0.92) : (op.cost * 0.92)).toFixed(6)}</td>
                            </tr>
                            `;
                            
                            // Si es una campaña, añadir detalles expandibles
                            if (isCampaign) {
                                html += '<tr class="campaign-details" style="display: none;"><td colspan="4">';
                                html += '<div style="padding: 15px; background: #f9fafb; border-left: 3px solid #667eea; margin: 5px 0;">';
                                
                                // Mostrar colas de esta campaña
                                if (op.queues_details && op.queues_details.length > 0) {
                                    html += '<h4 style="margin-top: 0; color: #667eea;">📦 Colas (' + op.queues_count + ')</h4>';
                                    html += '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
                                    html += '<thead><tr style="border-bottom: 1px solid #e5e7eb;"><th style="text-align: left; padding: 8px;">Cola</th><th style="text-align: left; padding: 8px;">Fecha</th><th style="text-align: left; padding: 8px;">Items</th><th style="text-align: left; padding: 8px;">Tokens</th><th style="text-align: left; padding: 8px;">Costo (EUR)</th></tr></thead>';
                                    html += '<tbody>';
                                    
                                    op.queues_details.forEach((queue, idx) => {
                                        html += `<tr style="border-bottom: 1px solid #f3f4f6;">`;
                                        html += `<td style="padding: 8px;">Cola ${idx + 1}</td>`;
                                        html += `<td style="padding: 8px;">${queue.date}</td>`;
                                        html += `<td style="padding: 8px;">`;
                                        
                                        queue.subitems.forEach(sub => {
                                            html += `<div style="margin: 3px 0; font-size: 13px;">`;
                                            html += `  <span style="color: #6b7280;">→</span> ${sub.display_name}: <strong>${sub.count}</strong>`;
                                            html += `</div>`;
                                        });
                                        
                                        html += `</td>`;
                                        html += `<td style="padding: 8px;"><strong>${queue.total_tokens.toLocaleString()}</strong></td>`;
                                        html += `<td style="padding: 8px;">€${(queue.total_cost * 0.92).toFixed(6)}</td>`;
                                        html += `</tr>`;
                                    });
                                    
                                    html += '</tbody></table>';
                                }
                                
                                // Mostrar operaciones individuales de esta campaña
                                if (op.operations && op.operations.length > 0) {
                                    html += '<h4 style="color: #667eea;">📝 Otras Operaciones</h4>';
                                    html += '<table style="width: 100%; border-collapse: collapse;">';
                                    html += '<thead><tr style="border-bottom: 1px solid #e5e7eb;"><th style="text-align: left; padding: 8px;">Tipo</th><th style="text-align: left; padding: 8px;">Cantidad</th><th style="text-align: left; padding: 8px;">Tokens</th><th style="text-align: left; padding: 8px;">Costo (EUR)</th></tr></thead>';
                                    html += '<tbody>';
                                    
                                    op.operations.forEach(operation => {
                                        html += `<tr style="border-bottom: 1px solid #f3f4f6;">`;
                                        html += `<td style="padding: 8px;">${operation.display_name}</td>`;
                                        html += `<td style="padding: 8px;">${operation.count.toLocaleString()}</td>`;
                                        html += `<td style="padding: 8px;">${operation.tokens.toLocaleString()}</td>`;
                                        html += `<td style="padding: 8px;">€${(operation.cost * 0.92).toFixed(6)}</td>`;
                                        html += `</tr>`;
                                    });
                                    
                                    html += '</tbody></table>';
                                }
                                
                                html += '</div></td></tr>';
                            }
                            // Si es una cola (modo legacy), añadir detalles expandibles
                            else if (isQueue && op.queues_details) {
                                html += '<tr class="queue-details" style="display: none;"><td colspan="4">';
                                html += '<div style="padding: 15px; background: #f9fafb; border-left: 3px solid #667eea; margin: 5px 0;">';
                                html += '<table style="width: 100%; border-collapse: collapse;">';
                                html += '<thead><tr style="border-bottom: 1px solid #e5e7eb;"><th style="text-align: left; padding: 8px;">Cola</th><th style="text-align: left; padding: 8px;">Fecha</th><th style="text-align: left; padding: 8px;">Items</th><th style="text-align: left; padding: 8px;">Tokens</th><th style="text-align: left; padding: 8px;">Costo (EUR)</th></tr></thead>';
                                html += '<tbody>';
                                
                                op.queues_details.forEach((queue, idx) => {
                                    html += `<tr style="border-bottom: 1px solid #f3f4f6;">`;
                                    html += `<td style="padding: 8px;">Cola ${idx + 1}</td>`;
                                    html += `<td style="padding: 8px;">${queue.date}</td>`;
                                    html += `<td style="padding: 8px;">`;
                                    
                                    queue.subitems.forEach(sub => {
                                        html += `<div style="margin: 3px 0; font-size: 13px;">`;
                                        html += `  <span style="color: #6b7280;">→</span> ${sub.display_name}: <strong>${sub.count}</strong>`;
                                        html += `</div>`;
                                    });
                                    
                                    html += `</td>`;
                                    html += `<td style="padding: 8px;"><strong>${queue.total_tokens.toLocaleString()}</strong></td>`;
                                    html += `<td style="padding: 8px;">€${(queue.total_cost * 0.92).toFixed(6)}</td>`;
                                    html += `</tr>`;
                                });
                                
                                html += '</tbody></table>';
                                html += '</div></td></tr>';
                            }
                            
                            return html;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Por Fecha -->
        <div class="stats-table-card">
            <div class="stats-table-header">
                <h3>📅 Por Fecha</h3>
                <button class="btn-reset" onclick="resetStats()">🗑️ Resetear Estadísticas</button>
            </div>
            <div class="stats-table-body">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Operaciones</th>
                            <th>Tokens</th>
                            <th>Costo (EUR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${stats.by_date.map(day => `
                            <tr>
                                <td>${day.date}</td>
                                <td>${day.operations.toLocaleString()}</td>
                                <td>${day.tokens.toLocaleString()}</td>
                                <td>€${(day.cost * 0.92).toFixed(6)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('stats-data').innerHTML = html;
}

function refreshStats() {
    if (!currentLicenseId) {
        alert('Primero busca una licencia');
        return;
    }
    loadStats();
}

function resetStats() {
    if (!currentLicenseKey) return;
    
    if (!confirm(`¿Estás seguro de resetear TODAS las estadísticas de la licencia ${currentLicenseKey}?\n\nEsta acción NO se puede deshacer.`)) {
        return;
    }
    
    fetch('modules/license-stats/ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'reset_stats',
            license_key: currentLicenseKey
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Estadísticas reseteadas correctamente');
            loadStats();
        } else {
            alert('❌ Error al resetear estadísticas: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(err => {
        alert('❌ Error: ' + err.message);
    });
}

function toggleCampaignDetails(row) {
    const detailsRow = row.nextElementSibling;
    if (detailsRow && detailsRow.classList.contains('campaign-details')) {
        const isVisible = detailsRow.style.display !== 'none';
        detailsRow.style.display = isVisible ? 'none' : 'table-row';
        
        // Cambiar icono
        const icon = row.querySelector('.toggle-icon');
        if (icon) {
            icon.textContent = isVisible ? '▼' : '▲';
        }
    }
}

function toggleQueueDetails(row) {
    const detailsRow = row.nextElementSibling;
    if (detailsRow && detailsRow.classList.contains('queue-details')) {
        const isVisible = detailsRow.style.display !== 'none';
        detailsRow.style.display = isVisible ? 'none' : 'table-row';
        
        // Cambiar icono
        const icon = row.querySelector('.toggle-icon');
        if (icon) {
            icon.textContent = isVisible ? '▼' : '▲';
        }
    }
}
</script>
