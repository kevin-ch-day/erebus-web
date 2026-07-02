<?php
// app/views/permissions_oem_permissions.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'OEM Permissions';
$permissionsUrl = api_url('android_permission_oem_permissions.php');
$lovUrl = api_url('android_permission_lov.php');
?>

<!-- Anchors: backend decides truth; catalog pages are read-only until workflow is wired. -->
<h1>OEM Permissions</h1>
<p class="muted page-helper">
    Read-only permission-level review surface for OEM namespaces. Use it to inspect vendor-specific current evidence and ledger context before jumping into single-permission Review.
</p>
<div id="perm-oem-page" style="display:none;"
     data-permissions-endpoint="<?= h($permissionsUrl) ?>"
     data-lov-endpoint="<?= h($lovUrl) ?>"
     data-page-size="<?= (int)DEFAULT_PAGE_SIZE ?>"></div>

<section class="perm-section">
    <div class="detail-card">
        <div class="detail-card-title">OEM permission review queue</div>
        <div class="muted">Use this surface to reduce repeated OEM triage and identify the next vendor permission that needs evidence-backed review.</div>
        <div class="filters" style="margin: 12px 0 0;">
            <div class="filter-field" style="min-width: 220px;">
                <label for="perm-oem-search">Search</label>
                <input id="perm-oem-search" type="search" placeholder="Permission or vendor namespace" />
            </div>
            <div class="filter-field" style="min-width: 180px;">
                <label for="perm-oem-status">Triage status</label>
                <select id="perm-oem-status">
                    <option value="">All</option>
                </select>
            </div>
            <div class="filter-field" style="min-width: 140px;">
                <label>&nbsp;</label>
                <button class="btn" id="perm-oem-search-btn" type="button">Search</button>
            </div>
        </div>
    </div>
</section>

<div class="table-scroll" style="margin-top: 16px;">
    <table class="table" id="perm-oem-table">
        <thead>
            <tr>
                <th>Permission</th>
                <th>Namespace</th>
                <th>Status</th>
                <th>Samples observed</th>
                <th>Last seen</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="perm-oem-body">
            <tr>
                <td colspan="6" class="muted">Loading OEM permissions...</td>
            </tr>
        </tbody>
    </table>
</div>
<div class="muted" style="margin-top: 8px;">
    Counts represent distinct samples (COUNT(DISTINCT sample_id)).
</div>
<div class="filters-note muted" id="perm-oem-meta">--</div>
<div class="health-error" id="perm-oem-error"></div>
