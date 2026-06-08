<?php
// app/views/permissions_aosp.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'AOSP Permissions';
$catalogUrl = api_url('android_permission_aosp_catalog.php');
$lovUrl = api_url('android_permission_lov.php');
$pageScripts = [
    'assets/js/permission_intel_shared.js',
    'assets/js/pages/permissions_aosp_page.js',
];
?>

<!-- Anchors: backend decides truth; catalog pages are read-only. -->
<h1>AOSP Permissions</h1>
<p class="muted page-helper">
    Read-only validation surface for backend-classified AOSP permissions. Use it to confirm baseline coverage before marking an active UNKNOWN as an AOSP gap; do not treat this page as a triage queue.
</p>
<div id="perm-aosp-page" style="display:none;"
     data-catalog-endpoint="<?= h($catalogUrl) ?>"
     data-lov-endpoint="<?= h($lovUrl) ?>"
     data-page-size="<?= (int)DEFAULT_PAGE_SIZE ?>"></div>

<section class="perm-section">
    <div class="detail-card">
        <div class="detail-card-title">AOSP reference catalog</div>
        <div class="muted">Use this catalog to avoid repeating baseline AOSP decisions during evidence-backed triage.</div>
        <div class="filters" style="margin: 12px 0 0;">
            <div class="filter-field" style="min-width: 220px;">
                <label for="perm-dict-search">Search</label>
                <input id="perm-dict-search" type="search" placeholder="Permission or namespace" />
            </div>
            <div class="filter-field" style="min-width: 180px;">
                <label for="perm-dict-bucket">Bucket</label>
                <select id="perm-dict-bucket">
                    <option value="">All</option>
                </select>
            </div>
            <div class="filter-field" style="min-width: 140px;">
                <label>&nbsp;</label>
                <button class="btn" id="perm-aosp-search" type="button">Search</button>
            </div>
        </div>
    </div>
</section>

<div class="table-scroll" style="margin-top: 16px;">
    <table class="table" id="perm-aosp-table">
        <thead>
            <tr>
                <th>Permission</th>
                <th>Bucket</th>
                <th>Historical samples</th>
                <th>First seen</th>
                <th>Last seen</th>
            </tr>
        </thead>
        <tbody id="perm-aosp-body">
            <tr>
                <td colspan="5" class="muted">Loading AOSP permissions...</td>
            </tr>
        </tbody>
    </table>
</div>
<div class="muted" style="margin-top: 8px;">
    Counts represent distinct observed samples (COUNT(DISTINCT sample_id)).
</div>
<div class="filters-note muted" id="perm-aosp-meta">--</div>
<div class="health-error" id="perm-aosp-error"></div>
