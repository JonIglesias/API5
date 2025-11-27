<?php
/**
 * Settings Module
 */

$success = '';
$error = '';

// Obtener settings actuales de BD
try {
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Guardar OpenAI API Key en BD
        $apiKey = $_POST['openai_api_key'] ?? '';
        
        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value, setting_type) 
                              VALUES ('openai_api_key', ?, 'string') 
                              ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$apiKey, $apiKey]);
        
        // Actualizar config.php con WooCommerce settings
        $configPath = __DIR__ . '/../../../config.php';
        
        if (is_writable($configPath)) {
            $configContent = file_get_contents($configPath);
            
            // Reemplazar WooCommerce API URL
            $configContent = preg_replace(
                "/define\('WC_API_URL',\s*'[^']*'\);/",
                "define('WC_API_URL', '" . addslashes($_POST['wc_api_url']) . "');",
                $configContent
            );
            
            // Reemplazar Consumer Key
            $configContent = preg_replace(
                "/define\('WC_CONSUMER_KEY',\s*'[^']*'\);/",
                "define('WC_CONSUMER_KEY', '" . addslashes($_POST['wc_consumer_key']) . "');",
                $configContent
            );
            
            // Reemplazar Consumer Secret
            $configContent = preg_replace(
                "/define\('WC_CONSUMER_SECRET',\s*'[^']*'\);/",
                "define('WC_CONSUMER_SECRET', '" . addslashes($_POST['wc_consumer_secret']) . "');",
                $configContent
            );
            
            file_put_contents($configPath, $configContent);
            $success = '✅ Configuración guardada correctamente.';
        } else {
            $success = '✅ OpenAI guardado en BD. ⚠️ config.php no es escribible.';
        }
    }
    
    // Leer OpenAI API Key de BD
    $stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'openai_api_key' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $openaiKey = $result['setting_value'] ?? '';
    
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $openaiKey = '';
}
?>

<style>
.settings-card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.settings-card h3 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
}
.info-box {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 20px;
}
.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 20px;
}
.success-box {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 15px;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.form-group input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}
.form-group small {
    color: #6c757d;
    font-size: 12px;
    display: block;
    margin-top: 5px;
}
.btn {
    padding: 10px 20px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.btn-primary {
    background: #007bff;
    color: white;
}
.btn-primary:hover {
    background: #0056b3;
}
.alert {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.key-preview {
    font-family: monospace;
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    word-break: break-all;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <!-- OpenAI Configuration -->
    <div class="settings-card">
        <h3>🤖 OpenAI API</h3>
        
        <div class="info-box">
            <strong>ℹ️ Información:</strong> La API Key se guarda de forma segura en la base de datos.<br>
            Obtén tu API Key en: <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
        </div>
        
        <?php if ($openaiKey): ?>
        <div class="success-box">
            <strong>✅ API Key Configurada</strong>
            <div class="key-preview" style="margin-top: 10px;">
                <?= htmlspecialchars(substr($openaiKey, 0, 10)) ?>...<?= htmlspecialchars(substr($openaiKey, -10)) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label>OpenAI API Key *</label>
            <input type="password" 
                   name="openai_api_key" 
                   value="<?= htmlspecialchars($openaiKey) ?>" 
                   placeholder="sk-..." 
                   required>
            <small>Formato: sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</small>
        </div>
    </div>

    <!-- WooCommerce Configuration -->
    <div class="settings-card">
        <h3>🛒 WooCommerce API</h3>
        
        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Obtén las credenciales en WooCommerce → Settings → Advanced → REST API
        </div>
        
        <div class="form-group">
            <label>WooCommerce API URL *</label>
            <input type="url" 
                   name="wc_api_url" 
                   value="<?= htmlspecialchars(WC_API_URL) ?>" 
                   required>
            <small>Ejemplo: https://tu-tienda.com/wp-json/wc/v3/</small>
        </div>
        
        <div class="form-group">
            <label>Consumer Key *</label>
            <input type="text" 
                   name="wc_consumer_key" 
                   value="<?= htmlspecialchars(WC_CONSUMER_KEY) ?>" 
                   required>
            <small>Empieza con: ck_</small>
        </div>
        
        <div class="form-group">
            <label>Consumer Secret *</label>
            <input type="password" 
                   name="wc_consumer_secret" 
                   value="<?= htmlspecialchars(WC_CONSUMER_SECRET) ?>" 
                   required>
            <small>Empieza con: cs_</small>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary">💾 Guardar Configuración</button>
</form>

<script>
// Mostrar contraseñas al hacer hover
document.querySelectorAll('input[type="password"]').forEach(input => {
    input.addEventListener('mouseenter', function() {
        this.type = 'text';
    });
    input.addEventListener('mouseleave', function() {
        this.type = 'password';
    });
});
</script>
