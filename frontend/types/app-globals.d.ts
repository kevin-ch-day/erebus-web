export type JsonRecord = Record<string, unknown>;

export interface AppJsonSuccess<T = JsonRecord> {
  ok: true;
  status: number;
  body: T;
  raw: string;
}

export interface AppJsonFailure {
  ok: false;
  status: number;
  error: string;
  raw: string;
  body?: unknown;
}

export interface AppPayloadSuccess<T = unknown> {
  ok: true;
  data: T;
  meta: JsonRecord;
  status: number;
  raw: string;
  elapsedMs: number;
}

export interface AppPayloadFailure {
  ok: false;
  error: string;
  code?: string;
  data?: unknown;
  status: number;
  raw: string;
  elapsedMs: number;
}

export interface AppPageErrorOptions {
  title: string;
  summary?: string;
  detail?: string;
  status?: number;
  code?: string;
  raw?: string;
  hint?: string;
  primaryActionHref?: string;
  primaryActionLabel?: string;
  secondaryActionHref?: string;
  secondaryActionLabel?: string;
}

export interface AppSurface {
  escapeHtml(value: unknown): string;
  fmt(value: unknown, fallback?: string): string;
  parseUtcToMs(value: unknown): number | null;
  getDisplayTz(): string;
  getSecondaryTz(): string;
  formatUtc(value: unknown, options?: { timeZone?: string; includeSeconds?: boolean }): string;
  copyText(value: string): void;
  pageBaseUrl(): string;
  pageUrl(page: string, params?: Record<string, unknown>): string;
  currentPageUrl(): string;
  appendQueryParam(url: string, key: string, value: unknown): string;
  parseJsonResponse(res: Response): Promise<AppJsonSuccess | AppJsonFailure>;
  normalizePayload(body: unknown): { ok: true; data: unknown; meta: JsonRecord } | { ok: false; error: string; code: string; data?: unknown };
  fetchJson(url: string, options?: RequestInit): Promise<AppJsonSuccess | AppJsonFailure>;
  fetchPayload(url: string, options?: RequestInit): Promise<AppPayloadSuccess | AppPayloadFailure>;
  postJson(url: string, payload: unknown, options?: RequestInit): Promise<AppJsonSuccess | AppJsonFailure>;
  postForm(url: string, payload: Record<string, string>, options?: RequestInit): Promise<AppJsonSuccess | AppJsonFailure>;
  clearPageError(el: HTMLElement | null): void;
  renderPageError(el: HTMLElement | null, options: AppPageErrorOptions): void;
  readonly?: {
    setTableMessage?: (el: HTMLElement | null, span: number, message: string, className?: string) => void;
  };
}

export interface PermissionIntelSurface {
  normalizeKey(value: unknown): string;
  formatCount(value: unknown): string;
  formatPct(value: unknown): string;
  resolveWorkflowUnknownBacklog(...sources: Array<Record<string, unknown> | null | undefined>): number;
  resolveActionableReviewBacklog(...sources: Array<Record<string, unknown> | null | undefined>): number;
  statusFromUnknownPct(value: unknown): { label: string; className: string };
  riskHint(permission: unknown, namespace: unknown): { label: string; className: string };
  riskReasonLabels: Record<string, string>;
  riskReasonLabel(value: unknown): string;
  queueStatusLabels: Record<string, string>;
  queueStatusLabel(statusKey: unknown): string;
  queueStatusBadge(statusKey: unknown): { label: string; className: string };
  triagePriorityBucket(statusKey: unknown): number;
  setLov(lov: unknown): void;
  getLov(): unknown;
  fetchLov(endpoint: string): Promise<unknown>;
  classifyNamespace(namespace: unknown): { label: string; className: string };
  formatUtcDual(value: unknown): { primary: string; secondary: string };
  bindEnterReload(elements: Array<EventTarget | null | undefined>, handler: () => void): void;
  renderError(errorEl: HTMLElement | null, title: string, detail: string): void;
  createReadonlyCatalogPage(options: ReadonlyCatalogPageOptions): {
    loadPage: () => Promise<void>;
    resetAndLoad: () => Promise<void>;
    primeLov: () => Promise<void>;
    setPage: (value: unknown) => void;
    getPage: () => number;
  };
}

export type ReadonlyCatalogPageOptions = {
  endpoint: string;
  lovEndpoint?: string;
  pageSize?: number;
  bodyEl: HTMLElement | null;
  metaEl: HTMLElement | null;
  errorEl: HTMLElement | null;
  colSpan?: number;
  loadMessage?: string;
  emptyMessage?: string;
  emptyMeta?: string;
  renderMetaText?: (meta: JsonRecord) => string;
  buildParams?: () => Record<string, string> | URLSearchParams;
  renderRow?: (row: JsonRecord) => string;
  loadLov?: (lov: unknown) => void;
};

declare global {
  interface Window {
    App: AppSurface;
    PermissionIntel: PermissionIntelSurface;
  }
}
