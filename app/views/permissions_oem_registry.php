<?php
// app/views/permissions_oem_registry.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'OEM Registry';
$registryUrl = api_url('android_permission_oem_registry.php');
$lovUrl = api_url('android_permission_lov.php');
$pageScripts = [
    'assets/js/permission_intel_shared.js',
    'assets/js/pages/permissions_oem_registry_page.js',
];
?>

<!-- Anchors: backend decides truth; catalog pages are read-only. -->
<h1>OEM Registry</h1>
<p class="muted page-helper">
    OEM-focused namespace posture view for vendor-specific permission surfaces. Use it to separate real OEM namespaces, launcher ecosystems, service namespaces, and SDK-style exceptions before promoting anything to OEM truth.
</p>
<div id="perm-oem-registry-page" style="display:none;"
     data-registry-endpoint="<?= h($registryUrl) ?>"
     data-lov-endpoint="<?= h($lovUrl) ?>"
     data-page-size="<?= (int)DEFAULT_PAGE_SIZE ?>"></div>

<section class="perm-section">
    <div class="detail-card">
        <div class="detail-card-title">OEM namespace review</div>
        <div class="muted">Use this registry to scope OEM review. Confirm permission-level evidence in Triage, Evidence, or Fusion before recording decisions.</div>
        <div class="filters" style="margin: 12px 0 0;">
            <div class="filter-field" style="min-width: 220px;">
                <label for="oem-search">Search vendor</label>
                <input id="oem-search" type="search" placeholder="com.samsung, com.huawei..." />
            </div>
            <div class="filter-field" style="min-width: 180px;">
                <label for="oem-class">Classification</label>
                <select id="oem-class">
                    <option value="">All</option>
                </select>
            </div>
            <div class="filter-field" style="min-width: 140px;">
                <label>&nbsp;</label>
                <button class="btn" id="oem-registry-search" type="button">Search</button>
            </div>
        </div>
    </div>
</section>

<div class="table-scroll" style="margin-top: 16px;">
    <table class="table" id="oem-registry-table">
        <thead>
            <tr>
                <th>Namespace</th>
                <th>Occurrences observed</th>
                <th>Unique perms</th>
                <th>Classification</th>
                <th>Review hint</th>
                <th>First seen</th>
                <th>Last seen</th>
            </tr>
        </thead>
        <tbody id="oem-registry-body">
            <tr>
                <td colspan="7" class="muted">Loading OEM registry...</td>
            </tr>
        </tbody>
    </table>
</div>
<div class="muted" style="margin-top: 8px;">
    Counts on this page reflect observed VT event volume by namespace in the current web contract. OEM classification is heuristic and prefix-based, but review hints now distinguish vendor permission spaces, vendor launcher/home ecosystems, vendor service namespaces, and known SDK-style exceptions. Non-OEM namespaces should be reviewed under the Anomalous or Expected filters, not treated as vendor truth.
    <span title="Occurrences on this page reflect VT event volume.">ⓘ</span>
</div>
<div class="filters-note muted" id="oem-registry-meta">--</div>
<div class="health-error" id="oem-registry-error"></div>
