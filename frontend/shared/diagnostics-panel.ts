export type DiagnosticsUpdatePayload = {
  status?: number;
  elapsedMs?: number;
  capabilities?: unknown;
};

export type DiagnosticsPanelOptions = {
  baseUrlEl?: HTMLElement | null;
  lastFetchEl?: HTMLElement | null;
  statusEl?: HTMLElement | null;
  latencyEl?: HTMLElement | null;
  capabilitiesEl?: HTMLElement | null;
  apiBase?: string;
  formatUtc?: (value: unknown, includeSeconds?: boolean) => string;
};

function formatCapabilities(value: unknown): string {
  if (!value) return '--';
  if (Array.isArray(value)) {
    return value.map((item) => String(item)).join(', ') || '--';
  }
  if (typeof value === 'object') {
    const entries = Object.entries(value as Record<string, unknown>);
    if (!entries.length) return '--';
    return entries
      .map(([key, val]) => `${key}:${val === true ? 'yes' : val === false ? 'no' : String(val)}`)
      .join(', ');
  }
  return String(value);
}

function formatNowUtc(formatUtc?: DiagnosticsPanelOptions['formatUtc']): string {
  const formatter = formatUtc || ((value) => String(value));
  return formatter(new Date().toISOString(), true);
}

export function createDiagnosticsPanel(options: DiagnosticsPanelOptions = {}): {
  update: (payload?: DiagnosticsUpdatePayload) => void;
  setBaseUrl: () => void;
} {
  const {
    baseUrlEl,
    lastFetchEl,
    statusEl,
    latencyEl,
    capabilitiesEl,
    apiBase,
    formatUtc,
  } = options;

  const setBaseUrl = (): void => {
    if (!baseUrlEl) return;
    baseUrlEl.textContent = apiBase && apiBase.trim() !== '' ? apiBase : 'same-origin';
  };

  const update = (payload: DiagnosticsUpdatePayload = {}): void => {
    const { status, elapsedMs, capabilities } = payload;
    if (lastFetchEl) lastFetchEl.textContent = formatNowUtc(formatUtc);
    if (statusEl && status !== undefined) statusEl.textContent = status ? `HTTP ${status}` : '--';
    if (latencyEl && elapsedMs !== undefined) latencyEl.textContent = `${elapsedMs} ms`;
    if (capabilitiesEl && capabilities !== undefined) {
      capabilitiesEl.textContent = formatCapabilities(capabilities);
    }
  };

  setBaseUrl();
  return { update, setBaseUrl };
}
