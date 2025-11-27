<?php
/**
 * Test Login Simple
 * Sube a: /api_claude_4/admin/test-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Login Simple</h1>";

// Paso 1: Definir constante
echo "<h2>Paso 1: Definir API_ACCESS</h2>";
define('API_ACCESS', true);
echo "✅ Definido<br>";

// Paso 2: Cargar config
echo "<h2>Paso 2: Cargar config.php</h2>";
try {
    require_once __DIR__ . '/../config.php';
    echo "✅ Config cargado<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    die();
}

// Paso 3: Cargar Database
echo "<h2>Paso 3: Cargar Database.php</h2>";
try {
    require_once __DIR__ . '/../core/Database.php';
    echo "✅ Database.php cargado<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    die();
}

// Paso 4: Intentar instanciar Database
echo "<h2>Paso 4: Instanciar Database</h2>";
try {
    $db = Database::getInstance();
    echo "✅ Database instanciada<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    die();
}

// Paso 5: Test query simple
echo "<h2>Paso 5: Query de prueba</h2>";
try {
    $result = $db->query("SELECT 1 as test");
    echo "✅ Query ejecutada: ";
    print_r($result);
    echo "<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    die();
}

// Paso 6: Verificar tabla admin_users
echo "<h2>Paso 6: Verificar tabla admin_users</h2>";
try {
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "admin_users");
    echo "✅ Usuarios en BD: " . $result[0]['total'] . "<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    die();
}

// Paso 7: Cargar Auth
echo "<h2>Paso 7: Cargar Auth.php</h2>";
try {
    require_once __DIR__ . '/../core/Auth.php';
    echo "✅ Auth.php cargado<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    die();
}

// Paso 8: Iniciar sesión
echo "<h2>Paso 8: Iniciar sesión PHP</h2>";
try {
    session_start();
    echo "✅ Sesión iniciada (ID: " . session_id() . ")<br>";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Paso 9: Test de login
echo "<h2>Paso 9: Intentar login de prueba</h2>";
echo "<form method='POST'>";
echo "Usuario: <input type='text' name='username' value='admin'><br>";
echo "Password: <input type='password' name='password'><br>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h3>Procesando login...</h3>";
    echo "Username: " . htmlspecialchars($username) . "<br>";
    echo "Password: " . (empty($password) ? "vacío" : "***") . "<br>";
    
    try {
        $result = Auth::attempt($username, $password);
        if ($result) {
            echo "✅ <strong>LOGIN EXITOSO</strong><br>";
            echo "Session data: ";
            print_r($_SESSION);
        } else {
            echo "❌ <strong>LOGIN FALLIDO</strong> (credenciales incorrectas)<br>";
        }
    } catch (Throwable $e) {
        echo "❌ <strong>ERROR EN AUTH::ATTEMPT</strong><br>";
        echo "Mensaje: " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . "<br>";
        echo "Line: " . $e->getLine() . "<br>";
        echo "Trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<h2>✅ Todos los pasos completados</h2>";
echo "<p>Si llegaste aquí, los archivos están bien. El problema está en el login real.</p>";