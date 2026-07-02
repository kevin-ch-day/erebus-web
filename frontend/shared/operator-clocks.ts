export function tzLabelFromKeyOrId(key: unknown, tz: unknown): string {
  const keyLabels: Record<string, string> = {
    minneapolis: 'Minneapolis',
    denver: 'Denver',
    las_vegas: 'Las Vegas',
    new_york: 'New York',
    anchorage: 'Anchorage',
    honolulu: 'Honolulu',
    amsterdam: 'Amsterdam',
    paris: 'Paris',
    utc: 'UTC',
    dubai: 'Dubai',
    tokyo: 'Tokyo',
  };

  const normalizedKey = String(key || '').trim();
  if (normalizedKey && keyLabels[normalizedKey]) {
    return keyLabels[normalizedKey];
  }

  const tzLabels: Record<string, string> = {
    'America/Chicago': 'Minneapolis',
    'America/Denver': 'Denver',
    'America/Los_Angeles': 'Las Vegas',
    'America/New_York': 'New York',
    'America/Anchorage': 'Anchorage',
    'Pacific/Honolulu': 'Honolulu',
    'Europe/Amsterdam': 'Amsterdam',
    'Europe/Paris': 'Paris',
    UTC: 'UTC',
    'Asia/Dubai': 'Dubai',
    'Asia/Tokyo': 'Tokyo',
  };

  const normalizedTz = String(tz || '').trim();
  return tzLabels[normalizedTz] || normalizedTz || '';
}

export function readSelectedOption(select: HTMLSelectElement | null): HTMLOptionElement | null {
  if (!select) return null;
  return select.options[select.selectedIndex] || null;
}

export function readSelectedOptionText(select: HTMLSelectElement | null): string {
  const option = readSelectedOption(select);
  return option ? String(option.textContent || '').trim() : '';
}

export function readSelectedOptionTz(select: HTMLSelectElement | null): string {
  const option = readSelectedOption(select);
  return option ? String(option.dataset.tz || '') : '';
}

export function readSelectedOptionKey(select: HTMLSelectElement | null): string {
  return String(select?.value || '').trim();
}

export function syncOperatorClockDatasets(
  primaryKey: string,
  primaryTz: string,
  secondaryKey: string,
  secondaryTz: string
): void {
  document.documentElement.dataset.displayTz = primaryTz || 'UTC';
  document.documentElement.dataset.displayTzKey = primaryKey || '';
  document.documentElement.dataset.secondaryTz = secondaryKey === 'none' ? '' : secondaryTz;
  document.documentElement.dataset.secondaryTzKey = secondaryKey === 'none' ? '' : secondaryKey;
  window.dispatchEvent(new Event('topbar-tz-change'));
}
