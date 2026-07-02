<?php
// app/views/permissions_google.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Google Permissions';
$catalogUrl = api_url('android_permission_google_catalog.php');
$lovUrl = api_url('android_permission_lov.php');
?>

<!-- Anchors: backend decides truth; catalog pages are read-only. -->
<h1>Google Permissions</h1>
<p class="muted page-helper">
    Read-only validation surface for Google-defined permission families, including GMS / Play Services cases. Use it to confirm backend coverage before recording a Google-defined workflow decision.
</p>
<div id="perm-google-page" style="display:none;"
     data-catalog-endpoint="<?= h($catalogUrl) ?>"
     data-lov-endpoint="<?= h($lovUrl) ?>"
     data-page-size="<?= (int)DEFAULT_PAGE_SIZE ?>"></div>

<section class="perm-section">
    <div class="detail-card">
        <div class="detail-card-title">Google reference catalog</div>
        <div class="muted">Use this catalog to avoid repeating known Google / Play Services decisions during evidence-backed triage.</div>
        <div class="filters" style="margin: 12px 0 0;">
            <div class="filter-field" style="min-width: 220px;">
                <label for="perm-google-search">Search</label>
                <input id="perm-google-search" type="search" placeholder="Permission or namespace" />
            </div>
            <div class="filter-field" style="min-width: 180px;">
                <label for="perm-google-namespace">Namespace</label>
                <input id="perm-google-namespace" type="search" placeholder="com.google.*" />
            </div>
            <div class="filter-field" style="min-width: 140px;">
                <label>&nbsp;</label>
                <button class="btn" id="perm-google-search-btn" type="button">Search</button>
            </div>
        </div>
    </div>
</section>

<div class="table-scroll" style="margin-top: 16px;">
    <table class="table" id="perm-google-table">
        <thead>
            <tr>
                <th>Permission</th>
                <th>Bucket</th>
                <th>Historical samples</th>
                <th>First seen</th>
                <th>Last seen</th>
            </tr>
        </thead>
        <tbody id="perm-google-body">
            <tr>
                <td colspan="5" class="muted">Loading Google permissions...</td>
            </tr>
        </tbody>
    </table>
</div>
<div class="muted" style="margin-top: 8px;">
    Counts represent distinct observed samples (COUNT(DISTINCT sample_id)).
</div>
<div class="filters-note muted" id="perm-google-meta">--</div>
<div class="health-error" id="perm-google-error"></div>
