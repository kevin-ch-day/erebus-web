(() => {
  const primarySelect = document.getElementById('tz');
  const secondarySelect = document.getElementById('tz_secondary');
  const primaryPreview = document.getElementById('time-primary-preview');
  const secondaryPreview = document.getElementById('time-secondary-preview');
  const usDefaultButtons = document.querySelectorAll('[data-primary-tz-key]');

  if (!primarySelect || !secondarySelect) return;

  function optionText(select) {
    const opt = select.options[select.selectedIndex] || null;
    return opt ? String(opt.textContent || '').trim() : '';
  }

  function optionTz(select) {
    const opt = select.options[select.selectedIndex] || null;
    return opt && opt.dataset ? String(opt.dataset.tz || '') : '';
  }

  function optionKey(select) {
    return String(select.value || '').trim();
  }

  function syncPageState() {
    const primaryLabel = optionText(primarySelect);
    const primaryTz = optionTz(primarySelect);
    const secondaryLabel = optionText(secondarySelect);
    const secondaryTz = optionTz(secondarySelect);
    const secondaryKey = optionKey(secondarySelect);

    document.documentElement.dataset.displayTz = primaryTz || 'UTC';
    document.documentElement.dataset.displayTzKey = optionKey(primarySelect);
    document.documentElement.dataset.secondaryTz = secondaryKey === 'none' ? '' : secondaryTz;
    document.documentElement.dataset.secondaryTzKey = secondaryKey === 'none' ? '' : secondaryKey;

    if (primaryPreview) {
      primaryPreview.textContent = 'Primary clock: ' + (primaryLabel || '--');
    }
    if (secondaryPreview) {
      secondaryPreview.textContent = secondaryKey === 'none'
        ? 'Second clock: disabled'
        : 'Second clock: ' + (secondaryLabel || '--');
    }

    window.dispatchEvent(new Event('topbar-tz-change'));
  }

  primarySelect.addEventListener('change', () => {
    if (optionKey(primarySelect) === optionKey(secondarySelect)) {
      secondarySelect.value = 'none';
    }
    syncPageState();
  });
  secondarySelect.addEventListener('change', () => {
    if (optionKey(primarySelect) === optionKey(secondarySelect)) {
      secondarySelect.value = 'none';
    }
    syncPageState();
  });

  usDefaultButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const nextKey = String(button.dataset.primaryTzKey || '').trim();
      if (!nextKey) return;
      primarySelect.value = nextKey;
      if (optionKey(primarySelect) === optionKey(secondarySelect)) {
        secondarySelect.value = 'none';
      }
      syncPageState();
    });
  });

  syncPageState();
})();
