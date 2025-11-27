<?php
$webhooks = $db->query("SELECT * FROM " . DB_PREFIX . "webhook_logs ORDER BY received_at DESC LIMIT 100");

$result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "webhook_logs");
$total = $result[0]['total'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "webhook_logs WHERE processed = 1");
$processed = $result[0]['total'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "webhook_logs WHERE processed = 0");
$failed = $result[0]['total'] ?? 0;
?>
<style>
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.stat-card .label { font-size: 14px; color: #6c757d; }
.stat-card .value { font-size: 32px; font-weight: 700; margin-top: 10px; }
.info-box { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin-bottom: 20px; }
.webhooks-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; }
th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; }
td { padding: 12px; border-bottom: 1px solid #dee2e6; }
tr:hover { background: #f8f9fa; }
.badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-failed { background: #f8d7da; color: #721c24; }
</style>

<div class="info-box">
    <strong>Endpoint para webhooks:</strong><br>
    POST <?= API_BASE_URL ?>webhooks/woocommerce
</div>

<div class="stats-row">
    <div class="stat-card">
        <div class="label">Total Recibidos</div>
        <div class="value"><?= $total ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Procesados</div>
        <div class="value" style="color: #28a745;"><?= $processed ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Fallidos</div>
        <div class="value" style="color: #dc3545;"><?= $failed ?></div>
    </div>
</div>

<div class="webhooks-table">
    <h3 style="padding: 20px; margin: 0; border-bottom: 1px solid #dee2e6;">Últimos Webhooks</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Evento</th>
                <th>Subscription ID</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($webhooks)): ?>
                <tr><td colspan="4" style="text-align: center; padding: 40px; color: #6c757d;">No hay webhooks</td></tr>
            <?php else: ?>
                <?php foreach ($webhooks as $wh): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($wh['received_at'])) ?></td>
                        <td><?= htmlspecialchars($wh['event_type']) ?></td>
                        <td><?= htmlspecialchars($wh['woo_subscription_id'] ?? 'N/A') ?></td>
                        <td><span class="badge badge-<?= $wh['processed'] ? 'success' : 'failed' ?>"><?= $wh['processed'] ? 'Procesado' : 'Fallido' ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
