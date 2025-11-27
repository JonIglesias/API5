/**
 * Módulo de Gestión de Modelos OpenAI
 */

document.addEventListener('DOMContentLoaded', function() {
    loadModels();
    
    document.getElementById('btn-refresh-prices').addEventListener('click', function() {
        loadModels(true);
    });
});

async function loadModels(forceRefresh = false) {
    const loading = document.getElementById('loading-models');
    const container = document.getElementById('models-container');
    const errorContainer = document.getElementById('error-container');
    
    loading.style.display = 'block';
    container.style.display = 'none';
    errorContainer.style.display = 'none';
    
    try {
        const response = await fetch('modules/models/ajax.php?action=get_models' + (forceRefresh ? '&refresh=1' : ''));
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error desconocido');
        }
        
        renderModels(data.models, data.current_model);
        
        loading.style.display = 'none';
        container.style.display = 'block';
        
    } catch (error) {
        loading.style.display = 'none';
        errorContainer.style.display = 'block';
        document.getElementById('error-message').textContent = error.message;
    }
}

function renderModels(models, currentModel) {
    const container = document.getElementById('models-list');
    const currentModelSpan = document.getElementById('current-model-name');
    
    currentModelSpan.textContent = currentModel || 'gpt-4o-mini';
    
    container.innerHTML = models.map(model => {
        const isSelected = model.id === currentModel;
        
        return `
            <div class="model-card ${isSelected ? 'selected' : ''}" data-model="${model.id}">
                <div class="model-header">
                    <input type="radio" 
                           name="selected_model" 
                           class="model-checkbox" 
                           value="${model.id}"
                           ${isSelected ? 'checked' : ''}
                           onchange="selectModel('${model.id}')">
                    <div class="model-name">${model.name}</div>
                </div>
                
                <div class="model-pricing">
                    <div class="price-row">
                        <span class="price-label">Input:</span>
                        <span class="price-value">$${model.input_price}/1K tokens</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Output:</span>
                        <span class="price-value">$${model.output_price}/1K tokens</span>
                    </div>
                    <div class="price-row" style="border-top: 1px solid #ddd; margin-top: 8px; padding-top: 8px;">
                        <span class="price-label"><strong>Por 1M tokens:</strong></span>
                        <span class="price-value"><strong>$${(model.input_price * 1000).toFixed(2)} / $${(model.output_price * 1000).toFixed(2)}</strong></span>
                    </div>
                </div>
                
                <div class="model-features">
                    ${model.context ? `<span class="feature-badge">📄 ${model.context}K context</span>` : ''}
                    ${model.features ? model.features.map(f => `<span class="feature-badge">${f}</span>`).join('') : ''}
                </div>
            </div>
        `;
    }).join('');
}

async function selectModel(modelId) {
    if (!confirm(`¿Cambiar modelo activo a ${modelId}?`)) {
        loadModels(); // Recargar para resetear el radio button
        return;
    }
    
    try {
        const response = await fetch('modules/models/ajax.php?action=set_model', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ model: modelId })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error al cambiar modelo');
        }
        
        // Recargar para reflejar cambios
        loadModels();
        
        alert('✅ Modelo actualizado correctamente');
        
    } catch (error) {
        alert('❌ Error: ' + error.message);
        loadModels();
    }
}
