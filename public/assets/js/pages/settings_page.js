(() => {
  const tzSelect = document.getElementById('tz');
  const tzPreview = document.getElementById('settings-tz-preview');
  if (!tzSelect) return;

  function selectedOption() {
    return tzSelect.options[tzSelect.selectedIndex] || null;
  }

  function selectedTz() {
    const opt = selectedOption();
    return opt && opt.dataset ? opt.dataset.tz : '';
  }

  function previewTimezone() {
    const opt = selectedOption();
    const tz = selectedTz();
    if (tz) {
      document.documentElement.dataset.displayTz = tz;
      window.dispatchEvent(new Event('topbar-tz-change'));
    }
    if (tzPreview) {
      const label = opt ? String(opt.textContent || '').trim() : '';
      tzPreview.textContent = 'Current selection: ' + (label || '--');
    }
  }

  tzSelect.addEventListener('change', previewTimezone);
  previewTimezone();
})();
