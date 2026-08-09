/**
 * Renders one correctly proportioned PDOK aerial image around a building.
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

        const earthRadius = 6378137;
        const longitudeRadians = longitude * Math.PI / 180;
        const latitudeRadians = Math.max(
          Math.min(latitude * Math.PI / 180, 1.4844222297453324),
          -1.4844222297453324
        );
        const centerX = earthRadius * longitudeRadians;
        const centerY = earthRadius * Math.log(Math.tan(Math.PI / 4 + latitudeRadians / 2));

        const displayWidth = Math.max(canvas.clientWidth, 800);
        const displayHeight = Math.max(canvas.clientHeight, 360);
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        const imageWidth = Math.min(Math.round(displayWidth * pixelRatio), 2048);
        const imageHeight = Math.min(Math.round(displayHeight * pixelRatio), 1024);

        const halfHeightMetres = 52;
        const halfWidthMetres = halfHeightMetres * (displayWidth / displayHeight);
        const boundingBox = [
          centerX - halfWidthMetres,
          centerY - halfHeightMetres,
          centerX + halfWidthMetres,
          centerY + halfHeightMetres
        ].join(',');

        const parameters = new URLSearchParams({
          service: 'WMS',
          version: '1.3.0',
          request: 'GetMap',
          layers: 'Actueel_orthoHR',
          styles: '',
          crs: 'EPSG:3857',
          bbox: boundingBox,
          width: String(imageWidth),
          height: String(imageHeight),
          format: 'image/jpeg',
          transparent: 'false'
        });

        const aerialImage = document.createElement('img');
        aerialImage.className = 'brebo-building-map__image';
        aerialImage.src = `https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?${parameters.toString()}`;
        aerialImage.alt = '';
        aerialImage.loading = 'eager';
        aerialImage.decoding = 'async';
        canvas.appendChild(aerialImage);

        const marker = document.createElement('span');
        marker.className = 'brebo-building-map__marker';
        marker.setAttribute('aria-hidden', 'true');
        canvas.appendChild(marker);
        map.dataset.mapReady = 'true';
      });
    }
  };
})(Drupal);
