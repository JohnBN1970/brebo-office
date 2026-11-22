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
 * Office-test function preview lives in the same centrally CSP-hashed script
 * as the theme switcher. This is presentation only; server authorization always
 * uses the actual authenticated Office operator.
 */
(() => {
  'use strict';
  const key = 'brebo-office-role-preview';
  const apply = () => {
    const picker = document.getElementById('office-role-preview');
    const status = document.getElementById('office-role-preview-status');
    if (!picker || !status) return;
    let selected = 'actual';
    try {
      const stored = sessionStorage.getItem(key);
      if (stored && Array.from(picker.options).some(option => option.value === stored)) selected = stored;
    } catch (_) {}
    picker.value = selected;
    const update = () => {
      const option = picker.options[picker.selectedIndex];
      const label = option ? option.textContent.trim() : 'Eigen account';
      document.body.dataset.officePreviewRole = picker.value;
      status.textContent = picker.value === 'actual'
        ? 'Eigen account · werkelijke rechten'
        : 'Voorbeeld: ' + label + ' · rechten ongewijzigd';
      status.title = 'Voorbeeldweergave; geen wijziging van toegangsrechten';
      status.setAttribute('data-office-preview-active', String(picker.value !== 'actual'));
    };
    picker.addEventListener('change', () => {
      try { sessionStorage.setItem(key, picker.value); } catch (_) {}
      update();
    });
    update();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
  else apply();
})();
