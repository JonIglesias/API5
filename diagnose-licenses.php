<?php
/**
 * Script de diagnóstico de licencias en la base de datos
 *
 * Muestra todas las licencias y su asociación con pedidos de WooCommerce
 *
 * Ejecutar: php diagnose-licenses.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_ACCESS', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

echo "==========================================================\n";
echo "DIAGNÓSTICO DE LICENCIAS EN LA BASE DE DATOS\n";
echo "==========================================================\n\n";

// Obtener todas las licencias
$licenses = $db->query("
    SELECT
        l.id,
        l.license_key,
        l.user_email,
        l.plan_id,
        l.last_order_id,
        l.woo_subscription_id,
        l.license_key_synced_to_woo,
        l.created_at,
        p.name as plan_name,
        p.woo_product_id
    FROM " . DB_PREFIX . "licenses l
    LEFT JOIN " . DB_PREFIX . "plans p ON l.plan_id = p.id
    ORDER BY l.created_at DESC
    LIMIT 50
");

if (empty($licenses)) {
    echo "❌ NO HAY LICENCIAS EN LA BASE DE DATOS\n\n";
    exit(0);
}

echo "📊 Total de licencias: " . count($licenses) . " (mostrando últimas 50)\n\n";

echo "==========================================================\n";
echo "LISTA DE LICENCIAS:\n";
echo "==========================================================\n\n";

foreach ($licenses as $lic) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "License Key: {$lic['license_key']}\n";
    echo "  Plan: {$lic['plan_name']} (ID: {$lic['plan_id']})\n";
    echo "  Email: {$lic['user_email']}\n";
    echo "  Creada: {$lic['created_at']}\n";
    echo "  Sincronizada a WC: " . ($lic['license_key_synced_to_woo'] ? 'Sí' : 'No') . "\n";
    echo "\n";
    echo "  🔗 ASOCIACIÓN CON PEDIDOS:\n";
    echo "     last_order_id: " . ($lic['last_order_id'] ?: '❌ NULL') . "\n";
    echo "     woo_subscription_id: " . ($lic['woo_subscription_id'] ?: '❌ NULL') . "\n";

    // Verificar si hay problema de asociación
    if (!$lic['last_order_id'] && !$lic['woo_subscription_id']) {
        echo "\n  ⚠️  PROBLEMA: Licencia sin asociación a ningún pedido\n";
        echo "     Esto hace imposible determinar a qué pedido pertenece.\n";
    }

    echo "\n";
}

// Estadísticas
echo "==========================================================\n";
echo "ESTADÍSTICAS:\n";
echo "==========================================================\n\n";

$withOrderId = array_filter($licenses, function($l) {
    return !empty($l['last_order_id']);
});

$withSubscriptionId = array_filter($licenses, function($l) {
    return !empty($l['woo_subscription_id']);
});

$withoutAnyId = array_filter($licenses, function($l) {
    return empty($l['last_order_id']) && empty($l['woo_subscription_id']);
});

$synced = array_filter($licenses, function($l) {
    return $l['license_key_synced_to_woo'];
});

echo "Licencias con last_order_id: " . count($withOrderId) . " (" . round(count($withOrderId) / count($licenses) * 100) . "%)\n";
echo "Licencias con woo_subscription_id: " . count($withSubscriptionId) . " (" . round(count($withSubscriptionId) / count($licenses) * 100) . "%)\n";
echo "Licencias SIN ningún ID de pedido: " . count($withoutAnyId) . " (" . round(count($withoutAnyId) / count($licenses) * 100) . "%)\n";
echo "Licencias sincronizadas a WC: " . count($synced) . " (" . round(count($synced) / count($licenses) * 100) . "%)\n\n";

if (!empty($withoutAnyId)) {
    echo "🚨 PROBLEMA CRÍTICO:\n";
    echo "Hay " . count($withoutAnyId) . " licencia(s) sin asociación a pedidos.\n";
    echo "Esto indica que el auto-sync no está guardando correctamente los order_ids.\n\n";

    echo "Licencias afectadas:\n";
    foreach ($withoutAnyId as $l) {
        echo "  - {$l['license_key']} ({$l['user_email']}) - Creada: {$l['created_at']}\n";
    }
    echo "\n";
}

// Buscar licencias duplicadas por usuario+plan
echo "==========================================================\n";
echo "DETECCIÓN DE LICENCIAS DUPLICADAS:\n";
echo "==========================================================\n\n";

$userPlanGroups = [];
foreach ($licenses as $lic) {
    $key = $lic['user_email'] . '|' . $lic['plan_id'];
    if (!isset($userPlanGroups[$key])) {
        $userPlanGroups[$key] = [];
    }
    $userPlanGroups[$key][] = $lic;
}

$duplicates = array_filter($userPlanGroups, function($group) {
    return count($group) > 1;
});

if (empty($duplicates)) {
    echo "✅ No se encontraron usuarios con múltiples licencias del mismo plan.\n\n";
} else {
    echo "⚠️  Se encontraron " . count($duplicates) . " usuario(s) con múltiples licencias del mismo plan:\n\n";

    foreach ($duplicates as $key => $group) {
        list($email, $planId) = explode('|', $key);
        $planName = $group[0]['plan_name'];

        echo "Usuario: {$email} - Plan: {$planName}\n";
        echo "  Tiene " . count($group) . " licencias:\n";
        foreach ($group as $lic) {
            echo "    - {$lic['license_key']} (Order: " . ($lic['last_order_id'] ?: 'NULL') . ") - {$lic['created_at']}\n";
        }
        echo "\n";
    }

    echo "📝 NOTA: Es CORRECTO que un usuario tenga múltiples licencias del mismo plan\n";
    echo "         si tiene múltiples pedidos (ej: renovaciones, nuevas compras).\n";
    echo "         Cada licencia debe estar asociada a su pedido específico (last_order_id).\n\n";
}

echo "==========================================================\n";
