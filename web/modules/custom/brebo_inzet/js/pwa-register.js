(function () {
  'use strict';

  const markPwaShell = () => {
    document.documentElement.classList.add('brebo-inzet-pwa');
    if (document.body) {
      document.body.classList.add('brebo-inzet-pwa');
    }
  };

  markPwaShell();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markPwaShell, {once: true});
  }

  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/brebo-inzet-sw.js', {scope: '/'})
      .catch((error) => console.warn('BREBO Inzet service worker kon niet worden geregistreerd.', error));
  }, {once: true});
})();
