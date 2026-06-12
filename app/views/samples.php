<?php
// app/views/samples.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';

$title = "Malware Samples";
$queryStatus = trim((string)($_GET['status'] ?? ''));
$queryFamily = trim((string)($_GET['family'] ?? ''));
$queryFamilyAlignment = trim((string)($_GET['family_alignment'] ?? ''));
$querySearch = trim((string)($_GET['q'] ?? ''));
$queryColumns = trim((string)($_GET['columns'] ?? 'simple'));
if (!in_array($queryColumns, ['simple', 'detailed'], true)) {
    $queryColumns = 'simple';
}
$querySortBy = strtolower(trim((string)($_GET['sort_by'] ?? 'id')));
if (!in_array($querySortBy, ['id', 'label', 'family', 'alignment'], true)) {
    $querySortBy = 'id';
}
$querySortDir = strtolower(trim((string)($_GET['sort_dir'] ?? 'desc')));
if (!in_array($querySortDir, ['asc', 'desc'], true)) {
    $querySortDir = 'desc';
}
$queryPage = max(1, (int)($_GET['page'] ?? 1));
$queryPageSize = max(1, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
$samplesApiUrl = api_url('samples_list.php');
$pageScripts = [
    'assets/js/modules/samples/samples_query_builder.js',
    'assets/js/modules/samples/samples_table_renderer.js',
    'assets/js/pages/samples_page.js',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Threat Workspace</div>
        <div class="page-kicker">Sample lookup and workflow inventory</div>
        <h1 class="page-hero-title">Malware Samples</h1>
        <p class="page-hero-lede muted">
            Use this page for broad sample lookup, VT state review, and quick movement into taxonomy or dataset-governance work when a row needs deeper interpretation.
        </p>
        <div class="page-hero-actions">
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => 'android'])) ?>">Repair Queue</a>
            <a class="btn" href="<?= h(page_url('label_surfaces')) ?>">Label Surfaces</a>
            <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Type Benchmark</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Interpretation</h2>
        <p>Workflow truth comes from VT processing state. Family alignment here is visibility context only; governed label authority and benchmark interpretation live on Label Surfaces and Type Benchmark.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Inventory</div>
                <div class="hero-metric-value">Catalog + VT state</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Row authority</div>
                <div class="hero-metric-value">Label Surfaces</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Benchmark</div>
                <div class="hero-metric-value">Type Benchmark</div>
            </div>
        </div>
    </aside>
</section>
<div id="samples-page-root" style="display:none;"
     data-endpoint="<?= h($samplesApiUrl) ?>"
     data-detail-base="<?= h(page_url('sample')) ?>"
     data-stale-minutes="<?= (int)STALE_CLAIM_MINUTES ?>"
     data-default-page-size="<?= (int)DEFAULT_PAGE_SIZE ?>"
     data-default-columns="simple"
     data-default-sort-by="id"
     data-default-sort-dir="desc"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Filters</h2>
            <p class="muted">Use these filters to narrow the catalog slice before opening row-level authority or repair surfaces.</p>
        </div>
    </div>
    <div class="filters">
        <div class="filter-field">
            <label for="samples-search">Search</label>
            <input id="samples-search" type="search" placeholder="SHA256 or label" value="<?= htmlspecialchars($querySearch) ?>" />
        </div>
        <div class="filter-field">
            <label for="samples-family">Family</label>
            <input id="samples-family" type="search" placeholder="Family label" value="<?= htmlspecialchars($queryFamily) ?>" />
        </div>
        <div class="filter-field">
            <label for="samples-family-alignment">Family alignment</label>
            <select id="samples-family-alignment">
                <option value="">All alignment states</option>
                <option value="mismatch" <?= $queryFamilyAlignment === 'mismatch' ? 'selected' : '' ?>>Mismatch</option>
                <option value="signal_only" <?= $queryFamilyAlignment === 'signal_only' ? 'selected' : '' ?>>Signal only</option>
                <option value="catalog_only" <?= $queryFamilyAlignment === 'catalog_only' ? 'selected' : '' ?>>Catalog only</option>
                <option value="aligned" <?= $queryFamilyAlignment === 'aligned' ? 'selected' : '' ?>>Aligned</option>
                <option value="unlabeled" <?= $queryFamilyAlignment === 'unlabeled' ? 'selected' : '' ?>>Unlabeled</option>
                <option value="generic_label" <?= $queryFamilyAlignment === 'generic_label' ? 'selected' : '' ?>>Generic catalog label</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="samples-status">Status</label>
            <select id="samples-status">
                <option value="">All statuses</option>
                <?php
                $statusOptions = [
                    'NEW' => 'New',
                    'REANALYZE' => 'Reanalyze',
                    'PROCESSING' => 'Processing',
                    'LOOKED_UP' => 'Looked up',
                    'NO_DATA' => 'No data',
                    'ERROR' => 'Error',
                    'RETRY_WAIT' => 'Retry wait',
                    'DISABLED' => 'Disabled',
                ];
                foreach ($statusOptions as $opt => $label): ?>
                    <option value="<?= h($opt) ?>" <?= $queryStatus === $opt ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="samples-page-size">Page size</label>
            <select id="samples-page-size">
                <?php foreach ([50, 100, 200, 500] as $size): ?>
                    <option value="<?= (int)$size ?>" <?= $queryPageSize === $size ? 'selected' : '' ?>><?= (int)$size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="samples-columns">Columns</label>
            <select id="samples-columns">
                <option value="simple" <?= $queryColumns === 'simple' ? 'selected' : '' ?>>Compact</option>
                <option value="detailed" <?= $queryColumns === 'detailed' ? 'selected' : '' ?>>Detailed</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="samples-sort-by">Sort by</label>
            <select id="samples-sort-by">
                <option value="id" <?= $querySortBy === 'id' ? 'selected' : '' ?>>ID</option>
                <option value="label" <?= $querySortBy === 'label' ? 'selected' : '' ?>>Label</option>
                <option value="family" <?= $querySortBy === 'family' ? 'selected' : '' ?>>Family</option>
                <option value="alignment" <?= $querySortBy === 'alignment' ? 'selected' : '' ?>>Family alignment</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="samples-sort-dir">Direction</label>
            <select id="samples-sort-dir">
                <option value="desc" <?= $querySortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                <option value="asc" <?= $querySortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
            </select>
        </div>
        <div class="filter-field samples-filter-actions">
            <label>Actions</label>
            <div class="samples-filter-buttons">
                <button class="btn" id="samples-refresh" type="button">Refresh</button>
                <button class="btn btn-muted" id="samples-reset" type="button">Reset filters</button>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
        <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Sample Inventory</h2>
            <p class="muted">Broad sample table for catalog identity, family alignment context, and VT workflow posture.</p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table samples-table <?= $queryColumns === 'detailed' ? 'detailed' : 'simple' ?>">
        <thead>
            <tr>
                <th>
                    <button class="samples-sort-btn" type="button" data-sort="id" aria-label="Sort by ID">
                        ID
                        <span class="samples-sort-indicator"><?= $querySortBy === 'id' ? ($querySortDir === 'asc' ? '↑' : '↓') : '·' ?></span>
                    </button>
                </th>
                <th>
                    <button class="samples-sort-btn" type="button" data-sort="label" aria-label="Sort by Label">
                        Label
                        <span class="samples-sort-indicator"><?= $querySortBy === 'label' ? ($querySortDir === 'asc' ? '↑' : '↓') : '·' ?></span>
                    </button>
                </th>
                <th>
                    <button class="samples-sort-btn" type="button" data-sort="alignment" aria-label="Sort by Family Alignment">
                        Family alignment
                        <span class="samples-sort-indicator"><?= $querySortBy === 'alignment' ? ($querySortDir === 'asc' ? '↑' : '↓') : '·' ?></span>
                    </button>
                </th>
                <th>
                    <button class="samples-sort-btn" type="button" data-sort="family" aria-label="Sort by Family">
                        Family
                        <span class="samples-sort-indicator"><?= $querySortBy === 'family' ? ($querySortDir === 'asc' ? '↑' : '↓') : '·' ?></span>
                    </button>
                </th>
                <th>Primary</th>
                <th>SHA256 tail</th>
                <th class="col-vt">Malicious</th>
                <th class="col-vt">Suspicious</th>
                <th class="col-vt">Undetected</th>
                <th class="col-vt">Harmless</th>
                <th class="col-detail">Attempts</th>
                <th class="col-detail">Last attempt</th>
                <th class="col-detail">Last HTTP</th>
                <th class="col-detail">Last error</th>
                <th class="col-detail">Last run/key</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="samples-body">
            <tr>
                <td colspan="16" class="muted">Loading samples...</td>
            </tr>
        </tbody>
        </table>
    </div>

    <div class="table-footer">
        <div class="table-meta muted" id="samples-meta">--</div>
        <div class="table-controls">
            <button class="btn" id="samples-prev">Prev</button>
            <div class="table-page">
                Page <input id="samples-page" type="number" min="1" value="<?= (int)$queryPage ?>" /> / <span id="samples-pages">--</span>
            </div>
            <button class="btn" id="samples-next">Next</button>
        </div>
    </div>
</section>

<div class="health-error" id="samples-error"></div>
