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
    const fallback = MODES.includes(settings().defaultMode) ? settings().defaultMode : 'system';
    try {
      const stored = window.localStorage.getItem(storageKey());
      return MODES.includes(stored) ? stored : fallback;
    }
    catch (error) {
      return fallback;
    }
  }

  function saveMode(mode) {
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
        saveMode(mode);
        applyMode(mode);
      });
      wrapper.appendChild(button);
    });

    return wrapper;
  }

  // Apply the preference as soon as this asset executes.
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
