<?php
/**
 * Dashboard Stats Backend
 * 
 * @version 4.0
 */

define('API_ACCESS', true);
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';

Auth::require();

$action = $_GET['action'] ?? 'stats';

$db = Database::getInstance();

switch ($action) {
    case 'stats':
        // Estadísticas generales
        $stats = [];
        
        // Licencias activas
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses WHERE status = 'active'");
        $stats['active_licenses'] = $result['count'];
        
        // Total de licencias
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "licenses");
        $stats['total_licenses'] = $result['count'];
        
        // Tokens usados hoy
        $today = date('Y-m-d');
        $result = $db->fetchOne("SELECT SUM(tokens_total) as total FROM " . DB_PREFIX . "usage_tracking WHERE DATE(created_at) = ?", [$today]);
        $stats['tokens_today'] = $result['total'] ?? 0;
        
        // Webhooks últimas 24h
        $since = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM " . DB_PREFIX . "webhook_logs WHERE received_at >= ?", [$since]);
        $stats['webhooks_24h'] = $result['count'];
        
        Response::success($stats);
        break;
        
    case 'recent_syncs':
        // Últimas 10 sincronizaciones
        $sql = "SELECT sl.*, l.license_key 
                FROM " . DB_PREFIX . "sync_logs sl
                LEFT JOIN " . DB_PREFIX . "licenses l ON sl.license_id = l.id
                ORDER BY sl.created_at DESC
                LIMIT 10";
        
        $syncs = $db->fetchAll($sql);
        Response::success($syncs);
        break;
        
    case 'expiring_licenses':
        // Licencias que expiran en los próximos 7 días
        $in7days = date('Y-m-d H:i:s', strtotime('+7 days'));
        $sql = "SELECT * FROM " . DB_PREFIX . "licenses 
                WHERE status = 'active' 
                AND period_ends_at <= ? 
                ORDER BY period_ends_at ASC
                LIMIT 10";
        
        $licenses = $db->fetchAll($sql, [$in7days]);
        Response::success($licenses);
        break;
        
    default:
        Response::error('Unknown action', 400);
}
