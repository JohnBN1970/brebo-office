(function (Drupal, drupalSettings, once) {
  'use strict';

  const MODES = ['day', 'night', 'system'];
  const LABELS = {
    day: Drupal.t('Dag'),
    night: Drupal.t('Nacht'),
    system: Drupal.t('Systeem'),
  };
  const ICONS = {
    day: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"></path></svg>',
    night: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"></path></svg>',
    system: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"></rect><path d="M8 20h8M12 16v4"></path></svg>',
  };

  function settings() {
    return drupalSettings.breboOfficeAppearance || {};
  }

  function storageKey() {
    const userId = Number(settings().userId || 0);
    return `brebo-office-theme:${userId}`;
  }

  function readMode() {
    const serverMode = settings().defaultMode;
    if (MODES.includes(serverMode)) {
      return serverMode;
    }
    try {
      const stored = window.localStorage.getItem(storageKey());
      return MODES.includes(stored) ? stored : 'system';
    }
    catch (error) {
      return 'system';
    }
  }

  function saveLocalMode(mode) {
    if (!MODES.includes(mode)) return;
    try { window.localStorage.setItem(storageKey(), mode); }
    catch (error) {}
  }

  async function persistMode(mode) {
    const persistUrl = settings().persistUrl;
    if (!persistUrl || !MODES.includes(mode)) return;
    try {
      const tokenResponse = await fetch(Drupal.url('session/token'), {credentials: 'same-origin'});
      if (!tokenResponse.ok) return;
      const token = await tokenResponse.text();
      const response = await fetch(persistUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': token},
        body: JSON.stringify({mode}),
      });
      if (response.ok) {
        settings().defaultMode = mode;
        try { window.localStorage.removeItem(storageKey()); }
        catch (error) {}
      }
    }
    catch (error) {}
  }

  function applyMode(mode) {
    const safeMode = MODES.includes(mode) ? mode : 'system';
    document.documentElement.dataset.breboTheme = safeMode;
    document.documentElement.dataset.breboThemePreference = safeMode;
    document.querySelectorAll('[data-brebo-theme-mode]').forEach((button) => {
      button.setAttribute('aria-pressed', button.dataset.breboThemeMode === safeMode ? 'true' : 'false');
    });
  }

  function buildSwitcher() {
    const wrapper = document.createElement('div');
    wrapper.className = 'brebo-theme-switcher';
    wrapper.setAttribute('role', 'group');
    wrapper.setAttribute('aria-label', Drupal.t('Weergavemodus'));

    MODES.forEach((mode) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'brebo-theme-switcher__button';
      button.dataset.breboThemeMode = mode;
      button.innerHTML = `<span class="brebo-theme-switcher__icon">${ICONS[mode]}</span><span class="visually-hidden">${LABELS[mode]}</span>`;
      button.setAttribute('aria-label', LABELS[mode]);
      button.title = Drupal.t('Weergave: @mode', {'@mode': LABELS[mode]});
      button.addEventListener('click', () => {
        saveLocalMode(mode);
        applyMode(mode);
        persistMode(mode);
      });
      wrapper.appendChild(button);
    });
    return wrapper;
  }

  applyMode(readMode());
  Drupal.behaviors.breboOfficeThemePreference = {
    attach(context) {
      once('brebo-theme-switcher', '.brebo-office-nav__footer', context).forEach((footer) => {
        footer.prepend(buildSwitcher());
        applyMode(readMode());
      });
    },
  };
})(Drupal, drupalSettings, once);
