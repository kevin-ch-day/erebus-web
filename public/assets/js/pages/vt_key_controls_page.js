(() => {
  const root = document.getElementById('vt-key-ops');
  if (!root || !window.App) return;

  const statusEndpoint = root.dataset.statusEndpoint || '';
  const healthEndpoint = root.dataset.healthEndpoint || '';
  const fallbackStatusEndpoint = root.dataset.fallbackStatusEndpoint || '';
  const fallbackHealthEndpoint = root.dataset.fallbackHealthEndpoint || '';

  const eligibleEl = document.getElementById('vt-ops-eligible');
  const coolingEl = document.getElementById('vt-ops-cooling');
  const leasedEl = document.getElementById('vt-ops-leased');
  const supportsLeasesEl = document.getElementById('vt-ops-supports-leases');
  const lastRefreshEl = document.getElementById('vt-ops-last-refresh');
  const refreshBtn = document.getElementById('vt-ops-refresh');

  const unavailableEl = document.getElementById('vt-ops-unavailable');
  const bodyEl = document.getElementById('vt-ops-body');
  const metaEl = document.getElementById('vt-ops-meta');
  const errorEl = document.getElementById('vt-ops-error');
  const bannerEl = document.getElementById('vt-ops-api-banner');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const readonly = App.readonly || {};
  const formatUtcFixed = readonly.formatUtcFixed || ((value) => App.formatUtc(value, { timeZone: 'UTC' }));
  const fetchPayload = readonly.fetchPayload || App.fetchPayload;
  const offlineBanner = 'Enhanced API unavailable — showing DB-backed read-only state.';

  function setUnavailable(message) {
    if (!unavailableEl) return;
    unavailableEl.textContent = message;
    unavailableEl.style.display = 'block';
  }

  function clearUnavailable() {
    if (!unavailableEl) return;
    unavailableEl.textContent = '';
    unavailableEl.style.display = 'none';
  }

  function setBannerApiRunning() {
    if (!bannerEl) return;
    bannerEl.className = 'notice info';
    bannerEl.textContent = 'API server: RUNNING';
  }

  function setBannerFallback() {
    if (!bannerEl) return;
    bannerEl.className = 'notice warn';
    bannerEl.textContent = offlineBanner;
  }

  function shouldFallback(res) {
    if (!res || res.ok) return false;
    if (res.code === 'ERR_SCHEMA_MISSING') return false;
    if (res.status === 0 || res.status === 404 || res.status >= 500) return true;
    if (res.error === 'Non-JSON response') return true;
    return false;
  }

  function parseUtcMs(value) {
    return App.parseUtcToMs ? App.parseUtcToMs(value) : null;
  }

  function quotaResetUtc(quotaDay) {
    if (!quotaDay) return null;
    const parts = String(quotaDay).split('-');
    if (parts.length !== 3) return null;
    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);
    if (!year || !month || !day) return null;
    const resetMs = Date.UTC(year, month - 1, day + 1, 0, 0, 0);
    return Number.isNaN(resetMs) ? null : resetMs;
  }

  function formatRemaining(ms) {
    if (ms === null || ms === undefined || Number.isNaN(ms)) return '--';
    const total = Math.max(0, Math.floor(ms / 1000));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    return `${hours}h ${minutes}m`;
  }

  function deriveStatus(row, supportsLeases) {
    const enabled = row.is_enabled === 1 || row.is_enabled === '1' || row.is_enabled === true;
    const visible = row.is_visible === 1 || row.is_visible === '1' || row.is_visible === true;
    if (!enabled) return 'disabled';
    if (!visible) return 'hidden';

    const leaseMs = supportsLeases && row.lease_until_utc ? parseUtcMs(row.lease_until_utc) : null;
    if (leaseMs && leaseMs > Date.now()) return 'leased';

    const cooldownMs = row.cooldown_until_utc ? parseUtcMs(row.cooldown_until_utc) : null;
    if (cooldownMs && cooldownMs > Date.now()) return 'cooling';

    return 'eligible';
  }

  function statusBadge(status) {
    switch (status) {
      case 'eligible':
        return '<span class="badge ok">Eligible</span>';
      case 'cooling':
        return '<span class="badge warn">Cooling</span>';
      case 'leased':
        return '<span class="badge warn">Leased</span>';
      case 'hidden':
        return '<span class="badge muted">Hidden</span>';
      case 'disabled':
        return '<span class="badge err">Disabled</span>';
      default:
        return `<span class="badge muted">${esc(status)}</span>`;
    }
  }

  function renderRows(keys, supportsLeases) {
    if (!bodyEl) return;
    bodyEl.innerHTML = '';
    if (!Array.isArray(keys) || keys.length === 0) {
      bodyEl.innerHTML = '<tr><td colspan="14" class="muted">No keys reported.</td></tr>';
      return;
    }

    keys.forEach((row) => {
      const keyId = row.api_key_id ? `#${row.api_key_id}` : '--';
      const last6 = row.last6 ? String(row.last6) : '';
      const keyLabel = last6 ? `${keyId} / ${last6}` : keyId;

      const enabled = row.is_enabled === 1 || row.is_enabled === '1' || row.is_enabled === true;
      const visible = row.is_visible === 1 || row.is_visible === '1' || row.is_visible === true;
      const enabledLabel = row.is_enabled === null || row.is_enabled === undefined ? '--' : (enabled ? 'Yes' : 'No');
      const visibleLabel = row.is_visible === null || row.is_visible === undefined ? '--' : (visible ? 'Yes' : 'No');

      const quotaLimit = row.daily_quota_limit;
      const quotaUsed = row.daily_quota_used;
      const isUnlimited = Number(quotaLimit) === 0;
      const requestsLeft = isUnlimited
        ? 'Unlimited'
        : (quotaLimit !== null && quotaLimit !== undefined && quotaUsed !== null && quotaUsed !== undefined
          ? Math.max(0, Number(quotaLimit) - Number(quotaUsed))
          : '--');

      const resetMs = !isUnlimited ? quotaResetUtc(row.quota_day_utc) : null;
      const resetUtc = resetMs ? formatUtcFixed(new Date(resetMs).toISOString()) : (isUnlimited ? 'Unlimited' : '--');
      const remaining = resetMs ? formatRemaining(resetMs - Date.now()) : (isUnlimited ? 'Unlimited' : '--');
      const quotaLabel = isUnlimited
        ? `${esc(fmt(quotaUsed))}/Unlimited`
        : `${esc(fmt(quotaUsed))}/${esc(fmt(quotaLimit))}`;

      const cooldown = row.cooldown_until_utc ? formatUtcFixed(row.cooldown_until_utc) : '--';
      const last429 = row.last_429_at_utc ? formatUtcFixed(row.last_429_at_utc) : '--';
      const retryAfter = row.last_429_retry_after_seconds !== null && row.last_429_retry_after_seconds !== undefined
        ? String(row.last_429_retry_after_seconds)
        : '--';
      const rateCount = row.rate_limit_429_count !== null && row.rate_limit_429_count !== undefined
        ? String(row.rate_limit_429_count)
        : '--';

      const leaseUntil = supportsLeases && row.lease_until_utc ? formatUtcFixed(row.lease_until_utc) : '--';
      const leaseOwner = supportsLeases && row.lease_owner ? String(row.lease_owner) : '--';

      const status = deriveStatus(row, supportsLeases);

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="mono">${esc(keyLabel)}</td>
        <td>${statusBadge(status)}</td>
        <td>${esc(enabledLabel)}</td>
        <td>${esc(visibleLabel)}</td>
        <td>${esc(fmt(requestsLeft))}</td>
        <td>${quotaLabel}</td>
        <td>${esc(resetUtc)}</td>
        <td>${esc(remaining)}</td>
        <td>${esc(cooldown)}</td>
        <td>${esc(leaseUntil)}</td>
        <td>${esc(leaseOwner)}</td>
        <td>${esc(last429)}</td>
        <td>${esc(retryAfter)}</td>
        <td>${esc(rateCount)}</td>
      `;
      bodyEl.appendChild(tr);
    });
  }

  function renderMeta(payload) {
    if (!metaEl) return;
    const generated = payload && payload.generated_at_utc ? formatUtcFixed(payload.generated_at_utc) : '--';
    metaEl.textContent = `Last refresh: ${generated}`;
    if (lastRefreshEl) lastRefreshEl.textContent = generated;
  }

  async function loadData() {
    if (!statusEndpoint) return;
    if (errorEl) errorEl.textContent = '';
    if (bodyEl) {
      bodyEl.innerHTML = '<tr><td colspan="14" class="muted">Loading key ops...</td></tr>';
    }
    clearUnavailable();

    try {
      let statusRes = await fetchPayload(statusEndpoint);
      let usedFallback = false;
      if (shouldFallback(statusRes) && fallbackStatusEndpoint) {
        statusRes = await fetchPayload(fallbackStatusEndpoint);
        usedFallback = true;
      }

      let healthRes = healthEndpoint ? await fetchPayload(healthEndpoint) : { ok: false };
      if (shouldFallback(healthRes) && fallbackHealthEndpoint) {
        healthRes = await fetchPayload(fallbackHealthEndpoint);
        usedFallback = true;
      }

      if (!statusRes.ok) {
        if (usedFallback) {
          setBannerFallback();
        }
        if (statusRes.code === 'ERR_SCHEMA_MISSING' || statusRes.status === 404) {
          setUnavailable('Backend capability not enabled for VT key ops yet.');
          setBannerApiRunning();
          if (bodyEl) {
            bodyEl.innerHTML = '<tr><td colspan="14" class="muted">Not available.</td></tr>';
          }
          return;
        }
        const detail = statusRes.raw ? `\n\n${String(statusRes.raw).slice(0, 2000)}` : '';
        if (errorEl) {
          errorEl.innerHTML = '<pre>VT key ops API error.\n\nHTTP ' + statusRes.status + '\nerror: ' +
            esc(statusRes.error || 'Request failed') + detail + '</pre>';
        }
        return;
      }

      const statusPayload = statusRes.data || {};
      const healthPayload = healthRes.ok ? (healthRes.data || {}) : {};
      const supportsLeases = statusPayload.supports_leases === true;

      renderRows(statusPayload.keys || [], supportsLeases);
      renderMeta(statusPayload);

      if (usedFallback) {
        setBannerFallback();
      } else {
        setBannerApiRunning();
      }

      if (eligibleEl) eligibleEl.textContent = healthPayload.eligible_key_count !== undefined
        ? String(healthPayload.eligible_key_count)
        : '--';
      if (coolingEl) coolingEl.textContent = healthPayload.cooling_key_count !== undefined
        ? String(healthPayload.cooling_key_count)
        : '--';
      if (leasedEl) leasedEl.textContent = healthPayload.leased_key_count !== undefined
        ? String(healthPayload.leased_key_count)
        : '--';
      if (supportsLeasesEl) supportsLeasesEl.textContent = supportsLeases ? 'Yes' : 'No';
    } catch (e) {
      if (errorEl) {
        errorEl.innerHTML = '<pre>VT key ops API error:\n' + esc(e && e.message ? e.message : String(e)) + '</pre>';
      }
    }
  }

  if (refreshBtn) refreshBtn.addEventListener('click', loadData);
  loadData();
})();
