<?php
$pageTitle = 'Gestión de Modelos OpenAI';
?>

<div class="admin-content">
    <div class="content-header">
        <h1><?php echo $pageTitle; ?></h1>
        <button id="btn-refresh-prices" class="btn btn-primary">
            🔄 Actualizar Precios
        </button>
    </div>

    <div id="loading-models" style="text-align: center; padding: 40px;">
        <p>Cargando modelos y precios en tiempo real...</p>
    </div>

    <div id="models-container" style="display: none;">
        <div class="info-box">
            <strong>ℹ️ Información:</strong> Los precios se consultan directamente desde OpenAI. 
            Selecciona el modelo que quieres usar por defecto en la API.
        </div>

        <div class="models-grid" id="models-list">
            <!-- Se rellena vía JS -->
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <strong>Modelo actual en uso:</strong> <span id="current-model-name">Cargando...</span>
        </div>
    </div>

    <div id="error-container" style="display: none;">
        <div class="alert alert-danger">
            <strong>Error:</strong> <span id="error-message"></span>
        </div>
    </div>
</div>

<style>
.models-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.model-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    background: white;
    transition: all 0.3s;
}

.model-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.model-card.selected {
    border-color: #007bff;
    background: #f0f8ff;
}

.model-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.model-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.model-name {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    flex: 1;
}

.model-pricing {
    margin: 15px 0;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 5px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    margin: 5px 0;
}

.price-label {
    font-weight: 500;
    color: #666;
}

.price-value {
    font-family: monospace;
    color: #007bff;
}

.model-features {
    margin-top: 10px;
    font-size: 13px;
    color: #666;
}

.feature-badge {
    display: inline-block;
    padding: 3px 8px;
    margin: 2px;
    background: #e9ecef;
    border-radius: 3px;
    font-size: 12px;
}

.info-box {
    padding: 15px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 5px;
    margin-bottom: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}
</style>

<script src="modules/models/models.js"></script>
