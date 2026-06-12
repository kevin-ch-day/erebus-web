export interface SamplesPageQueryElements {
  searchEl?: HTMLInputElement | null;
  familyEl?: HTMLInputElement | null;
  familyAlignmentEl?: HTMLSelectElement | null;
  statusEl?: HTMLSelectElement | null;
  pageSizeEl?: HTMLSelectElement | null;
  pageEl?: HTMLInputElement | null;
  columnsEl?: HTMLSelectElement | null;
  sortByEl?: HTMLSelectElement | null;
  sortDirEl?: HTMLSelectElement | null;
}

export interface SamplesPageRenderRowsOptions {
  bodyEl: HTMLElement | null;
  detailBase: string;
}

export interface SamplesPageRenderMetaOptions {
  metaEl: HTMLElement | null;
  pagesEl: HTMLElement | null;
  pageEl: HTMLInputElement | null;
  prevBtn: HTMLButtonElement | null;
  nextBtn: HTMLButtonElement | null;
  activeFilters: number;
}

export interface SamplesPagePayloadRow extends Record<string, unknown> {}

export interface SamplesPagePayload extends Record<string, unknown> {
  rows?: SamplesPagePayloadRow[];
  total_count?: unknown;
  page?: unknown;
  page_size?: unknown;
  total_pages?: unknown;
}

export interface SamplesPageNamespace {
  buildQuery(elements: SamplesPageQueryElements, pageOverride?: number | string | null): URLSearchParams;
  updateUrl(params: URLSearchParams): void;
  applyColumnsView(tableEl: Element | null, value: string): void;
  renderRows(rows: SamplesPagePayloadRow[], options: SamplesPageRenderRowsOptions): void;
  renderMeta(payload: SamplesPagePayload, options: SamplesPageRenderMetaOptions): void;
}

declare global {
  interface Window {
    SamplesPage: SamplesPageNamespace;
  }
}
