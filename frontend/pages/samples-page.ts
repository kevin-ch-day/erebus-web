import type { AppPayloadFailure, AppPayloadSuccess, AppSurface } from '../types/app-globals';
import type {
  SamplesPageNamespace,
  SamplesPagePayload,
  SamplesPageQueryElements,
} from '../types/samples-page-globals';

const root = document.getElementById('samples-page-root') as HTMLElement | null;

if (root && window.App && window.SamplesPage) {
  const pageRoot = root;
  const App = window.App as AppSurface;
  const SamplesPage = window.SamplesPage as SamplesPageNamespace;

  const endpoint = root.dataset.endpoint || '';
  const detailBase = root.dataset.detailBase || '';
  const searchEl = document.getElementById('samples-search') as HTMLInputElement | null;
  const familyEl = document.getElementById('samples-family') as HTMLInputElement | null;
  const familyAlignmentEl = document.getElementById('samples-family-alignment') as HTMLSelectElement | null;
  const statusEl = document.getElementById('samples-status') as HTMLSelectElement | null;
  const pageSizeEl = document.getElementById('samples-page-size') as HTMLSelectElement | null;
  const pageEl = document.getElementById('samples-page') as HTMLInputElement | null;
  const columnsEl = document.getElementById('samples-columns') as HTMLSelectElement | null;
  const sortByEl = document.getElementById('samples-sort-by') as HTMLSelectElement | null;
  const sortDirEl = document.getElementById('samples-sort-dir') as HTMLSelectElement | null;
  const sortButtons = Array.from(document.querySelectorAll('.samples-sort-btn')) as HTMLButtonElement[];
  const refreshBtn = document.getElementById('samples-refresh') as HTMLButtonElement | null;
  const resetBtn = document.getElementById('samples-reset') as HTMLButtonElement | null;

  const tableEl = document.querySelector('.samples-table');
  const bodyEl = document.getElementById('samples-body') as HTMLElement | null;
  const metaEl = document.getElementById('samples-meta') as HTMLElement | null;
  const pagesEl = document.getElementById('samples-pages') as HTMLElement | null;
  const prevBtn = document.getElementById('samples-prev') as HTMLButtonElement | null;
  const nextBtn = document.getElementById('samples-next') as HTMLButtonElement | null;
  const errorEl = document.getElementById('samples-error') as HTMLElement | null;

  if (endpoint && detailBase && bodyEl) {
    const tableBodyEl = bodyEl;
    const esc = App.escapeHtml;
    const copyText = App.copyText;

    let pendingTimer: number | null = null;
    let lastPayload: SamplesPagePayload | null = null;

    const queryElements: SamplesPageQueryElements = {
      searchEl,
      familyEl,
      familyAlignmentEl,
      statusEl,
      pageSizeEl,
      pageEl,
      columnsEl,
      sortByEl,
      sortDirEl,
    };

    function updateSortIndicators(): void {
      if (!sortButtons.length || !sortByEl || !sortDirEl) return;
      const activeBy = String(sortByEl.value || '').toLowerCase();
      const activeDir = String(sortDirEl.value || '').toLowerCase();

      sortButtons.forEach((btn) => {
        const key = String(btn.dataset.sort || '').toLowerCase();
        const indicator = btn.querySelector('.samples-sort-indicator');
        const active = key === activeBy;

        btn.classList.toggle('active', active);
        if (indicator) {
          indicator.textContent = active ? (activeDir === 'asc' ? '↑' : '↓') : '·';
        }
      });
    }

    function countActiveFilters(): number {
      let count = 0;
      if (searchEl && searchEl.value.trim() !== '') count += 1;
      if (familyEl && familyEl.value.trim() !== '') count += 1;
      if (familyAlignmentEl && familyAlignmentEl.value !== '') count += 1;
      if (statusEl && statusEl.value !== '') count += 1;
      if (columnsEl && columnsEl.value === 'detailed') count += 1;
      if (sortByEl && sortByEl.value !== (pageRoot.dataset.defaultSortBy || 'id')) count += 1;
      if (sortDirEl && sortDirEl.value !== (pageRoot.dataset.defaultSortDir || 'desc')) count += 1;
      return count;
    }

    function resetFiltersToDefaults(): void {
      const defaultPageSize = pageRoot.dataset.defaultPageSize || (pageSizeEl && pageSizeEl.options.length ? pageSizeEl.options[0].value : '100');
      const defaultColumns = pageRoot.dataset.defaultColumns || 'simple';
      const defaultSortBy = pageRoot.dataset.defaultSortBy || 'id';
      const defaultSortDir = pageRoot.dataset.defaultSortDir || 'desc';

      if (searchEl) searchEl.value = '';
      if (familyEl) familyEl.value = '';
      if (familyAlignmentEl) familyAlignmentEl.value = '';
      if (statusEl) statusEl.value = '';
      if (columnsEl) columnsEl.value = defaultColumns;
      if (sortByEl) sortByEl.value = defaultSortBy;
      if (sortDirEl) sortDirEl.value = defaultSortDir;
      if (pageSizeEl) pageSizeEl.value = defaultPageSize;
      if (pageEl) pageEl.value = '1';
      SamplesPage.applyColumnsView(tableEl, defaultColumns);
      updateSortIndicators();
    }

    function renderFailure(response: AppPayloadFailure): void {
      if (!errorEl) return;
      const raw = response.raw ? String(response.raw).slice(0, 2000) : '';
      const detail = raw ? `\n\n${esc(raw)}` : '';
      errorEl.innerHTML = `<pre>Samples API error.\n\nHTTP ${response.status}\nerror: ${esc(response.error)}${detail}</pre>`;
    }

    function asPayload(response: AppPayloadSuccess): SamplesPagePayload {
      return (response.data && typeof response.data === 'object') ? (response.data as SamplesPagePayload) : {};
    }

    async function loadSamples(pageOverride: number | string | null = null): Promise<void> {
      const params = SamplesPage.buildQuery(queryElements, pageOverride);
      SamplesPage.updateUrl(params);
      const url = `${endpoint}?${params.toString()}`;

      if (pendingTimer !== null) {
        window.clearTimeout(pendingTimer);
        pendingTimer = null;
      }

      if (errorEl) errorEl.textContent = '';
      tableBodyEl.innerHTML = '<tr><td colspan="16" class="muted">Loading samples...</td></tr>';

      try {
        const res = await App.fetchPayload(url);
        if (!res.ok) {
          renderFailure(res);
          return;
        }

        lastPayload = asPayload(res);
        SamplesPage.renderRows(lastPayload.rows || [], { bodyEl: tableBodyEl, detailBase });
        SamplesPage.renderMeta(lastPayload, {
          metaEl,
          pagesEl,
          pageEl,
          prevBtn,
          nextBtn,
          activeFilters: countActiveFilters(),
        });
      } catch (error) {
        if (errorEl) {
          errorEl.innerHTML = `<pre>Samples API error:\n${esc(error instanceof Error ? error.message : String(error))}</pre>`;
        }
      }
    }

    function scheduleReload(): void {
      if (pendingTimer !== null) window.clearTimeout(pendingTimer);
      pendingTimer = window.setTimeout(() => {
        void loadSamples(1);
      }, 200);
    }

    tableBodyEl.addEventListener('click', (event) => {
      const target = event.target;
      const button = target instanceof Element ? target.closest('.copy-btn') : null;
      if (!(button instanceof HTMLElement)) return;
      const value = button.getAttribute('data-copy') || '';
      if (!value) return;
      copyText(value);
    });

    [searchEl, familyEl, familyAlignmentEl, statusEl, pageSizeEl, columnsEl, sortByEl, sortDirEl].forEach((el) => {
      if (!el) return;
      el.addEventListener('input', scheduleReload);
      el.addEventListener('change', scheduleReload);
    });
    [searchEl, familyEl].forEach((el) => {
      if (!el) return;
      el.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        void loadSamples(1);
      });
    });

    if (columnsEl) {
      columnsEl.addEventListener('change', () => SamplesPage.applyColumnsView(tableEl, columnsEl.value));
    }
    if (sortByEl) sortByEl.addEventListener('change', updateSortIndicators);
    if (sortDirEl) sortDirEl.addEventListener('change', updateSortIndicators);
    if (pageEl) pageEl.addEventListener('change', () => { void loadSamples(pageEl.value || 1); });
    if (prevBtn && pageEl) {
      prevBtn.addEventListener('click', () => {
        void loadSamples(Math.max(1, Number(pageEl.value) - 1));
      });
    }
    if (nextBtn && pageEl) {
      nextBtn.addEventListener('click', () => {
        const pages = lastPayload ? Number(lastPayload.total_pages || 1) : 1;
        const next = Math.min(pages, Number(pageEl.value) + 1);
        void loadSamples(next);
      });
    }
    if (refreshBtn && pageEl) {
      refreshBtn.addEventListener('click', () => { void loadSamples(pageEl.value || 1); });
    }
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        resetFiltersToDefaults();
        void loadSamples(1);
      });
    }
    sortButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        if (!sortByEl || !sortDirEl) return;
        const nextBy = String(btn.dataset.sort || '').toLowerCase();
        if (!nextBy) return;

        const currentBy = String(sortByEl.value || '').toLowerCase();
        const currentDir = String(sortDirEl.value || '').toLowerCase();
        const nextDir = currentBy === nextBy ? (currentDir === 'asc' ? 'desc' : 'asc') : 'desc';

        sortByEl.value = nextBy;
        sortDirEl.value = nextDir;
        updateSortIndicators();
        void loadSamples(1);
      });
    });

    SamplesPage.applyColumnsView(tableEl, columnsEl?.value || 'simple');
    updateSortIndicators();
    void loadSamples();
  }
}
