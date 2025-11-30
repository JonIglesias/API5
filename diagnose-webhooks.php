<?php
/**
 * Script de diagnóstico para webhooks de WooCommerce
 *
 * Ejecutar: php diagnose-webhooks.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

echo "==========================================================\n";
echo "DIAGNÓSTICO DE WEBHOOKS DE WOOCOMMERCE\n";
echo "==========================================================\n\n";

// 1. Información del endpoint
echo "1️⃣  ENDPOINT DEL WEBHOOK:\n";
echo "==========================================================\n\n";

$baseUrl = defined('API_BASE_URL') ? API_BASE_URL : 'https://tu-dominio.com/api';
$webhookUrl = $baseUrl . '/webhooks/woocommerce';

echo "URL del webhook: {$webhookUrl}\n";
echo "Método: POST\n\n";

echo "📋 CONFIGURACIÓN EN WOOCOMMERCE:\n";
echo "   1. Ve a WooCommerce → Settings → Advanced → Webhooks\n";
echo "   2. Asegúrate de tener webhooks configurados para:\n";
echo "      - Order created\n";
echo "      - Order updated\n";
echo "      - Subscription created (si usas suscripciones)\n";
echo "      - Subscription updated\n";
echo "   3. Delivery URL: {$webhookUrl}\n";
echo "   4. Status: Active\n\n";

// 2. Verificar tabla de logs de webhooks
echo "==========================================================\n";
echo "2️⃣  WEBHOOKS RECIBIDOS (últimos 10):\n";
echo "==========================================================\n\n";

try {
    $webhookLogs = $db->query("
        SELECT *
        FROM " . DB_PREFIX . "webhook_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");

    if (empty($webhookLogs)) {
        echo "❌ NO se han recibido webhooks.\n\n";
        echo "POSIBLES CAUSAS:\n";
        echo "  1. Los webhooks NO están configurados en WooCommerce\n";
        echo "  2. La URL del webhook es incorrecta\n";
        echo "  3. WooCommerce no puede alcanzar la URL (firewall, DNS)\n";
        echo "  4. Los webhooks están desactivados en WooCommerce\n\n";
    } else {
        echo "✅ Se han recibido " . count($webhookLogs) . " webhook(s) recientemente:\n\n";

        foreach ($webhookLogs as $log) {
            echo "ID: {$log['id']}\n";
            echo "  Evento: {$log['event']}\n";
            echo "  Status: {$log['status']}\n";
            echo "  Fecha: {$log['created_at']}\n";

            if ($log['status'] === 'error' || $log['status'] === 'failed') {
                echo "  ❌ ERROR: " . ($log['error_message'] ?? 'Unknown error') . "\n";
            } else {
                echo "  ✅ Procesado correctamente\n";
            }

            // Mostrar datos del webhook si existen
            if (!empty($log['payload'])) {
                $payload = json_decode($log['payload'], true);
                if (isset($payload['id'])) {
                    echo "  Order/Subscription ID: {$payload['id']}\n";
                }
            }

            echo "  ---\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  No se pudo leer la tabla de logs de webhooks.\n";
    echo "Error: {$e->getMessage()}\n\n";
}

// 3. Verificar archivo de log de webhooks
echo "\n==========================================================\n";
echo "3️⃣  LOG DE ARCHIVO (logs/webhook.log):\n";
echo "==========================================================\n\n";

$webhookLogFile = API_BASE_DIR . '/logs/webhook.log';

if (file_exists($webhookLogFile)) {
    $fileSize = filesize($webhookLogFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);

    echo "Archivo: {$webhookLogFile}\n";
    echo "Tamaño: {$fileSizeMB} MB\n\n";

    if ($fileSize > 0) {
        echo "📄 ÚLTIMAS 20 LÍNEAS:\n";
        echo "---\n";

        $lines = file($webhookLogFile);
        $lastLines = array_slice($lines, -20);

        foreach ($lastLines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded) {
                $timestamp = $decoded['timestamp'] ?? 'N/A';
                $level = $decoded['level'] ?? 'INFO';
                $message = $decoded['message'] ?? '';

                echo "[{$timestamp}] {$level}: {$message}\n";

                if (isset($decoded['context']) && !empty($decoded['context'])) {
                    if (isset($decoded['context']['event'])) {
                        echo "  → Evento: {$decoded['context']['event']}\n";
                    }
                    if (isset($decoded['context']['error'])) {
                        echo "  → Error: {$decoded['context']['error']}\n";
                    }
                }
            }
        }
        echo "---\n\n";
    } else {
        echo "⚠️  El archivo está vacío (0 bytes).\n";
        echo "   No se han registrado webhooks en el archivo de log.\n\n";
    }
} else {
    echo "❌ El archivo de log no existe: {$webhookLogFile}\n";
    echo "   Esto es normal si nunca se han recibido webhooks.\n\n";
}

// 4. Verificar configuración
echo "==========================================================\n";
echo "4️⃣  CONFIGURACIÓN:\n";
echo "==========================================================\n\n";

echo "Webhook Secret: " . (defined('WEBHOOK_SECRET') ? WEBHOOK_SECRET : 'NO CONFIGURADO') . "\n";
echo "IP Whitelist: " . (defined('WEBHOOK_ALLOWED_IPS') ? implode(', ', WEBHOOK_ALLOWED_IPS) : 'DESHABILITADA (permite todas)') . "\n\n";

// 5. Test de conectividad
echo "==========================================================\n";
echo "5️⃣  TEST DE WEBHOOK (simulación local):\n";
echo "==========================================================\n\n";

echo "Para probar el webhook manualmente:\n\n";

echo "curl -X POST {$webhookUrl} \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -H 'X-WC-Webhook-Topic: order.created' \\\n";
echo "  -d '{\n";
echo "    \"id\": 12345,\n";
echo "    \"status\": \"completed\",\n";
echo "    \"billing\": {\"email\": \"test@example.com\"},\n";
echo "    \"line_items\": [{\"product_id\": 123}]\n";
echo "  }'\n\n";

// 6. Recomendaciones
echo "==========================================================\n";
echo "RECOMENDACIONES:\n";
echo "==========================================================\n\n";

if (empty($webhookLogs)) {
    echo "🔧 ACCIÓN REQUERIDA: Configurar webhooks en WooCommerce\n\n";
    echo "PASOS:\n";
    echo "1. Ve a WooCommerce → Settings → Advanced → Webhooks\n";
    echo "2. Click 'Add webhook'\n";
    echo "3. Configuración sugerida:\n\n";

    echo "   WEBHOOK 1: Pedidos creados\n";
    echo "   - Name: Order Created\n";
    echo "   - Status: Active\n";
    echo "   - Topic: Order created\n";
    echo "   - Delivery URL: {$webhookUrl}\n";
    echo "   - Secret: " . (defined('WEBHOOK_SECRET') ? WEBHOOK_SECRET : 'sin secret') . "\n";
    echo "   - API Version: WP REST API Integration v3\n\n";

    echo "   WEBHOOK 2: Pedidos actualizados\n";
    echo "   - Name: Order Updated\n";
    echo "   - Status: Active\n";
    echo "   - Topic: Order updated\n";
    echo "   - Delivery URL: {$webhookUrl}\n";
    echo "   - Secret: " . (defined('WEBHOOK_SECRET') ? WEBHOOK_SECRET : 'sin secret') . "\n";
    echo "   - API Version: WP REST API Integration v3\n\n";

    echo "4. Click 'Save webhook'\n";
    echo "5. Prueba creando un pedido de prueba en WooCommerce\n";
    echo "6. Ejecuta este script de nuevo para verificar\n\n";
} else {
    $failedWebhooks = array_filter($webhookLogs, function($log) {
        return in_array($log['status'], ['error', 'failed']);
    });

    if (!empty($failedWebhooks)) {
        echo "⚠️  HAY WEBHOOKS FALLANDO:\n";
        echo "   Revisa los errores en los logs arriba\n";
        echo "   Verifica que:\n";
        echo "   - Los planes tienen woo_product_id configurado\n";
        echo "   - Los productos en los pedidos coinciden con planes\n";
        echo "   - No hay errores de conexión a base de datos\n\n";
    } else {
        echo "✅ Los webhooks están funcionando correctamente!\n";
        echo "   Si aún no se crean licencias automáticamente, verifica:\n";
        echo "   - Que los planes tengan woo_product_id configurado\n";
        echo "   - Que los pedidos tengan productos mapeados a planes\n";
        echo "   - Ejecuta: php diagnose-orders.php\n\n";
    }
}

echo "📚 ALTERNATIVA: AUTO-SYNC (CRON)\n";
echo "   Aunque no funcionen los webhooks, el cron auto-sync\n";
echo "   se ejecuta cada 5 minutos como backup y crea licencias.\n";
echo "   Verifica que esté funcionando: php diagnose-orders.php\n\n";

echo "==========================================================\n";
