/**
 * Renders a dependency-free PDOK aerial tile grid around a stored building.
 */
(function (Drupal) {
  'use strict';

  Drupal.behaviors.breboBuildingMap = {
    attach(context) {
      context.querySelectorAll('[data-brebo-building-map="true"]').forEach((map) => {
        if (map.dataset.mapReady === 'true') {
          return;
        }

        const latitude = Number.parseFloat(map.dataset.latitude);
        const longitude = Number.parseFloat(map.dataset.longitude);
        const canvas = map.querySelector('.brebo-building-map__tiles');
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || !canvas) {
          return;
        }

        const zoom = 19;
        const scale = 2 ** zoom;
        const centerX = Math.floor(((longitude + 180) / 360) * scale);
        const latitudeRadians = latitude * Math.PI / 180;
        const centerY = Math.floor(
          (1 - Math.asinh(Math.tan(latitudeRadians)) / Math.PI) / 2 * scale
        );

        for (let row = -1; row <= 1; row += 1) {
          for (let column = -1; column <= 1; column += 1) {
            const tile = document.createElement('img');
            tile.src = `https://service.pdok.nl/hwh/luchtfotorgb/wmts/v1_0/Actueel_orthoHR/EPSG:3857/${zoom}/${centerX + column}/${centerY + row}.jpeg`;
            tile.alt = '';
            tile.loading = 'eager';
            tile.decoding = 'async';
            tile.style.left = `${(column + 1) * 33.333333}%`;
            tile.style.top = `${(row + 1) * 33.333333}%`;
            canvas.appendChild(tile);
          }
        }

        const marker = document.createElement('span');
        marker.className = 'brebo-building-map__marker';
        marker.setAttribute('aria-hidden', 'true');
        canvas.appendChild(marker);
        map.dataset.mapReady = 'true';
      });
    }
  };
})(Drupal);
