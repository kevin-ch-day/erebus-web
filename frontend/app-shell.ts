import Alpine from 'alpinejs';

import './shared/app-core';
import './shared/db-status-pill';
import './shared/permission-intel';
import './shared/topbar-clock';
import './pages/analysis-fusion';
import './pages/permissions-review';
import './pages/vt-confidence';

declare global {
  interface Window {
    Alpine: typeof Alpine;
  }
}

type NavSectionState = {
  key: string;
  collapsed: boolean;
  defaultCollapsed: boolean;
  init(): void;
  toggle(): void;
};

const NAV_LAYOUT_VERSION = '2026-05-shell-v1';
const NAV_LAYOUT_VERSION_KEY = 'nav-layout-version';

window.Alpine = Alpine;

Alpine.data('navSection', (key: string, isActive = false, defaultCollapsed = true): NavSectionState => ({
  key: String(key || ''),
  collapsed: false,
  defaultCollapsed: !!defaultCollapsed,

  init() {
    const storedVersion = window.localStorage.getItem(NAV_LAYOUT_VERSION_KEY);
    const useStoredState = storedVersion === NAV_LAYOUT_VERSION;
    let collapsed = this.defaultCollapsed;

    if (this.key) {
      const stored = useStoredState
        ? window.localStorage.getItem(`nav-section:${NAV_LAYOUT_VERSION}:${this.key}`)
        : null;
      if (stored === 'collapsed') collapsed = true;
      if (stored === 'expanded') collapsed = false;
    }

    if (isActive) {
      collapsed = false;
    }

    this.collapsed = collapsed;
    window.localStorage.setItem(NAV_LAYOUT_VERSION_KEY, NAV_LAYOUT_VERSION);
  },

  toggle() {
    this.collapsed = !this.collapsed;
    if (this.key) {
      window.localStorage.setItem(
        `nav-section:${NAV_LAYOUT_VERSION}:${this.key}`,
        this.collapsed ? 'collapsed' : 'expanded'
      );
    }
  },
}));

Alpine.start();
