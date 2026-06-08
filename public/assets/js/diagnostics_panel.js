(() => {
  const App = window.App || {};

  function formatCapabilities(value) {
    if (!value) return '--';
    if (Array.isArray(value)) {
      return value.map((item) => String(item)).join(', ') || '--';
    }
    if (typeof value === 'object') {
      const entries = Object.entries(value);
      if (!entries.length) return '--';
      return entries.map(([key, val]) => `${key}:${val === true ? 'yes' : val === false ? 'no' : String(val)}`).join(', ');
    }
    return String(value);
  }

  function formatNowUtc(formatUtc) {
    const formatter = formatUtc || ((value) => String(value));
    return formatter(new Date().toISOString(), true);
  }

  function createDiagnostics(options = {}) {
    const {
      baseUrlEl,
      lastFetchEl,
      statusEl,
      latencyEl,
      capabilitiesEl,
      apiBase,
      formatUtc,
    } = options;

    const setBaseUrl = () => {
      if (!baseUrlEl) return;
      baseUrlEl.textContent = apiBase && apiBase.trim() !== '' ? apiBase : 'same-origin';
    };

    const update = (payload = {}) => {
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

  App.diagnostics = { create: createDiagnostics };
  window.App = App;
})();
