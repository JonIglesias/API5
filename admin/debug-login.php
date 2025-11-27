<?php
/**
 * Debug Login - Ver error exacto
 * Sube a: /api_claude_4/admin/debug-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Login</h1>";

echo "<h2>1. Verificar archivos necesarios</h2>";
$files = [
    '../config.php' => __DIR__ . '/../config.php',
    '../core/Database.php' => __DIR__ . '/../core/Database.php',
    '../core/Auth.php' => __DIR__ . '/../core/Auth.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "$name: " . ($exists ? '✅ Existe' : '❌ NO EXISTE') . "<br>";
    if (!$exists) {
        echo "&nbsp;&nbsp;Ruta buscada: $path<br>";
    }
}

echo "<h2>2. Intentar cargar config.php</h2>";
try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/../config.php';
    echo "✅ Config cargado<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "API_VERSION: " . API_VERSION . "<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<h2>3. Intentar cargar Database.php</h2>";
try {
    require_once __DIR__ . '/../core/Database.php';
    echo "✅ Database.php cargado<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<h2>4. Intentar cargar Auth.php</h2>";
try {
    require_once __DIR__ . '/../core/Auth.php';
    echo "✅ Auth.php cargado<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<h2>5. Verificar sesión</h2>";
try {
    session_start();
    echo "✅ Sesión iniciada<br>";
    echo "Session ID: " . session_id() . "<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h2>6. Ver último error de PHP</h2>";
$error = error_get_last();
if ($error) {
    echo "<pre>";
    print_r($error);
    echo "</pre>";
} else {
    echo "No hay errores registrados<br>";
}

echo "<h2>7. Contenido del directorio /core/</h2>";
$coreDir = __DIR__ . '/../core/';
if (is_dir($coreDir)) {
    $files = scandir($coreDir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
} else {
    echo "❌ Directorio /core/ no existe<br>";
}

echo "<h2>8. PHP Error Log</h2>";
$errorLog = ini_get('error_log');
echo "Error log path: " . ($errorLog ?: 'default') . "<br>";

if ($errorLog && file_exists($errorLog)) {
    echo "<h3>Últimas líneas:</h3>";
    echo "<pre>";
    $lines = file($errorLog);
    echo htmlspecialchars(implode('', array_slice($lines, -20)));
    echo "</pre>";
}