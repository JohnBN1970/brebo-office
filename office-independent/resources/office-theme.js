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
