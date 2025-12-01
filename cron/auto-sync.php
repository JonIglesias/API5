<?php
/**
 * Cron: Auto-Sync con WooCommerce
 *
 * Este script sincroniza automáticamente las suscripciones de WooCommerce
 * con las licencias locales, sin depender de webhooks.
 *
 * Ejecutar cada 5 minutos para que las licencias se generen rápido:
 * Crontab: star-slash-5 * * * * php /path/to/API5/cron/auto-sync.php
 * (Reemplazar star-slash con asterisco y barra)
 *
 * @version 1.3
 */

// Mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

// Detectar si es CLI o Web
$isCli = (php_sapi_name() === 'cli');

// Función para output compatible con CLI y Web
function output($message) {
    global $isCli;
    if ($isCli) {
        echo $message . "\n";
    } else {
        echo $message . "<br>\n";
    }
}

// Headers para web
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../core/Logger.php';
    require_once __DIR__ . '/../services/AutoSyncService.php';
} catch (Exception $e) {
    output("ERROR loading dependencies: " . $e->getMessage());
    exit(1);
}

// Obtener argumento para tipo de sync
if ($isCli) {
    $syncType = $argv[1] ?? 'recent';
} else {
    $syncType = $_GET['type'] ?? 'recent';
}

output("[" . date('Y-m-d H:i:s') . "] Starting auto-sync ({$syncType})...");

try {
    $autoSync = new AutoSyncService();

    if ($syncType === 'full') {
        output("Running FULL sync of all orders...");
        $results = $autoSync->syncAll();
    } else {
        $hours = defined('SYNC_HOURS_LOOKBACK') ? SYNC_HOURS_LOOKBACK : 168;
        $days = round($hours / 24, 1);
        output("Running RECENT sync (last {$hours} hours / {$days} days)...");
        $results = $autoSync->syncRecent($hours);
    }

    output("");
    output("=== Results ===");
    output("Created:   {$results['created']}");
    output("Updated:   {$results['updated']}");
    output("Unchanged: {$results['unchanged']}");
    output("Skipped:   {$results['skipped']}");
    output("Errors:    {$results['errors']}");

    if (!empty($results['details'])) {
        output("");
        output("Details:");
        foreach ($results['details'] as $detail) {
            output("  - {$detail}");
        }
    }

    // Guardar estado del cron para el panel admin
    $autoSync->saveCronStatus($results, $syncType);

    // Registrar en log de sincronización
    Logger::sync('info', "Cron auto-sync completed ({$syncType})", [
        'type' => $syncType,
        'results' => $results
    ]);

    output("");
    output("[" . date('Y-m-d H:i:s') . "] Auto-sync completed successfully!");

} catch (Exception $e) {
    output("");
    output("ERROR: " . $e->getMessage());

    Logger::sync('error', 'Cron auto-sync failed', [
        'error' => $e->getMessage()
    ]);

    exit(1);
}
