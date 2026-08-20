(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboInzetMobileClock = {
    attach: function (context) {
      once('brebo-inzet-mobile-clock', '[data-brebo-mobile-clock]', context).forEach(function (root) {
        var status = root.querySelector('[data-brebo-clock-location-status]');
        var lat = root.querySelector('input[name="clock_latitude"]');
        var lng = root.querySelector('input[name="clock_longitude"]');
        var accuracy = root.querySelector('input[name="clock_accuracy"]');
        var buttons = root.querySelectorAll('button[type="submit"], input[type="submit"]');

        function disableButtons(disabled) {
          buttons.forEach(function (button) { button.disabled = disabled; });
        }

        function setStatus(message) {
          if (status) { status.textContent = message; }
        }

        if (!navigator.geolocation) {
          setStatus('Locatiebepaling wordt niet ondersteund op dit apparaat.');
          return;
        }

        disableButtons(true);
        setStatus('Werkzone bepalen…');

        navigator.geolocation.getCurrentPosition(function (position) {
          lat.value = position.coords.latitude.toFixed(8);
          lng.value = position.coords.longitude.toFixed(8);
          accuracy.value = position.coords.accuracy.toFixed(2);
          setStatus('Locatie bepaald (nauwkeurigheid circa ' + Math.round(position.coords.accuracy) + ' m).');
          disableButtons(false);
        }, function () {
          setStatus('Locatie kon niet worden bepaald. Klokken blijft mogelijk, maar wordt als locatie-afwijking geregistreerd.');
          disableButtons(false);
        }, {
          enableHighAccuracy: true,
          timeout: 12000,
          maximumAge: 0
        });
      });
    }
  };
})(Drupal, once);
