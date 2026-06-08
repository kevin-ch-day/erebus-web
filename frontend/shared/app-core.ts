import type {
  AppJsonFailure,
  AppJsonSuccess,
  AppPageErrorOptions,
  AppPayloadFailure,
  AppPayloadSuccess,
  AppSurface,
  JsonRecord,
} from '../types/app-globals';

const tzFormatters = new Map<string, Intl.DateTimeFormat>();

const App: AppSurface = window.App || ({} as AppSurface);

App.escapeHtml = (value: unknown): string => {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
};

App.fmt = (value: unknown, fallback = '--'): string => {
  if (value === null || value === undefined || value === '') return fallback;
  return String(value);
};

App.parseUtcToMs = (value: unknown): number | null => {
  if (!value) return null;
  const raw = String(value).trim();
  if (!raw) return null;
  const cleaned = raw.replace(/\s+UTC$/i, '');
  let iso = cleaned;
  if (/^\d{4}-\d{2}-\d{2}$/.test(cleaned)) {
    iso = `${cleaned}T00:00:00`;
  } else if (/\d{4}-\d{2}-\d{2}\s+\d/.test(cleaned)) {
    iso = cleaned.replace(' ', 'T');
  }
  if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) {
    iso += 'Z';
  }
  const ms = Date.parse(iso);
  return Number.isNaN(ms) ? null : ms;
};

App.getDisplayTz = (): string => {
  const tz = document.documentElement.dataset.displayTz || 'UTC';
  return tz || 'UTC';
};

App.getSecondaryTz = (): string => {
  const tz = document.documentElement.dataset.secondaryTz || '';
  return tz || '';
};

function getFormatter(timeZone: string, includeSeconds = false): Intl.DateTimeFormat {
  const key = `${timeZone}:${includeSeconds ? 's' : 'm'}`;
  const cached = tzFormatters.get(key);
  if (cached) return cached;

  const options: Intl.DateTimeFormatOptions = {
    timeZone,
    month: 'short',
    day: '2-digit',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: includeSeconds ? '2-digit' : undefined,
    hour12: true,
  };

  let formatter: Intl.DateTimeFormat;
  try {
    formatter = new Intl.DateTimeFormat('en-US', options);
  } catch {
    formatter = new Intl.DateTimeFormat('en-US', { ...options, timeZone: 'UTC' });
  }
  tzFormatters.set(key, formatter);
  return formatter;
}

App.formatUtc = (value: unknown, options: { timeZone?: string; includeSeconds?: boolean } = {}): string => {
  const ms = App.parseUtcToMs(value);
  if (ms === null) return App.fmt(value);
  const tz = options.timeZone || App.getDisplayTz();
  const includeSeconds = options.includeSeconds === true;
  const formatter = getFormatter(tz, includeSeconds);
  const parts = formatter.formatToParts(new Date(ms));
  const map: Record<string, string> = {};
  parts.forEach((part) => {
    map[part.type] = part.value;
  });
  const time = includeSeconds
    ? `${map.hour}:${map.minute}:${map.second}`
    : `${map.hour}:${map.minute}`;
  const dayPeriod = map.dayPeriod ? ` ${map.dayPeriod}` : '';
  return `${map.month} ${map.day} ${map.year} ${time}${dayPeriod}`;
};

App.copyText = (value: string): void => {
  if (!value) return;
  if (navigator.clipboard?.writeText) {
    void navigator.clipboard.writeText(value).catch(() => {});
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
  } catch {
    // no-op
  }
  document.body.removeChild(temp);
};

App.pageBaseUrl = (): string => {
  const current = new URL(window.location.href);
  const pathname = current.pathname || '';
  if (pathname.endsWith('/index.php')) return pathname;
  if (pathname.endsWith('/')) return `${pathname}index.php`;
  return 'index.php';
};

App.pageUrl = (page: string, params: Record<string, unknown> = {}): string => {
  const url = new URL(App.pageBaseUrl(), window.location.origin);
  if (page) {
    url.searchParams.set('p', String(page));
  }
  Object.entries(params || {}).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;
    url.searchParams.set(String(key), String(value));
  });
  const current = new URL(window.location.href);
  return url.pathname + url.search + (url.hash || '') + (current.origin === url.origin ? '' : '');
};

App.currentPageUrl = (): string => {
  return window.location.pathname + window.location.search + window.location.hash;
};

App.appendQueryParam = (url: string, key: string, value: unknown): string => {
  if (!url) return '';
  const target = new URL(url, window.location.href);
  target.searchParams.set(String(key), String(value));
  return target.pathname + target.search + target.hash;
};

App.parseJsonResponse = async (res: Response): Promise<AppJsonSuccess | AppJsonFailure> => {
  const raw = await res.text();
  let body: unknown = null;
  try {
    body = JSON.parse(raw);
  } catch {
    return { ok: false, status: res.status, error: 'Non-JSON response', raw };
  }
  return { ok: true, status: res.status, body: body as JsonRecord, raw };
};

App.normalizePayload = (body: unknown) => {
  if (!body || typeof body !== 'object') {
    return { ok: false as const, error: 'Empty response', code: 'ERR_EMPTY' };
  }
  const candidate = body as JsonRecord & { ok?: boolean; error?: unknown; code?: unknown; data?: unknown; meta?: unknown };
  if (candidate.ok === false) {
    return {
      ok: false as const,
      error: typeof candidate.error === 'string' ? candidate.error : 'Request failed',
      code: typeof candidate.code === 'string' ? candidate.code : 'ERR_REQUEST',
      data: candidate.data ?? null,
    };
  }
  if (candidate.ok === true && candidate.data !== undefined) {
    return { ok: true as const, data: candidate.data, meta: (candidate.meta as JsonRecord) || {} };
  }
  return { ok: true as const, data: candidate, meta: (candidate.meta as JsonRecord) || {} };
};

App.fetchJson = async (url: string, options: RequestInit = {}): Promise<AppJsonSuccess | AppJsonFailure> => {
  let res: Response;
  try {
    res = await fetch(url, { cache: 'no-store', ...options });
  } catch (err) {
    return {
      ok: false,
      status: 0,
      error: err instanceof Error ? err.message : 'Network error',
      raw: '',
    };
  }

  const parsed = await App.parseJsonResponse(res);
  if (!parsed.ok) {
    return parsed;
  }

  const { body, raw } = parsed;
  const candidate = body as JsonRecord & { ok?: boolean; error?: unknown };
  if (!body || candidate.ok !== true) {
    return {
      ok: false,
      status: res.status,
      error: typeof candidate?.error === 'string' ? candidate.error : 'Request failed',
      body,
      raw,
    };
  }

  return { ok: true, status: res.status, body, raw };
};

App.fetchPayload = async (url: string, options: RequestInit = {}): Promise<AppPayloadSuccess | AppPayloadFailure> => {
  const started = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
  let res: Response;
  try {
    res = await fetch(url, { cache: 'no-store', ...options });
  } catch (err) {
    const ended = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
    return {
      ok: false,
      status: 0,
      error: err instanceof Error ? err.message : 'Network error',
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
  } as AppPayloadSuccess | AppPayloadFailure;
};

App.postJson = async (url: string, payload: unknown, options: RequestInit = {}): Promise<AppJsonSuccess | AppJsonFailure> => {
  return App.fetchJson(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...((options.headers as Record<string, string> | undefined) || {}),
    },
    body: JSON.stringify(payload),
    ...options,
  });
};

App.postForm = async (url: string, payload: Record<string, string>, options: RequestInit = {}): Promise<AppJsonSuccess | AppJsonFailure> => {
  const body = new URLSearchParams(payload || {});
  return App.fetchJson(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      ...((options.headers as Record<string, string> | undefined) || {}),
    },
    body: body.toString(),
    ...options,
  });
};

App.clearPageError = (el: HTMLElement | null): void => {
  if (!el) return;
  el.innerHTML = '';
  el.hidden = true;
};

App.renderPageError = (el: HTMLElement | null, options: AppPageErrorOptions): void => {
  if (!el) return;

  const title = App.escapeHtml(options.title || 'Page error');
  const summary = options.summary ? App.escapeHtml(options.summary) : '';
  const detail = options.detail ? App.escapeHtml(options.detail) : '';
  const hint = options.hint ? App.escapeHtml(options.hint) : '';
  const status = options.status && options.status > 0 ? String(options.status) : '';
  const code = options.code ? App.escapeHtml(options.code) : '';
  const raw = options.raw ? String(options.raw).slice(0, 1800) : '';
  const rawEscaped = raw ? App.escapeHtml(raw) : '';
  const facts: string[] = [];

  if (status) facts.push(`<div class="detail-row"><div class="detail-label">HTTP status</div><div class="detail-value">${App.escapeHtml(status)}</div></div>`);
  if (code) facts.push(`<div class="detail-row"><div class="detail-label">Code</div><div class="detail-value mono">${code}</div></div>`);
  facts.push(`<div class="detail-row"><div class="detail-label">Page</div><div class="detail-value mono">${App.escapeHtml(App.currentPageUrl())}</div></div>`);

  const actions: string[] = [];
  if (options.primaryActionHref && options.primaryActionLabel) {
    actions.push(`<a class="btn btn-primary" href="${App.escapeHtml(options.primaryActionHref)}">${App.escapeHtml(options.primaryActionLabel)}</a>`);
  }
  if (options.secondaryActionHref && options.secondaryActionLabel) {
    actions.push(`<a class="btn" href="${App.escapeHtml(options.secondaryActionHref)}">${App.escapeHtml(options.secondaryActionLabel)}</a>`);
  }

  el.hidden = false;
  el.innerHTML = `
    <section class="page-error-surface notice error" role="alert" aria-live="polite">
      <div class="notice-header">
        <strong>${title}</strong>
      </div>
      ${summary ? `<p class="page-error-copy">${summary}</p>` : ''}
      ${detail ? `<p class="page-error-detail mono">${detail}</p>` : ''}
      ${hint ? `<p class="page-error-hint muted">${hint}</p>` : ''}
      <div class="detail-grid page-error-grid">
        <div class="detail-card surface-panel-compact">
          <div class="detail-card-title">What failed</div>
          ${facts.join('')}
        </div>
        ${actions.length ? `
          <div class="detail-card surface-panel-compact">
            <div class="detail-card-title">Recovery</div>
            <div class="page-error-actions">${actions.join('')}</div>
          </div>
        ` : ''}
      </div>
      ${rawEscaped ? `
        <details class="page-error-raw">
          <summary>Technical details</summary>
          <pre>${rawEscaped}</pre>
        </details>
      ` : ''}
    </section>
  `;
};

window.App = App;

export { App };
