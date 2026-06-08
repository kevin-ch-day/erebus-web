(() => {
  const sections = Array.from(document.querySelectorAll('.nav-section'));
  if (!sections.length) return;

  function setCollapsed(section, collapsed) {
    const toggle = section.querySelector('.nav-section-toggle');
    section.classList.toggle('is-collapsed', collapsed);
    if (toggle) {
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
  }

  sections.forEach((section) => {
    const key = section.dataset.section || '';
    const toggle = section.querySelector('.nav-section-toggle');
    const active = section.querySelector('.nav-link-active');
    let collapsed = false;

    if (key) {
      const stored = window.localStorage.getItem(`nav-section:${key}`);
      if (stored === 'collapsed') collapsed = true;
      if (stored === 'expanded') collapsed = false;
    }

    if (active) {
      collapsed = false;
    }

    setCollapsed(section, collapsed);

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isCollapsed = section.classList.contains('is-collapsed');
        const next = !isCollapsed;
        setCollapsed(section, next);
        if (key) {
          window.localStorage.setItem(`nav-section:${key}`, next ? 'collapsed' : 'expanded');
        }
      });
    }
  });
})();

