import {
  readSelectedOptionKey,
  readSelectedOptionText,
  readSelectedOptionTz,
  syncOperatorClockDatasets,
} from '../shared/operator-clocks';

const primarySelect = document.getElementById('tz') as HTMLSelectElement | null;
const secondarySelect = document.getElementById('tz_secondary') as HTMLSelectElement | null;
const primaryPreview = document.getElementById('time-primary-preview') as HTMLElement | null;
const secondaryPreview = document.getElementById('time-secondary-preview') as HTMLElement | null;
const usDefaultButtons = Array.from(document.querySelectorAll('[data-primary-tz-key]')) as HTMLButtonElement[];

if (primarySelect && secondarySelect) {
  const primaryClockSelect = primarySelect;
  const secondaryClockSelect = secondarySelect;

  function syncPageState(): void {
    const primaryLabel = readSelectedOptionText(primaryClockSelect);
    const primaryTz = readSelectedOptionTz(primaryClockSelect);
    const secondaryLabel = readSelectedOptionText(secondaryClockSelect);
    const secondaryTz = readSelectedOptionTz(secondaryClockSelect);
    const secondaryKey = readSelectedOptionKey(secondaryClockSelect);
    const primaryKey = readSelectedOptionKey(primaryClockSelect);

    syncOperatorClockDatasets(primaryKey, primaryTz, secondaryKey, secondaryTz);

    if (primaryPreview) {
      primaryPreview.textContent = `Primary clock: ${primaryLabel || '--'}`;
    }
    if (secondaryPreview) {
      secondaryPreview.textContent = secondaryKey === 'none'
        ? 'Second clock: disabled'
        : `Second clock: ${secondaryLabel || '--'}`;
    }
  }

  function ensureDistinctSecondary(): void {
    if (readSelectedOptionKey(primaryClockSelect) === readSelectedOptionKey(secondaryClockSelect)) {
      secondaryClockSelect.value = 'none';
    }
  }

  primaryClockSelect.addEventListener('change', () => {
    ensureDistinctSecondary();
    syncPageState();
  });

  secondaryClockSelect.addEventListener('change', () => {
    ensureDistinctSecondary();
    syncPageState();
  });

  usDefaultButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const nextKey = String(button.dataset.primaryTzKey || '').trim();
      if (!nextKey) return;
      primaryClockSelect.value = nextKey;
      ensureDistinctSecondary();
      syncPageState();
    });
  });

  syncPageState();
}
