<?php
/**
 * Debug AJAX - Ver error exacto
 * Sube a: /admin/modules/sync/debug.php
 * Accede a: https://bocetosmarketing.com/api_claude_4/admin/modules/sync/debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug AJAX Sync</h1>";

echo "<h2>1. Verificar archivos necesarios</h2>";
$files = [
    '../../config.php' => __DIR__ . '/../../config.php',
    '../../core/Database.php' => __DIR__ . '/../../core/Database.php',
    '../../core/Auth.php' => __DIR__ . '/../../core/Auth.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "$name: " . ($exists ? '✅' : '❌') . "<br>";
}

echo "<h2>2. Cargar config</h2>";
try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/../../config.php';
    echo "✅ Config OK<br>";
} catch (Throwable $e) {
    echo "❌ " . $e->getMessage() . "<br>";
    die();
}

echo "<h2>3. Cargar Database</h2>";
try {
    require_once __DIR__ . '/../../core/Database.php';
    $db = Database::getInstance();
    echo "✅ Database OK<br>";
} catch (Throwable $e) {
    echo "❌ " . $e->getMessage() . "<br>";
    die();
}

echo "<h2>4. Cargar Auth</h2>";
try {
    require_once __DIR__ . '/../../core/Auth.php';
    echo "✅ Auth OK<br>";
} catch (Throwable $e) {
    echo "❌ " . $e->getMessage() . "<br>";
    die();
}

echo "<h2>5. Test BD</h2>";
try {
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "licenses");
    echo "✅ Licencias: " . ($result[0]['total'] ?? 0) . "<br>";
} catch (Throwable $e) {
    echo "❌ " . $e->getMessage() . "<br>";
}

echo "<h2>6. cURL</h2>";
if (function_exists('curl_init')) {
    echo "✅ cURL disponible<br>";
} else {
    echo "❌ cURL NO disponible<br>";
}

echo "<h2>7. WooCommerce Config</h2>";
echo "URL: " . WC_API_URL . "<br>";
echo "Key: " . substr(WC_CONSUMER_KEY, 0, 10) . "...<br>";

echo "<h2>✅ Tests completados</h2>";
echo "<p>Revisa error log: /home/bocetosm/logs/bocetosmarketing_com.php.error.log</p>";
