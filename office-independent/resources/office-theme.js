(() => {
  'use strict';
  const key = 'brebo-office-theme';
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  let mode = 'system';
  try { const saved = localStorage.getItem(key); if (['day', 'night', 'system'].includes(saved)) mode = saved; } catch (_) {}
  const apply = () => {
    document.documentElement.dataset.officeTheme = mode === 'system' ? (media.matches ? 'night' : 'day') : mode;
    document.querySelectorAll('[data-office-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.officeMode === mode)));
  };
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-office-mode]');
    if (!button || !['day', 'night', 'system'].includes(button.dataset.officeMode)) return;
    mode = button.dataset.officeMode;
    try { localStorage.setItem(key, mode); } catch (_) {}
    apply();
  });
  media.addEventListener('change', apply);
  window.addEventListener('storage', event => {
    if (event.key !== key) return;
    mode = ['day', 'night', 'system'].includes(event.newValue) ? event.newValue : 'system';
    apply();
  });
})();

/*
 * Office-test role preview persists across Office pages and browser tabs.
 * This is a visual preference only: never read this value in PHP ACL or grants.
 */
(() => {
  'use strict';
  const key = 'brebo-office-role-preview';
  const valid = picker => Array.from(picker.options).map(option => option.value);
  const read = picker => {
    const options = valid(picker);
    try {
      const saved = localStorage.getItem(key);
      if (saved && options.includes(saved)) return saved;
      // Migrate previous per-tab preference from the old role preview.
      const old = sessionStorage.getItem(key);
      if (old && options.includes(old)) {
        localStorage.setItem(key, old);
        return old;
      }
    } catch (_) {}
    return 'actual';
  };
  const init = () => {
    const picker = document.getElementById('office-role-preview');
    const status = document.getElementById('office-role-preview-status');
    if (!picker || !status) return;
    const display = () => {
      const option = picker.options[picker.selectedIndex];
      const label = option ? option.textContent.trim() : 'Eigen account';
      document.body.dataset.officePreviewRole = picker.value;
      status.textContent = picker.value === 'actual'
        ? 'Eigen account · werkelijke rechten'
        : 'Voorbeeld: ' + label + ' · rechten ongewijzigd';
      status.title = 'Voorbeeldweergave; geen wijziging van toegangsrechten';
      status.setAttribute('data-office-preview-active', String(picker.value !== 'actual'));
    };
    const restore = () => {
      picker.value = read(picker);
      display();
    };
    picker.addEventListener('change', () => {
      try {
        localStorage.setItem(key, picker.value);
        sessionStorage.removeItem(key);
      } catch (_) {}
      display();
    });
    window.addEventListener('storage', event => {
      if (event.key === key) restore();
    });
    // Back-forward cache restores a page without a fresh DOMContentLoaded.
    window.addEventListener('pageshow', restore);
    restore();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
