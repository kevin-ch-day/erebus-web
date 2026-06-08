(() => {
  if (!window.App || !window.SampleDetail) return;
  const SampleDetail = window.SampleDetail;
  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;

  SampleDetail.createPermissionsController = (elements, endpoints) => {
    const {
      permSummaryList,
      permSummaryEmpty,
      permDetailBody,
      permErrorEl,
      permNonAndroid,
      permTilesEl,
      permFilterBucket,
      permFilterKnown,
      permUnknownList,
      permStatusNote,
      permPipeline,
      permExportBtn,
    } = elements;
    const { permSummaryEndpoint, permDetailEndpoint } = endpoints;

    let permissionRows = [];
    let summaryRows = [];
    let exportSampleId = '';

    function fmtUtc(value) {
      return value ? formatUtc(value) : '--';
    }

    function bucketLabel(value) {
      const raw = fmt(value, '').trim();
      return raw === '' ? 'Unclassified yet' : raw;
    }

    function ruleLabel(value) {
      const raw = fmt(value, '').trim();
      return raw === '' ? 'n/a' : raw;
    }

    function bucketDisplay(row) {
      const bucket = bucketLabel(row.bucket);
      const classification = String(row.classification || '').toUpperCase();
      const isUnknownBucket = bucket.toUpperCase().includes('UNKNOWN');
      if (classification === 'OEM' && isUnknownBucket) {
        return {
          label: 'OEM (Unreviewed)',
          title: 'OEM permissions are intentionally treated as UNKNOWN until reviewed.',
        };
      }
      return { label: bucket, title: '' };
    }

    function isUnknown(row) {
      const bucketRaw = fmt(row.bucket, '').trim();
      if (bucketRaw === '') return true;
      return bucketRaw.toUpperCase() === 'UNKNOWN';
    }

    function knownBadge(row) {
      if (isUnknown(row)) {
        const classification = String(row.classification || '').toUpperCase();
        if (classification === 'OEM') {
          return '<span class="badge warn" title="OEM permissions remain unknown until reviewed.">OEM (Unreviewed)</span>';
        }
        return '<span class="badge warn">Unknown</span>';
      }
      return '<span class="badge ok">Known</span>';
    }

    function setPipelineStage(stage, state) {
      if (!permPipeline) return;
      const el = permPipeline.querySelector('[data-stage="' + stage + '"]');
      if (!el) return;
      el.classList.remove('stage-done', 'stage-warn', 'stage-pending');
      el.classList.add(state);
    }

    function updatePipelineStages() {
      const hasPerms = permissionRows.length > 0;
      const hasKnown = permissionRows.some((row) => !isUnknown(row));
      const hasSummary = summaryRows.length > 0;

      setPipelineStage('extract', hasPerms ? 'stage-done' : 'stage-pending');
      if (!hasPerms) {
        setPipelineStage('classify', 'stage-pending');
      } else if (hasKnown) {
        setPipelineStage('classify', 'stage-done');
      } else {
        setPipelineStage('classify', 'stage-warn');
      }
      setPipelineStage('persist', hasPerms ? 'stage-done' : 'stage-pending');
      setPipelineStage('summarize', hasSummary ? 'stage-done' : 'stage-pending');
    }

    function renderPermTiles(rows) {
      if (!permTilesEl) return;
      const counts = new Map();
      rows.forEach((row) => {
        const label = bucketLabel(row.bucket);
        counts.set(label, (counts.get(label) || 0) + 1);
      });

      const entries = Array.from(counts.entries()).sort((a, b) => b[1] - a[1]);
      permTilesEl.innerHTML = '';
      if (entries.length === 0) {
        permTilesEl.innerHTML = '<div class="muted">No buckets yet.</div>';
        return;
      }

      entries.forEach(([label, count]) => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'perm-tile';
        tile.setAttribute('data-bucket', label);
        tile.innerHTML = `
          <div class="perm-tile-label">${esc(label)}</div>
          <div class="perm-tile-count">${esc(String(count))}</div>
        `;
        permTilesEl.appendChild(tile);
      });
    }

    function populateBucketFilter(rows) {
      if (!permFilterBucket) return;
      const current = permFilterBucket.value;
      const buckets = Array.from(new Set(rows.map((row) => bucketLabel(row.bucket)))).sort();
      permFilterBucket.innerHTML = '<option value="">All buckets</option>';
      buckets.forEach((bucket) => {
        const opt = document.createElement('option');
        opt.value = bucket;
        opt.textContent = bucket;
        permFilterBucket.appendChild(opt);
      });
      if (current && buckets.includes(current)) {
        permFilterBucket.value = current;
      }
    }

    function renderUnknownList(rows) {
      if (!permUnknownList) return;
      const unknowns = rows.filter(isUnknown);
      if (unknowns.length === 0) {
        permUnknownList.textContent = 'None.';
        return;
      }

      const counts = new Map();
      unknowns.forEach((row) => {
        const key = fmt(row.permission_string, '');
        if (!key) return;
        counts.set(key, (counts.get(key) || 0) + 1);
      });
      const top = Array.from(counts.entries()).sort((a, b) => b[1] - a[1]).slice(0, 12);
      permUnknownList.innerHTML = top.map(([perm, count]) => {
        return `<div class="detail-row"><div class="detail-label mono">${esc(perm)}</div><div class="detail-value">${esc(String(count))}</div></div>`;
      }).join('');
    }

    function applyFilters(rows) {
      const bucket = permFilterBucket ? permFilterBucket.value : '';
      const known = permFilterKnown ? permFilterKnown.value : '';

      return rows.filter((row) => {
        const matchesBucket = bucket === '' || bucketLabel(row.bucket) === bucket;
        const isKnown = !isUnknown(row);
        const matchesKnown = known === '' || (known === 'known' && isKnown) || (known === 'unknown' && !isKnown);
        return matchesBucket && matchesKnown;
      });
    }

    function renderPermSummary(rows) {
      summaryRows = rows;
      if (!permSummaryList || !permSummaryEmpty) return;
      permSummaryList.innerHTML = '';
      if (!Array.isArray(rows) || rows.length === 0) {
        permSummaryEmpty.style.display = 'block';
        return;
      }
      permSummaryEmpty.style.display = 'none';
      rows.forEach((row) => {
        const bucket = bucketLabel(row.bucket);
        const rule = ruleLabel(row.rule_fired);
        const count = row.perm_count ?? 0;
        const rowEl = document.createElement('div');
        rowEl.className = 'detail-row';
        rowEl.innerHTML = `<div class="detail-label">${esc(bucket)} / ${esc(rule)}</div><div class="detail-value">${esc(String(count))}</div>`;
        permSummaryList.appendChild(rowEl);
      });
    }

    function renderPermDetail(rows) {
      if (!permDetailBody) return;
      permDetailBody.innerHTML = '';
      if (!Array.isArray(rows) || rows.length === 0) {
        permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">No permissions observed.</td></tr>';
        return;
      }

      rows.forEach((row) => {
        const bucket = bucketDisplay(row);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="cell-wrap mono">${esc(fmt(row.permission_string))}</td>
          <td>${esc(fmt(row.classification))}</td>
          <td${bucket.title ? ` title="${esc(bucket.title)}"` : ''}>${esc(bucket.label)}</td>
          <td>${knownBadge(row)}</td>
          <td>${esc(ruleLabel(row.rule_fired))}</td>
          <td>${esc(row.observed_at ? formatUtc(row.observed_at) : '--')}</td>
        `;
        permDetailBody.appendChild(tr);
      });
    }

    function updatePermissionTable() {
      const rows = applyFilters(permissionRows);
      renderPermDetail(rows);
    }

    function toCsv(rows) {
      const headers = ['permission_string', 'classification', 'bucket', 'known', 'rule_fired', 'observed_at'];
      const escapeCsv = (value) => {
        const raw = String(value ?? '');
        if (raw === '') return '';
        if (/["\n,]/.test(raw)) {
          return '"' + raw.replaceAll('"', '""') + '"';
        }
        return raw;
      };
      const lines = [headers.join(',')];
      rows.forEach((row) => {
        const line = [
          fmt(row.permission_string, ''),
          fmt(row.classification, ''),
          bucketLabel(row.bucket),
          isUnknown(row) ? 'Unknown' : 'Known',
          ruleLabel(row.rule_fired),
          fmt(row.observed_at, ''),
        ].map(escapeCsv).join(',');
        lines.push(line);
      });
      return lines.join('\n');
    }

    function downloadCsv(filename, contents) {
      const blob = new Blob([contents], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }

    function setStatus(message) {
      if (!permStatusNote) return;
      if (!message) {
        permStatusNote.style.display = 'none';
        permStatusNote.textContent = '';
        return;
      }
      permStatusNote.textContent = message;
      permStatusNote.style.display = 'block';
    }

    function resetEmpty() {
      permissionRows = [];
      summaryRows = [];
      if (permSummaryEmpty) permSummaryEmpty.style.display = 'block';
      if (permDetailBody) {
        permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">No permissions available.</td></tr>';
      }
      renderPermTiles(permissionRows);
      populateBucketFilter(permissionRows);
      renderUnknownList(permissionRows);
      updatePipelineStages();
      if (permExportBtn) permExportBtn.disabled = true;
    }

    async function loadPermissions(sampleIdValue) {
      if (!sampleIdValue || !permSummaryEndpoint || !permDetailEndpoint) {
        resetEmpty();
        return;
      }

      try {
        if (permErrorEl) permErrorEl.textContent = '';
        if (permSummaryEmpty) permSummaryEmpty.style.display = 'none';
        if (permSummaryList) permSummaryList.innerHTML = '';
        if (permDetailBody) {
          permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">Loading permissions...</td></tr>';
        }
        if (permFilterBucket) permFilterBucket.value = '';
        if (permFilterKnown) permFilterKnown.value = '';
        const summaryUrl = permSummaryEndpoint + '?sample_id=' + encodeURIComponent(sampleIdValue);
        const detailUrl = permDetailEndpoint + '?sample_id=' + encodeURIComponent(sampleIdValue);
        const [summaryRes, detailRes] = await Promise.all([
          App.fetchJson(summaryUrl),
          App.fetchJson(detailUrl),
        ]);

        if (!summaryRes.ok) {
          summaryRows = [];
          if (permSummaryEmpty) permSummaryEmpty.style.display = 'block';
          if (permErrorEl) {
            permErrorEl.innerHTML = '<pre>Permissions summary error.\n\nHTTP ' + summaryRes.status + '\nerror: ' +
              esc(summaryRes.error) + '</pre>';
          }
        } else {
          renderPermSummary(summaryRes.body.data || []);
        }

        if (!detailRes.ok) {
          permissionRows = [];
          if (permErrorEl) {
            permErrorEl.innerHTML = '<pre>Permissions detail error.\n\nHTTP ' + detailRes.status + '\nerror: ' +
              esc(detailRes.error) + '</pre>';
          }
          renderPermTiles(permissionRows);
          populateBucketFilter(permissionRows);
          renderUnknownList(permissionRows);
          renderPermDetail(permissionRows);
        } else {
          permissionRows = Array.isArray(detailRes.body.data) ? detailRes.body.data : [];
          renderPermTiles(permissionRows);
          populateBucketFilter(permissionRows);
          renderUnknownList(permissionRows);
          updatePermissionTable();
        }
        updatePipelineStages();
        if (permExportBtn) permExportBtn.disabled = permissionRows.length === 0;
      } catch (e) {
        if (permErrorEl) {
          permErrorEl.innerHTML = '<pre>Permissions error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
        }
        permissionRows = [];
        summaryRows = [];
        renderPermTiles(permissionRows);
        populateBucketFilter(permissionRows);
        renderUnknownList(permissionRows);
        renderPermDetail(permissionRows);
        updatePipelineStages();
        if (permExportBtn) permExportBtn.disabled = true;
      }
    }

    function showNonAndroid() {
      setStatus('');
      if (permNonAndroid) permNonAndroid.style.display = 'block';
      if (permSummaryEmpty) permSummaryEmpty.style.display = 'none';
      if (permDetailBody) {
        permDetailBody.innerHTML = '<tr><td colspan="6" class="muted">Not an Android sample.</td></tr>';
      }
      permissionRows = [];
      summaryRows = [];
      renderPermTiles(permissionRows);
      populateBucketFilter(permissionRows);
      renderUnknownList(permissionRows);
      updatePipelineStages();
      if (permExportBtn) permExportBtn.disabled = true;
    }

    if (permFilterBucket) {
      permFilterBucket.addEventListener('change', updatePermissionTable);
    }
    if (permFilterKnown) {
      permFilterKnown.addEventListener('change', updatePermissionTable);
    }
    if (permTilesEl) {
      permTilesEl.addEventListener('click', (event) => {
        const target = event.target;
        const tile = target && target.closest ? target.closest('.perm-tile') : null;
        if (!tile || !permFilterBucket) return;
        const bucket = tile.getAttribute('data-bucket') || '';
        permFilterBucket.value = bucket;
        updatePermissionTable();
      });
    }
    if (permExportBtn) {
      permExportBtn.addEventListener('click', () => {
        if (!permissionRows.length) return;
        const rows = applyFilters(permissionRows);
        const filename = `permissions_sample_${exportSampleId || 'unknown'}.csv`;
        downloadCsv(filename, toCsv(rows));
      });
    }

    return {
      loadPermissions,
      setStatus,
      showNonAndroid,
      resetEmpty,
      setExportSampleId: (value) => { exportSampleId = String(value || ''); },
    };
  };
})();
