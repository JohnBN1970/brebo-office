(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/brebo-inzet-sw.js', {scope: '/'})
      .catch((error) => console.warn('BREBO Inzet service worker kon niet worden geregistreerd.', error));
  }, {once: true});
})();
