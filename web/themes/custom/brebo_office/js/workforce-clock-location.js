(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboWorkforceClockLocation = {
    attach(context) {
      once('brebo-workforce-clock-location', '.brebo-inzet-clock', context).forEach((form) => {
        const status = form.querySelector('[data-brebo-clock-location-status]');
        const lat = form.querySelector('[data-brebo-clock-lat]');
        const lon = form.querySelector('[data-brebo-clock-lon]');
        const accuracy = form.querySelector('[data-brebo-clock-accuracy]');
        if (!navigator.geolocation) {
          if (status) status.textContent = 'Locatie niet beschikbaar op dit apparaat; geef bij klokken een toelichting.';
          return;
        }
        if (status) status.textContent = 'Werkplek wordt eenmalig bepaald…';
        navigator.geolocation.getCurrentPosition(
          (position) => {
            if (lat) lat.value = position.coords.latitude;
            if (lon) lon.value = position.coords.longitude;
            if (accuracy) accuracy.value = position.coords.accuracy || '';
            if (status) status.textContent = 'Locatie ontvangen. Alleen dit klokmoment gebruikt deze positie.';
          },
          () => {
            if (status) status.textContent = 'Locatie kon niet worden bepaald; geef bij klokken een toelichting.';
          },
          {enableHighAccuracy: true, timeout: 10000, maximumAge: 30000}
        );
      });
    }
  };
})(Drupal, once);
