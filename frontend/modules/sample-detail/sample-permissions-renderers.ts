import type {
  SampleDetailNamespace,
  SamplePermissionRow,
  SamplePermissionSummaryRow,
} from '../../types/sample-detail-globals';

const SampleDetail = window.SampleDetail as SampleDetailNamespace | undefined;

if (window.App && SampleDetail) {
  const esc = window.App.escapeHtml;
  const fmt = window.App.fmt;
  const formatUtc = window.App.formatUtc;

  SampleDetail.createPermissionsRenderers = (elements, helpers) => {
    const {
      permSummaryList,
      permSummaryEmpty,
      permDetailBody,
      permTilesEl,
      permFilterBucket,
      permUnknownList,
    } = elements;
    const {
      bucketLabel,
      bucketDisplay,
      isUnknown,
      knownBadge,
      ruleLabel,
    } = helpers;

    function renderPermTiles(rows: SamplePermissionRow[]): void {
      if (!permTilesEl) return;
      const counts = new Map<string, number>();
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

    function populateBucketFilter(rows: SamplePermissionRow[]): void {
      if (!(permFilterBucket instanceof HTMLSelectElement)) return;
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

    function renderUnknownList(rows: SamplePermissionRow[]): void {
      if (!permUnknownList) return;
      const unknowns = rows.filter(isUnknown);
      if (unknowns.length === 0) {
        permUnknownList.textContent = 'None.';
        return;
      }

      const counts = new Map<string, number>();
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

    function renderPermSummary(rows: SamplePermissionSummaryRow[]): void {
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

    function renderPermDetail(rows: SamplePermissionRow[]): void {
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

    return {
      renderPermTiles,
      populateBucketFilter,
      renderUnknownList,
      renderPermSummary,
      renderPermDetail,
    };
  };
}
