(() => {
  const root = document.getElementById('perm-evidence-page');
  if (!root || !window.App || !window.PermissionIntel) return;

  const endpoint = root.dataset.evidenceEndpoint || '';
  const initialPermission = root.dataset.permission || '';
  const returnUrl = root.dataset.returnUrl || App.pageUrl('permissions_triage');
  const initialLimit = Number(root.dataset.limit || '25') || 25;
  if (!endpoint) return;

  const inputEl = document.getElementById('perm-evidence-input');
  const limitEl = document.getElementById('perm-evidence-limit');
  const searchBtn = document.getElementById('perm-evidence-search');
  const reviewLinkEl = document.getElementById('perm-evidence-review-link');
  const titleEl = document.getElementById('perm-evidence-title');
  const metaEl = document.getElementById('perm-evidence-meta');
  const bodyEl = document.getElementById('perm-evidence-body');
  const errorEl = document.getElementById('perm-evidence-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const { formatCount, formatUtcDual } = PermissionIntel;
  const formatUtc = App.formatUtc;
  const pageUrl = App.pageUrl;
  const displayTz = App.getDisplayTz();
  const secondaryTz = App.getSecondaryTz();

  function updateReviewLink(permission) {
    if (!reviewLinkEl) return;
    const perm = String(permission || '').trim();
    if (!perm) {
      reviewLinkEl.setAttribute('href', '#');
      reviewLinkEl.setAttribute('aria-disabled', 'true');
      reviewLinkEl.classList.add('btn-disabled');
      return;
    }
    reviewLinkEl.setAttribute('href', pageUrl('permissions_review', {
      permission: perm,
      return: returnUrl,
    }));
    reviewLinkEl.removeAttribute('aria-disabled');
    reviewLinkEl.classList.remove('btn-disabled');
  }

  function updateUrl(permission, limit) {
    const url = new URL(window.location.href);
    if (permission) {
      url.searchParams.set('permission', permission);
    } else {
      url.searchParams.delete('permission');
    }
    if (limit) {
      url.searchParams.set('limit', String(limit));
    } else {
      url.searchParams.delete('limit');
    }
    window.history.replaceState({}, '', url.toString());
  }

  async function loadEvidence(permission) {
    const perm = String(permission || '').trim();
    const limit = limitEl ? Number(limitEl.value || '25') || 25 : initialLimit;
    updateReviewLink(perm);
    if (!perm) {
      titleEl.textContent = 'Select a permission to view evidence';
      metaEl.textContent = '--';
      bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No evidence loaded yet.</td></tr>';
      return;
    }

    updateUrl(perm, limit);
    errorEl.textContent = '';
    titleEl.textContent = `Evidence for ${perm}`;
    metaEl.textContent = 'Loading evidence...';
    bodyEl.innerHTML = '<tr><td colspan="7" class="muted">Loading evidence...</td></tr>';

    try {
      const res = await App.fetchJson(endpoint + '?permission=' + encodeURIComponent(perm) + '&limit=' + encodeURIComponent(limit));
      if (!res.ok) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="muted">Evidence unavailable. This can occur if VT is on hold or no OK runs exist.</td></tr>';
        metaEl.textContent = `Error: ${fmt(res.error)}`;
        return;
      }

      const rows = res.body.data || [];
      const meta = res.body.meta || {};
      const totalCount = Number(meta.total_count || rows.length || 0);
      metaEl.textContent = `Showing ${formatCount(rows.length)} of ${formatCount(totalCount)} evidence rows (limit ${limit})`;
      bodyEl.innerHTML = '';
      if (!Array.isArray(rows) || rows.length === 0) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No evidence available yet. This can occur if VT is on hold or no OK runs exist.</td></tr>';
        return;
      }

      rows.forEach((row) => {
        const sampleId = fmt(row.sample_id, '');
        const sampleLabel = fmt(row.sample_label, '');
        const sampleText = sampleLabel ? `${sampleLabel} (#${sampleId})` : `Sample #${sampleId}`;
        const sampleUrl = sampleId ? pageUrl('sample', { sample_id: sampleId }) : '';
        const triageStatus = String(row.triage_status || '').toLowerCase();
        const triageBadge = triageStatus === 'oem_candidate'
          ? ' <span class="badge warn">OEM candidate</span>'
          : '';
        const runId = fmt(row.run_id || row.last_run_id, '--');
        const observed = formatUtcDual(row.observed_at_utc || row.observed_at);
        const packageName = fmt(row.package_name || row.android_package_name);
        const runStatus = row.stopped_reason ? `Stopped: ${fmt(row.stopped_reason)}` :
          (Number(row.ok_count || 0) > 0 ? 'OK' : '--');
        const taxonomy = fmt(row.perm_taxonomy_version, '--');
        const observedPrimary = observed.primary || formatUtc(row.observed_at_utc || row.observed_at);
        const observedSecondary = observed.secondary;
        const observedHtml = observedSecondary
          ? `<div>${esc(observedPrimary)} (${esc(displayTz)})</div><div class="muted">${esc(observedSecondary)} (${esc(secondaryTz)})</div>`
          : `<div>${esc(observedPrimary)} (${esc(displayTz)})</div>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${sampleUrl
            ? `<a class="table-link" href="${esc(sampleUrl)}">${esc(sampleText)}</a>${triageBadge}`
            : `${esc(sampleText)}${triageBadge}`}</td>
          <td class="mono">${esc(packageName)}</td>
          <td class="mono">${esc(runId)}</td>
          <td>${esc(runStatus)}</td>
          <td class="mono">${esc(taxonomy)}</td>
          <td>${esc(fmt(row.vt_status_code))}</td>
          <td>${observedHtml}</td>
        `;
        bodyEl.appendChild(tr);
      });
    } catch (e) {
      bodyEl.innerHTML = '<tr><td colspan="7" class="muted">Evidence unavailable. This can occur if VT is on hold or no OK runs exist.</td></tr>';
      metaEl.textContent = `Error: ${fmt(e && e.message ? e.message : String(e))}`;
    }
  }

  if (searchBtn) {
    searchBtn.addEventListener('click', () => loadEvidence(inputEl ? inputEl.value : ''));
  }
  if (inputEl) {
    inputEl.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        loadEvidence(inputEl.value);
      }
    });
  }
  if (limitEl) {
    limitEl.addEventListener('change', () => loadEvidence(inputEl ? inputEl.value : ''));
  }

  if (reviewLinkEl) {
    reviewLinkEl.addEventListener('click', (event) => {
      if (reviewLinkEl.getAttribute('aria-disabled') === 'true') {
        event.preventDefault();
      }
    });
  }

  if (initialPermission) {
    loadEvidence(initialPermission);
  } else if (limitEl) {
    limitEl.value = String(initialLimit);
  }
  updateReviewLink(initialPermission);
})();
