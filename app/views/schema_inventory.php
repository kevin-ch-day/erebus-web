<?php
// app/views/schema_inventory.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Schema Inventory';
$endpoint = api_url('schema_inventory.php');
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Database</div>
        <div class="page-kicker">Read-only inventory</div>
        <h1 class="page-hero-title">Schema Inventory</h1>
        <p class="page-hero-lede muted">
            Web-side view of the known MariaDB surfaces Erebus Web expects across the primary and Permission Intel catalogs.
            Use this page to see what the app believes should exist, what is actually present, and which pages depend on each surface.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('schema_inventory')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
            <a class="btn" href="<?= h(page_url('admin_diagnostics')) ?>">Admin Diagnostics</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Why it matters</h2>
        <p>This closes a real gap in the web app: the backend already had a schema inventory API, but there was no page for operators to inspect it directly.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Mode</div>
                <div class="hero-metric-value">Read-only</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Source</div>
                <div class="hero-metric-value">MariaDB</div>
            </div>
        </div>
    </aside>
</section>

<div id="schema-inventory-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-refresh-seconds="60"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Inventory summary</h2>
            <p class="muted">High-level availability across the surfaces Erebus Web knows about.</p>
        </div>
        <div class="muted" id="schema-inventory-meta">Loading...</div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Catalog routing</div>
            <div class="detail-row">
                <div class="detail-label">Primary catalog</div>
                <div class="detail-value" id="schema-inventory-primary-db">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Permission Intel catalog</div>
                <div class="detail-value" id="schema-inventory-pi-db">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Split enabled</div>
                <div class="detail-value" id="schema-inventory-split">--</div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Availability</div>
            <div class="detail-row">
                <div class="detail-label">Known surfaces</div>
                <div class="detail-value" id="schema-inventory-total">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Available</div>
                <div class="detail-value" id="schema-inventory-available">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Missing surfaces</div>
                <div class="detail-value" id="schema-inventory-missing-surfaces">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Missing columns</div>
                <div class="detail-value" id="schema-inventory-missing-columns">--</div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Surface browser</h2>
            <p class="muted">Filter by availability, catalog role, or surface name. Missing columns are shown inline.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="flow-inline" style="gap: 12px; align-items: end; margin-bottom: 12px;">
            <label>
                <div class="muted">Search</div>
                <input id="schema-inventory-search" type="search" placeholder="Table, role, consumer page" />
            </label>
            <label>
                <div class="muted">Availability</div>
                <select id="schema-inventory-filter-availability">
                    <option value="all">All</option>
                    <option value="missing">Missing or incomplete</option>
                    <option value="available">Available only</option>
                </select>
            </label>
            <label>
                <div class="muted">Catalog role</div>
                <select id="schema-inventory-filter-role">
                    <option value="all">All</option>
                    <option value="primary">Primary</option>
                    <option value="permission_intel">Permission Intel</option>
                </select>
            </label>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Surface</th>
                        <th>Catalog</th>
                        <th>Kind</th>
                        <th>Analysis role</th>
                        <th>Status</th>
                        <th>Consumer pages</th>
                        <th>Missing columns</th>
                    </tr>
                </thead>
                <tbody id="schema-inventory-body">
                    <tr>
                        <td colspan="7" class="muted">Loading schema inventory...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="health-error" id="schema-inventory-error"></div>
