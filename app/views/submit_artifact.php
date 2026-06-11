<?php
// app/views/submit_artifact.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/artifact_sources.php';

$title = 'Submit Artifact';
$ingestEndpoint = api_url('artifact_ingest_queue.php');
?>

<div id="submit-artifact-page" style="display:none;"
     data-ingest-endpoint="<?= h($ingestEndpoint) ?>"></div>

<section class="page-hero">
    <div class="page-hero-media">
        <img src="<?= h(app_url('assets/img/artifact_hash_lookup.png')) ?>"
            alt="Artifact intake" />
    </div>
    <div class="page-hero-body">
        <div class="eyebrow">Threat Workspace</div>
        <div class="page-kicker">Manual queue submission</div>
        <h1 class="page-hero-title">Submit Artifact</h1>
        <p class="page-hero-lede muted">
            Bulk addition of artifacts for ingestion into the collection.
        </p>
    </div>
</section>

<section class="perm-section">
    <div class="detail-card">
        <div class="detail-card-title">Bulk intake form</div>
        <div class="muted">
            Each row will be queued into <code>malware_artifact_ingest_queue</code>.
        </div>
        <div class="detail-card-title" style="margin-top: 14px;">CSV paste/import</div>
        <div class="muted">
            Supports generic artifact CSV rows and Beacon/LAMDA-style exact-review packet columns.
            Beacon/LAMDA imports default to <code>artifact_source=csv</code> and keep batch metadata in the row fields.
        </div>
        <div style="margin-top: 10px;">
            <textarea id="artifact-csv-input" rows="8" style="width:100%; font-family:monospace;"
                placeholder="artifact_hash,artifact_name,artifact_family,artifact_category,artifact_subtype,artifact_source,artifact_source_other&#10;aaaaaaaa...,Sample,Family,Android,APK,csv,LAMDA batch"></textarea>
        </div>
        <div class="landing-actions" style="margin-top: 10px;">
            <button class="btn" type="button" id="artifact-import-csv">Import CSV into table</button>
            <button class="btn" type="button" id="artifact-clear-csv">Clear CSV</button>
        </div>
        <div class="muted" style="margin-top: 8px;">
            Recognized Beacon/LAMDA columns include <code>sha256</code>, <code>family_raw</code>, <code>sample_label_candidate</code>,
            <code>family_label_candidate</code>, <code>source_batch_label_candidate</code>, <code>year_month</code>, and <code>review_reason</code>.
        </div>
        <div class="notice info" id="artifact-bulk-status" style="display:none; margin-top: 10px;"></div>
        <div class="notice error" id="artifact-bulk-error" style="display:none; margin-top: 10px;"></div>
        <div class="table-scroll" style="margin-top: 12px;">
            <table class="table" id="artifact-bulk-table">
                <thead>
                    <tr>
                        <th>Artifact hash (MD5/SHA-1/SHA-256)</th>
                        <th>Artifact name</th>
                        <th>Artifact family</th>
                        <th>Artifact category</th>
                        <th>Artifact subtype</th>
                        <th>Artifact source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <tr>
                            <td>
                                <input type="text" class="artifact-hash-input"
                                       placeholder="md5, sha1, or sha256..." />
                                <div class="muted" style="margin-top: 4px;">
                                    Detected: <span class="artifact-hash-hint">--</span>
                                </div>
                                <div class="muted">Expected length: 32 / 40 / 64 hex chars.</div>
                            </td>
                            <td><input type="text" placeholder="Sample label" /></td>
                            <td><input type="text" placeholder="Family" /></td>
                            <td><input type="text" placeholder="Android, IoT, ..." /></td>
                            <td><input type="text" placeholder="APK, firmware, ..." /></td>
                            <td>
                                <select class="artifact-source-select">
                                    <?= render_artifact_source_options() ?>
                                </select>
                                <input type="text" class="artifact-source-other" placeholder="Other source (optional)" />
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div class="landing-actions" style="margin-top: 12px;">
            <button class="btn" type="button" id="artifact-add-row">Add row</button>
            <button class="btn btn-primary" type="button" id="artifact-queue-bulk">Queue artifacts</button>
        </div>
        <div class="muted" style="margin-top: 8px;">
            Bulk queue submit is available. CSV paste/import now fills the table; direct file upload is still not implemented.
        </div>
    </div>
</section>
