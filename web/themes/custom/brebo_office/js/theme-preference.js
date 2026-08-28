(function (Drupal, drupalSettings, once) {
  'use strict';

  const MODES = ['day', 'night', 'system'];
  const LABELS = {
    day: Drupal.t('Dag'),
    night: Drupal.t('Nacht'),
    system: Drupal.t('Systeem'),
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
    if (!MODES.includes(mode)) {
      return;
    }
    try {
      window.localStorage.setItem(storageKey(), mode);
    }
    catch (error) {
      // Storage can be unavailable in privacy-restricted browser contexts.
    }
  }

  async function persistMode(mode) {
    const persistUrl = settings().persistUrl;
    if (!persistUrl || !MODES.includes(mode)) {
      return;
    }

    try {
      const tokenResponse = await fetch(Drupal.url('session/token'), {
        credentials: 'same-origin',
      });
      if (!tokenResponse.ok) {
        return;
      }

      const token = await tokenResponse.text();
      const response = await fetch(persistUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify({mode}),
      });

      if (response.ok) {
        settings().defaultMode = mode;
        try {
          window.localStorage.removeItem(storageKey());
        }
        catch (error) {
          // Local fallback cleanup is optional.
        }
      }
    }
    catch (error) {
      // The local preference remains active if server persistence is unavailable.
    }
  }

  function applyMode(mode) {
    const safeMode = MODES.includes(mode) ? mode : 'system';
    document.documentElement.dataset.breboTheme = safeMode;
    document.documentElement.dataset.breboThemePreference = safeMode;

    document.querySelectorAll('[data-brebo-theme-mode]').forEach((button) => {
      const active = button.dataset.breboThemeMode === safeMode;
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
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
      button.textContent = LABELS[mode];
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

  // Apply the server preference as soon as this asset executes.
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
