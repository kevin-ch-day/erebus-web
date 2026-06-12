<?php
// app/views/sample_detail.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';

$title = 'Sample Detail';

$sampleId = trim((string)($_GET['sample_id'] ?? ''));
$sha256 = trim((string)($_GET['sha256'] ?? ''));
$detailApiUrl = api_url('sample_detail.php');
$updateApiUrl = api_url('sample_update.php');
$pageScripts = [
    'assets/js/modules/sample_detail/sample_detail_formatters.js',
    'assets/js/modules/sample_detail/sample_summary_renderer.js',
    'assets/js/modules/sample_detail/sample_permissions_csv.js',
    'assets/js/modules/sample_detail/sample_permissions_renderers.js',
    'assets/js/modules/sample_detail/sample_permissions_controller.js',
    'assets/js/modules/sample_detail/sample_metadata_editor_modal.js',
    'assets/js/modules/sample_detail/sample_detail_surface.js',
    'assets/js/pages/sample_detail_page.js',
];
?>

<section class="sample-detail-header sample-detail-shell">
    <div class="sample-detail-header-main">
        <div class="eyebrow">Threat Workspace</div>
        <div class="sample-detail-context-note">Inspection surface only. Label authority lives on Label Surfaces. Queue follow-up lives on Repair Queue.</div>
        <h1 class="sample-detail-title" id="sample-header-title">Sample Detail</h1>
        <div class="sample-detail-subtitle muted" id="sample-header-subtitle">Loading sample identity and workflow state.</div>
    </div>
    <div class="sample-detail-header-actions">
        <a class="btn" href="<?= h(page_url('malware_samples')) ?>">Back to Malware Samples</a>
        <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => 'android'])) ?>">Repair Queue</a>
        <a class="btn" href="<?= h(page_url('label_surfaces')) ?>">Label Surfaces</a>
        <button class="btn btn-muted" type="button" id="sample-reload">Reload sample</button>
    </div>
    <div class="sample-inspection-strip" id="sample-inspection-strip">
        <div class="sample-inspection-tile">
            <div class="sample-inspection-label">Platform</div>
            <div class="sample-inspection-value" id="sample-header-platform">--</div>
        </div>
        <div class="sample-inspection-tile">
            <div class="sample-inspection-label">VT status</div>
            <div class="sample-inspection-value" id="sample-header-vt-status">--</div>
        </div>
        <div class="sample-inspection-tile">
            <div class="sample-inspection-label">Alignment</div>
            <div class="sample-inspection-value" id="sample-header-alignment">--</div>
        </div>
        <div class="sample-inspection-tile">
            <div class="sample-inspection-label">Last analysis</div>
            <div class="sample-inspection-value" id="sample-header-last-analysis">--</div>
        </div>
        <div class="sample-inspection-tile sample-inspection-tile-wide">
            <div class="sample-inspection-label">SHA-256</div>
            <div class="sample-inspection-value mono" id="sample-header-sha256">--</div>
        </div>
    </div>
</section>
<div id="sample-detail-page" style="display:none;"
     data-detail-endpoint="<?= h($detailApiUrl) ?>"
     data-update-endpoint="<?= h($updateApiUrl) ?>"
     data-perm-summary-endpoint="<?= h(api_url('android_permissions_summary.php')) ?>"
     data-perm-detail-endpoint="<?= h(api_url('android_permissions_detail.php')) ?>"
     data-sample-id="<?= h($sampleId) ?>"
     data-sha256="<?= h($sha256) ?>"></div>

<section class="section-shell sample-detail-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Identity</h2>
            <p class="muted">Catalog identity, hashes, and direct lookup links for this sample.</p>
        </div>
    </div>
    <div class="sample-summary" id="sample-summary">
        <div class="sample-summary-title">Loading sample...</div>
        <div class="sample-summary-meta muted">--</div>
    </div>

    <section class="perm-section" id="sample-interpretation-section">
        <div class="section-shell-header" style="margin-top: 18px;">
            <div>
                <h2 class="section-shell-title">Erebus Interpretation</h2>
                <p class="muted">Current catalog classification and VT signal context for this sample.</p>
            </div>
        </div>
        <div class="detail-grid sample-detail-grid" id="sample-core-grid"></div>
    </section>

    <section class="perm-section" id="sample-ops-section">
        <div class="section-shell-header" style="margin-top: 18px;">
            <div>
                <h2 class="section-shell-title">VirusTotal</h2>
            </div>
        </div>
        <div class="detail-grid sample-detail-grid" id="sample-ops-grid"></div>
    </section>
</section>

<section class="perm-section sample-detail-shell" id="sample-platform-section">
    <div class="section-shell-header" style="margin-top: 18px;">
        <div>
            <h2 class="section-shell-title">Processing Context</h2>
        </div>
    </div>
    <div class="notice info" id="sample-platform-note" style="display:none;"></div>
    <div class="detail-grid sample-detail-grid" id="sample-platform-grid"></div>
</section>

<section class="sample-detail-shell" id="android-permissions-section">
    <div class="section-shell-header" style="margin-top: 18px;">
        <div>
            <h2 class="section-shell-title">Platform Evidence</h2>
            <p class="muted">Platform-native metadata and enrichment surfaces appear here when Erebus has them.</p>
        </div>
    </div>
    <div class="detail-grid sample-detail-grid" id="sample-platform-evidence-grid"></div>
    <div id="android-permissions-workflow">
        <div class="perm-header">
            <div class="perm-meta">
                <div class="perm-meta-title">Permission state</div>
                <div class="muted" id="perm-taxonomy">Taxonomy: --</div>
                <div class="muted" id="perm-run-link"></div>
            </div>
            <div class="perm-actions">
                <button class="btn btn-muted" type="button" id="perm-reload">Reload permissions</button>
                <button class="btn" type="button" id="perm-export">Export CSV</button>
            </div>
        </div>
        <div class="perm-pipeline" id="perm-pipeline">
            <div class="perm-stage" data-stage="extract">Extract</div>
            <div class="perm-stage" data-stage="classify">Normalize & Classify</div>
            <div class="perm-stage" data-stage="persist">Persist</div>
            <div class="perm-stage" data-stage="summarize">Summarize</div>
        </div>
        <div class="notice info" id="perm-status-note" style="display:none;"></div>
        <div class="muted" id="perm-non-android" style="display:none;">No Android permissions for this sample.</div>
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Bucket summary</div>
                <div class="perm-tiles" id="perm-tiles"></div>
                <div class="perm-filters">
                    <div class="filter-field">
                        <label for="perm-filter-bucket">Bucket</label>
                        <select id="perm-filter-bucket">
                            <option value="">All buckets</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="perm-filter-known">Known</label>
                        <select id="perm-filter-known">
                            <option value="">All</option>
                            <option value="known">Known</option>
                            <option value="unknown">Unknown</option>
                        </select>
                    </div>
                </div>
                <div id="perm-summary-list"></div>
                <div class="muted" id="perm-summary-empty" style="display:none;">No permissions observed.</div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Unknown permission review</div>
                <div id="perm-unknown-list" class="muted">--</div>
            </div>
        </div>

        <div class="table-scroll">
            <table class="table" id="perm-detail-table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Classification</th>
                        <th>Bucket</th>
                        <th>Known</th>
                        <th>Rule fired</th>
                        <th>Observed at</th>
                    </tr>
                </thead>
                <tbody id="perm-detail-body">
                    <tr>
                        <td colspan="6" class="muted">Loading permissions...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="health-error" id="perm-error"></div>
    </div>
</section>

<section class="perm-section sample-detail-shell" id="sample-advanced-section">
    <details>
        <summary class="muted">Show advanced diagnostics</summary>
        <div class="detail-grid sample-detail-grid" id="sample-advanced-grid" style="margin-top: 8px;"></div>
    </details>
</section>

<section class="perm-section sample-detail-shell" id="sample-error-section" style="display:none;">
    <div class="section-shell-header" style="margin-top: 18px;">
        <div>
            <h2 class="section-shell-title">Last Error</h2>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table" id="sample-error-table">
            <thead>
                <tr>
                    <th>HTTP status</th>
                    <th>Error category</th>
                    <th>Error message</th>
                    <th>Last attempt</th>
                </tr>
            </thead>
            <tbody id="sample-error-body">
                <tr>
                    <td colspan="4" class="muted">No error details.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<div class="health-error" id="sample-error"></div>

<div class="modal-backdrop" id="sample-edit-modal" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-title">Edit sample metadata</div>
                <div class="muted" id="sample-edit-meta">--</div>
            </div>
            <button class="modal-close" type="button" id="sample-edit-close">Close</button>
        </div>
        <div class="modal-body">
            <div class="modal-grid">
                <div class="modal-section">
                    <div class="detail-card-title">Sample info</div>
                    <div class="detail-row">
                        <div class="detail-label">Sample ID</div>
                        <div class="detail-value" id="sample-edit-id">--</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">SHA-256</div>
                        <div class="detail-value mono" id="sample-edit-sha">--</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Package name</div>
                        <div class="detail-value mono" id="sample-edit-package">--</div>
                    </div>
                </div>
                <div class="modal-section">
                    <div class="detail-card-title">Catalog fields</div>
                    <div class="modal-field">
                        <label for="sample-edit-label">Label</label>
                        <input id="sample-edit-label" type="text" />
                    </div>
                    <div class="modal-field">
                        <label for="sample-edit-family">Family</label>
                        <input id="sample-edit-family" type="text" />
                    </div>
                    <div class="modal-field">
                        <label for="sample-edit-primary">Primary</label>
                        <input id="sample-edit-primary" type="text" />
                    </div>
                    <div class="modal-field">
                        <label for="sample-edit-subtype">Subtype</label>
                        <input id="sample-edit-subtype" type="text" />
                    </div>
                    <div class="muted" style="font-size: 12px;">
                        Updates apply to the catalog record only. Authority-aware readiness pages may still hold, reinterpret, or exclude rows based on governed family/type policy.
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="muted" id="sample-edit-status">--</div>
            <button class="btn btn-primary" type="button" id="sample-edit-save">Save changes</button>
        </div>
    </div>
</div>
