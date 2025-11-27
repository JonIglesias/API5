<?php
/**
 * Dashboard Module
 * 
 * @version 4.0
 */
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Overview of your API system</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid" id="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Active Licenses</div>
        <div class="stat-value" id="stat-active-licenses">-</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Total Licenses</div>
        <div class="stat-value" id="stat-total-licenses">-</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Tokens Used Today</div>
        <div class="stat-value" id="stat-tokens-today">-</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Webhooks Received (24h)</div>
        <div class="stat-value" id="stat-webhooks-24h">-</div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Synchronizations</h3>
        <a href="?module=sync" class="btn btn-sm btn-primary">View All</a>
    </div>
    
    <div id="recent-syncs">
        <div class="loading">Loading recent synchronizations</div>
    </div>
</div>

<!-- Expiring Licenses -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Expiring Soon</h3>
        <a href="?module=licenses" class="btn btn-sm btn-primary">View All</a>
    </div>
    
    <div id="expiring-licenses">
        <div class="loading">Loading expiring licenses</div>
    </div>
</div>

<script src="modules/dashboard/dashboard.js"></script>
