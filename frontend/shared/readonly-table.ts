import type { AppPayloadFailure, AppSurface, JsonRecord } from '../types/app-globals';

export type CursorPager = {
  reset: () => void;
  push: () => void;
  pop: () => string;
  setCurrent: (value: string) => void;
  setNext: (value: string) => void;
  setHasMore: (value: unknown) => void;
  updateFromPayload: (payload: JsonRecord) => void;
  getCurrent: () => string;
  getNext: () => string;
  getHasMore: () => boolean;
  getPageIndex: () => number;
  getStackSize: () => number;
};

export function formatUtcFixed(app: AppSurface, value: unknown, includeSeconds = false): string {
  return app.formatUtc(value, { timeZone: 'UTC', includeSeconds });
}

export function renderUnknown(value: unknown, labelMap?: Record<string, string>): string {
  if (value === null || value === undefined || value === '') return '--';
  const key = String(value).toLowerCase();
  if (labelMap && labelMap[key]) return labelMap[key];
  if (key.startsWith('unknown:')) return String(value);
  return `unknown:${value}`;
}

export function setTableMessage(
  app: AppSurface,
  bodyEl: HTMLElement | null,
  colSpan: number,
  message: string,
  className = 'muted',
): void {
  if (!bodyEl) return;
  bodyEl.innerHTML = `<tr><td colspan="${colSpan}" class="${className}">${app.escapeHtml(message)}</td></tr>`;
}

export function createCursorPager(): CursorPager {
  let cursorStack: string[] = [];
  let currentCursor = '';
  let hasMore = false;
  let nextCursor = '';

  return {
    reset() {
      cursorStack = [];
      currentCursor = '';
      hasMore = false;
      nextCursor = '';
    },
    push() {
      cursorStack.push(currentCursor);
    },
    pop() {
      currentCursor = cursorStack.pop() || '';
      return currentCursor;
    },
    setCurrent(value: string) {
      currentCursor = value || '';
    },
    setNext(value: string) {
      nextCursor = value || '';
    },
    setHasMore(value: unknown) {
      hasMore = value === true || value === 1;
    },
    updateFromPayload(payload: JsonRecord) {
      hasMore = payload.has_more === true || payload.has_more === 1;
      nextCursor = String(payload.next_cursor ?? payload.nextCursor ?? '');
    },
    getCurrent() {
      return currentCursor;
    },
    getNext() {
      return nextCursor;
    },
    getHasMore() {
      return hasMore;
    },
    getPageIndex() {
      return cursorStack.length + 1;
    },
    getStackSize() {
      return cursorStack.length;
    },
  };
}

export function bindCopyTargets(app: AppSurface, root: ParentNode | null): void {
  if (!root || !app.copyText) return;
  const host = root as HTMLElement;
  if (host.dataset.copyBound === '1') return;
  host.dataset.copyBound = '1';
  host.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement | null)?.closest('[data-copy]') as HTMLElement | null;
    if (!target) return;
    const value = target.getAttribute('data-copy');
    if (!value) return;
    app.copyText(value);
    target.classList.add('copyable-active');
    window.setTimeout(() => target.classList.remove('copyable-active'), 700);
  });
}

export function shouldQueueApiFallback(res: AppPayloadFailure): boolean {
  if (res.ok) return false;
  if (res.code === 'ERR_SCHEMA_MISSING') return false;
  if (res.status === 0 || res.status === 404 || res.status >= 500) return true;
  if (res.error === 'Non-JSON response') return true;
  return false;
}
