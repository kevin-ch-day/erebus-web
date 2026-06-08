(() => {
  const App = window.App || {};

  App.escapeHtml = (value) => {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  };

  App.fmt = (value, fallback = '--') => {
    if (value === null || value === undefined || value === '') return fallback;
    return String(value);
  };

  App.parseUtcToMs = (value) => {
    if (!value) return null;
    const raw = String(value).trim();
    if (!raw) return null;
    const cleaned = raw.replace(/\s+UTC$/i, '');
    let iso = cleaned;
    if (/^\d{4}-\d{2}-\d{2}$/.test(cleaned)) {
      iso = cleaned + 'T00:00:00';
    } else if (/\d{4}-\d{2}-\d{2}\s+\d/.test(cleaned)) {
      iso = cleaned.replace(' ', 'T');
    }
    if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) {
      iso += 'Z';
    }
    const ms = Date.parse(iso);
    return Number.isNaN(ms) ? null : ms;
  };

  const tzFormatters = new Map();

  App.getDisplayTz = () => {
    const tz = document.documentElement.dataset.displayTz || 'UTC';
    return tz || 'UTC';
  };

  App.getSecondaryTz = () => {
    const tz = document.documentElement.dataset.secondaryTz || '';
    return tz || '';
  };

  function getFormatter(timeZone, includeSeconds = false) {
    const key = `${timeZone}:${includeSeconds ? 's' : 'm'}`;
    if (tzFormatters.has(key)) return tzFormatters.get(key);
    let formatter = null;
    const options = {
      timeZone,
      month: 'short',
      day: '2-digit',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      second: includeSeconds ? '2-digit' : undefined,
      hour12: true,
    };
    try {
      formatter = new Intl.DateTimeFormat('en-US', options);
    } catch (_) {
      formatter = new Intl.DateTimeFormat('en-US', { ...options, timeZone: 'UTC' });
    }
    tzFormatters.set(key, formatter);
    return formatter;
  }

  App.formatUtc = (value, options = {}) => {
    const ms = App.parseUtcToMs(value);
    if (ms === null) return App.fmt(value);
    const tz = options.timeZone || App.getDisplayTz();
    const includeSeconds = options.includeSeconds === true;
    const formatter = getFormatter(tz, includeSeconds);
    const parts = formatter.formatToParts(new Date(ms));
    const map = {};
    parts.forEach((part) => {
      map[part.type] = part.value;
    });
    const time = includeSeconds
      ? `${map.hour}:${map.minute}:${map.second}`
      : `${map.hour}:${map.minute}`;
    const dayPeriod = map.dayPeriod ? ` ${map.dayPeriod}` : '';
    return `${map.month} ${map.day} ${map.year} ${time}${dayPeriod}`;
  };

  App.copyText = (value) => {
    if (!value) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).catch(() => {});
      return;
    }

    const temp = document.createElement('textarea');
    temp.value = value;
    temp.style.position = 'fixed';
    temp.style.opacity = '0';
    document.body.appendChild(temp);
    temp.select();
    try {
      document.execCommand('copy');
    } catch (_) {
      // no-op
    }
    document.body.removeChild(temp);
  };

  App.pageBaseUrl = () => {
    const current = new URL(window.location.href);
    const pathname = current.pathname || '';
    if (pathname.endsWith('/index.php')) {
      return pathname;
    }
    if (pathname.endsWith('/')) {
      return pathname + 'index.php';
    }
    return 'index.php';
  };

  App.pageUrl = (page, params = {}) => {
    const url = new URL(App.pageBaseUrl(), window.location.origin);
    if (page) {
      url.searchParams.set('p', String(page));
    }
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') {
        return;
      }
      url.searchParams.set(String(key), String(value));
    });
    const current = new URL(window.location.href);
    return url.pathname + url.search + (url.hash || '') + (current.origin === url.origin ? '' : '');
  };

  App.currentPageUrl = () => {
    return window.location.pathname + window.location.search + window.location.hash;
  };

  App.appendQueryParam = (url, key, value) => {
    if (!url) return '';
    const target = new URL(url, window.location.href);
    target.searchParams.set(String(key), String(value));
    return target.pathname + target.search + target.hash;
  };

  App.parseJsonResponse = async (res) => {
    const raw = await res.text();
    let body = null;
    try {
      body = JSON.parse(raw);
    } catch (_) {
      return { ok: false, status: res.status, error: 'Non-JSON response', raw };
    }
    return { ok: true, status: res.status, body, raw };
  };

  App.normalizePayload = (body) => {
    if (!body || typeof body !== 'object') {
      return { ok: false, error: 'Empty response', code: 'ERR_EMPTY' };
    }
    if (body.ok === false) {
      return {
        ok: false,
        error: body.error || 'Request failed',
        code: body.code || 'ERR_REQUEST',
        data: body.data || null,
      };
    }
    if (body.ok === true && body.data !== undefined) {
      return { ok: true, data: body.data, meta: body.meta || {} };
    }
    return { ok: true, data: body, meta: body.meta || {} };
  };

  App.fetchJson = async (url, options = {}) => {
    let res = null;
    try {
      res = await fetch(url, { cache: 'no-store', ...options });
    } catch (err) {
      return {
        ok: false,
        status: 0,
        error: err && err.message ? err.message : 'Network error',
        raw: '',
      };
    }
    const parsed = await App.parseJsonResponse(res);
    if (!parsed.ok) {
      return parsed;
    }
    const { body, raw } = parsed;
    if (!body || body.ok !== true) {
      return {
        ok: false,
        status: res.status,
        error: body && body.error ? String(body.error) : 'Request failed',
        body,
        raw,
      };
    }

    return { ok: true, status: res.status, body, raw };
  };

  App.fetchPayload = async (url, options = {}) => {
    const started = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
    let res = null;
    try {
      res = await fetch(url, { cache: 'no-store', ...options });
    } catch (err) {
      const ended = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
      return {
        ok: false,
        status: 0,
        error: err && err.message ? err.message : 'Network error',
        raw: '',
        elapsedMs: Math.max(0, Math.round(ended - started)),
      };
    }
    const parsed = await App.parseJsonResponse(res);
    if (!parsed.ok) {
      const ended = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
      return { ...parsed, elapsedMs: Math.max(0, Math.round(ended - started)) };
    }
    const normalized = App.normalizePayload(parsed.body);
    const ended = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
    return {
      ...normalized,
      status: res.status,
      raw: parsed.raw,
      elapsedMs: Math.max(0, Math.round(ended - started)),
    };
  };

  App.postJson = async (url, payload, options = {}) => {
    return App.fetchJson(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
      },
      body: JSON.stringify(payload),
      ...options,
    });
  };

  App.postForm = async (url, payload, options = {}) => {
    const body = new URLSearchParams(payload || {});
    return App.fetchJson(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        ...(options.headers || {}),
      },
      body: body.toString(),
      ...options,
    });
  };

  App.clearPageError = (el) => {
    if (!(el instanceof HTMLElement)) return;
    el.innerHTML = '';
    el.style.display = 'none';
  };

  App.renderPageError = (el, details = {}) => {
    if (!(el instanceof HTMLElement)) return;
    const title = App.escapeHtml(details.title || 'Page unavailable');
    const summary = App.escapeHtml(details.summary || 'The page could not load the required data.');
    const detail = details.detail ? `<div><strong>Detail:</strong> ${App.escapeHtml(details.detail)}</div>` : '';
    const status = details.status ? `<div><strong>Status:</strong> ${App.escapeHtml(details.status)}</div>` : '';
    const hint = details.hint ? `<div>${App.escapeHtml(details.hint)}</div>` : '';
    const primaryAction = details.primaryActionHref && details.primaryActionLabel
      ? `<a class="btn btn-primary" href="${App.escapeHtml(details.primaryActionHref)}">${App.escapeHtml(details.primaryActionLabel)}</a>`
      : '';
    const secondaryAction = details.secondaryActionHref && details.secondaryActionLabel
      ? `<a class="btn" href="${App.escapeHtml(details.secondaryActionHref)}">${App.escapeHtml(details.secondaryActionLabel)}</a>`
      : '';

    el.innerHTML = `
      <div class="notice error">
        <strong>${title}</strong>
        <div style="margin-top:8px;">${summary}</div>
        ${detail ? `<div style="margin-top:8px;">${detail}</div>` : ''}
        ${status ? `<div style="margin-top:6px;">${status}</div>` : ''}
        ${hint ? `<div style="margin-top:8px;">${hint}</div>` : ''}
        ${(primaryAction || secondaryAction) ? `<div class="flow-inline" style="margin-top:12px;">${primaryAction}${secondaryAction}</div>` : ''}
      </div>
    `;
    el.style.display = '';
  };

  window.App = App;
})();
