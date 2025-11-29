<?php
/**
 * Test de sincronización de license keys vía HTTP
 * Acceder desde: https://tu-dominio.com/api_claude_5/test-sync-http.php
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Logger.php';
require_once __DIR__ . '/services/LicenseKeySyncService.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Sincronización License Keys</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .section { margin: 20px 0; padding: 15px; background: #252526; border-left: 3px solid #007acc; }
        pre { background: #1e1e1e; padding: 10px; overflow-x: auto; }
        h2 { color: #569cd6; }
        h3 { color: #4ec9b0; }
    </style>
</head>
<body>
    <h1>🔧 Test Sincronización License Keys</h1>

    <?php
    try {
        $syncService = new LicenseKeySyncService();

        // ===== ESTADÍSTICAS =====
        echo '<div class="section">';
        echo '<h2>📊 Estadísticas de Sincronización</h2>';
        $stats = $syncService->getSyncStats();
        echo '<pre>';
        echo "Total licencias:        " . $stats['total_licenses'] . "\n";
        echo "✓ Ya sincronizadas:     " . $stats['synced'] . "\n";
        echo "⏳ Pendientes:          " . $stats['pending'] . "\n";
        echo "❌ Máx. intentos:       " . $stats['max_attempts'] . "\n";
        echo "⚠️  Sin order ID:       " . $stats['no_order_id'] . "\n";
        echo '</pre>';
        echo '</div>';

        // ===== BUSCAR LICENCIAS PENDIENTES =====
        echo '<div class="section">';
        echo '<h2>🔍 Licencias Pendientes de Sincronizar</h2>';

        $db = Database::getInstance();
        $pendingLicenses = $db->query("
            SELECT id, license_key, last_order_id, woo_subscription_id,
                   license_key_sync_attempts, license_key_last_sync_attempt
            FROM " . DB_PREFIX . "licenses
            WHERE license_key_synced_to_woo = 0
              AND license_key_sync_attempts < 5
              AND (
                  (woo_subscription_id IS NOT NULL AND woo_subscription_id != '' AND woo_subscription_id > 0)
                  OR (last_order_id IS NOT NULL AND last_order_id != '' AND last_order_id > 0)
              )
            ORDER BY created_at DESC
            LIMIT 10
        ");

        if (empty($pendingLicenses)) {
            echo '<p class="success">✓ No hay licencias pendientes de sincronizar</p>';
        } else {
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse; color: #d4d4d4;">';
            echo '<tr><th>ID</th><th>License Key</th><th>Order ID</th><th>Intentos</th><th>Último Intento</th></tr>';
            foreach ($pendingLicenses as $lic) {
                echo '<tr>';
                echo '<td>' . $lic['id'] . '</td>';
                echo '<td><code>' . $lic['license_key'] . '</code></td>';
                echo '<td>' . ($lic['last_order_id'] ?? $lic['woo_subscription_id']) . '</td>';
                echo '<td>' . $lic['license_key_sync_attempts'] . '</td>';
                echo '<td>' . ($lic['license_key_last_sync_attempt'] ?? 'Nunca') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</div>';

        // ===== TEST: SINCRONIZAR UNA LICENCIA =====
        if (!empty($pendingLicenses)) {
            echo '<div class="section">';
            echo '<h2>🧪 Test: Sincronizar Primera Licencia Pendiente</h2>';

            $testLicense = $pendingLicenses[0];
            echo '<h3>Licencia ID: ' . $testLicense['id'] . ' - ' . $testLicense['license_key'] . '</h3>';

            $result = $syncService->syncLicenseKey($testLicense['id']);

            echo '<pre>';
            echo "Resultado: " . ($result['success'] ? '<span class="success">✓ ÉXITO</span>' : '<span class="error">✗ FALLO</span>') . "\n";
            echo "Mensaje: " . $result['message'] . "\n";

            if (isset($result['attempts'])) {
                echo "Intentos: " . $result['attempts'] . "\n";
            }
            if (isset($result['will_retry'])) {
                echo "Reintentará: " . ($result['will_retry'] ? 'Sí' : 'No') . "\n";
            }
            echo '</pre>';

            // Mostrar detalles completos
            echo '<details>';
            echo '<summary>Ver respuesta completa</summary>';
            echo '<pre>' . print_r($result, true) . '</pre>';
            echo '</details>';
            echo '</div>';
        }

        // ===== TEST: SINCRONIZAR TODAS =====
        echo '<div class="section">';
        echo '<h2>🚀 Test: Sincronizar TODAS las Pendientes</h2>';

        $batchResults = $syncService->syncPendingLicenseKeys(10);

        echo '<pre>';
        echo "Total procesadas: " . $batchResults['total'] . "\n";
        echo "<span class='success'>✓ Sincronizadas:  " . $batchResults['synced'] . "</span>\n";
        echo "<span class='error'>✗ Fallidas:      " . $batchResults['failed'] . "</span>\n";
        echo "⊘ Omitidas:      " . $batchResults['skipped'] . "\n";
        echo '</pre>';

        echo '<details>';
        echo '<summary>Ver respuesta completa</summary>';
        echo '<pre>' . print_r($batchResults, true) . '</pre>';
        echo '</details>';
        echo '</div>';

        // ===== VERIFICAR EN BD =====
        echo '<div class="section">';
        echo '<h2>✅ Verificar Estado Actualizado</h2>';

        $updatedStats = $syncService->getSyncStats();
        echo '<pre>';
        echo "Total licencias:        " . $updatedStats['total_licenses'] . "\n";
        echo "✓ Ya sincronizadas:     " . $updatedStats['synced'] . " ";
        if ($updatedStats['synced'] > $stats['synced']) {
            echo '<span class="success">(+' . ($updatedStats['synced'] - $stats['synced']) . ')</span>';
        }
        echo "\n";
        echo "⏳ Pendientes:          " . $updatedStats['pending'] . " ";
        if ($updatedStats['pending'] < $stats['pending']) {
            echo '<span class="success">(-' . ($stats['pending'] - $updatedStats['pending']) . ')</span>';
        }
        echo "\n";
        echo '</pre>';
        echo '</div>';

        // ===== LOGS RECIENTES =====
        echo '<div class="section">';
        echo '<h2>📝 Últimos Logs de Sincronización</h2>';

        $logFile = API_BASE_DIR . '/logs/sync.log';
        if (file_exists($logFile)) {
            $logs = file($logFile);
            $recentLogs = array_slice($logs, -10);
            echo '<pre style="font-size: 11px;">';
            foreach ($recentLogs as $log) {
                if (stripos($log, 'license key') !== false || stripos($log, 'license_key') !== false) {
                    echo '<span class="warning">' . htmlspecialchars($log) . '</span>';
                } else {
                    echo htmlspecialchars($log);
                }
            }
            echo '</pre>';
        } else {
            echo '<p>No se encontró el archivo de logs</p>';
        }
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="section">';
        echo '<h2 class="error">❌ ERROR</h2>';
        echo '<pre class="error">';
        echo "Mensaje: " . $e->getMessage() . "\n";
        echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "\nTrace:\n" . $e->getTraceAsString();
        echo '</pre>';
        echo '</div>';
    }
    ?>

    <div class="section">
        <h2>🔄 Siguientes Pasos</h2>
        <ol>
            <li>Revisa los resultados arriba</li>
            <li>Si hay errores, copia el mensaje completo</li>
            <li>Verifica en WooCommerce si llegó la license_key al pedido</li>
            <li>Puedes recargar esta página para probar de nuevo</li>
        </ol>
    </div>
</body>
</html>
<?php
Logger::sync('info', 'Manual sync test executed via HTTP', [
    'stats' => $stats ?? null,
    'batch_results' => $batchResults ?? null
]);
?>
