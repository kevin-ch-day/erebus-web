(() => {
  const App = window.App || {};

  const esc = (value) => (App.escapeHtml ? App.escapeHtml(value) : String(value ?? ''));

  const normalizePayload = App.normalizePayload || ((body) => {
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
  });

  const fetchPayload = App.fetchPayload || (async (url) => {
    const started = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
    let res = null;
    let raw = '';
    try {
      res = await fetch(url, { cache: 'no-store' });
      raw = await res.text();
    } catch (err) {
      const elapsedMs = Math.max(0, Math.round(((typeof performance !== 'undefined' && performance.now)
        ? performance.now()
        : Date.now()) - started));
      return {
        ok: false,
        status: 0,
        error: err && err.message ? err.message : 'Network error',
        raw,
        elapsedMs,
      };
    }

    let body = null;
    try {
      body = JSON.parse(raw);
    } catch (_) {
      const elapsedMs = Math.max(0, Math.round(((typeof performance !== 'undefined' && performance.now)
        ? performance.now()
        : Date.now()) - started));
      return { ok: false, status: res.status, error: 'Non-JSON response', raw, elapsedMs };
    }

    const normalized = normalizePayload(body);
    const elapsedMs = Math.max(0, Math.round(((typeof performance !== 'undefined' && performance.now)
      ? performance.now()
      : Date.now()) - started));
    return { ...normalized, status: res.status, raw, elapsedMs };
  });

  const formatUtcFixed = (value, includeSeconds = false) =>
    App.formatUtc(value, { timeZone: 'UTC', includeSeconds });

  const renderUnknown = (value, labelMap) => {
    if (value === null || value === undefined || value === '') return '--';
    const key = String(value).toLowerCase();
    if (labelMap && labelMap[key]) return labelMap[key];
    if (key.startsWith('unknown:')) return String(value);
    return `unknown:${value}`;
  };

  const setTableMessage = (bodyEl, colSpan, message, className = 'muted') => {
    if (!bodyEl) return;
    bodyEl.innerHTML = `<tr><td colspan="${colSpan}" class="${className}">${esc(message)}</td></tr>`;
  };

  const createCursorPager = () => {
    let cursorStack = [];
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
      setCurrent(value) {
        currentCursor = value || '';
      },
      setNext(value) {
        nextCursor = value || '';
      },
      setHasMore(value) {
        hasMore = value === true || value === 1;
      },
      updateFromPayload(payload) {
        hasMore = payload.has_more === true || payload.has_more === 1;
        nextCursor = payload.next_cursor || payload.nextCursor || '';
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
  };

  const bindCopy = (root) => {
    if (!root || !App.copyText) return;
    if (root.dataset.copyBound === '1') return;
    root.dataset.copyBound = '1';
    root.addEventListener('click', (event) => {
      const target = event.target.closest('[data-copy]');
      if (!target) return;
      const value = target.getAttribute('data-copy');
      if (!value) return;
      App.copyText(value);
      target.classList.add('copyable-active');
      setTimeout(() => target.classList.remove('copyable-active'), 700);
    });
  };

  App.readonly = {
    normalizePayload,
    fetchPayload,
    formatUtcFixed,
    renderUnknown,
    setTableMessage,
    createCursorPager,
    bindCopy,
  };

  window.App = App;
})();
