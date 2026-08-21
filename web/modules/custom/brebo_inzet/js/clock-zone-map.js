(function (Drupal, once) {
  'use strict';

  const earthRadius = 6378137;
  const worldSize = 2 * Math.PI * earthRadius;
  const tileSize = 256;

  function mercator(lat, lon) {
    const x = earthRadius * lon * Math.PI / 180;
    const clamped = Math.max(Math.min(lat * Math.PI / 180, 1.4844222297453324), -1.4844222297453324);
    const y = earthRadius * Math.log(Math.tan(Math.PI / 4 + clamped / 2));
    return {x, y};
  }

  function inverseMercator(x, y) {
    return {
      lon: x / earthRadius * 180 / Math.PI,
      lat: (2 * Math.atan(Math.exp(y / earthRadius)) - Math.PI / 2) * 180 / Math.PI
    };
  }

  Drupal.behaviors.breboClockZoneMap = {
    attach(context) {
      once('brebo-clock-zone-map', '[data-brebo-clock-zone-map="true"]', context).forEach((map) => {
        const latInput = document.querySelector('[name="latitude"]');
        const lonInput = document.querySelector('[name="longitude"]');
        const radiusInput = document.querySelector('[name="radius"]');
        const canvas = map.querySelector('.brebo-clock-zone-map__canvas');
        const marker = map.querySelector('.brebo-clock-zone-map__marker');
        const circle = map.querySelector('.brebo-clock-zone-map__circle');
        const readout = map.querySelector('.brebo-clock-zone-map__readout');
        if (!latInput || !lonInput || !radiusInput || !canvas || !marker || !circle) return;

        let lat = Number.parseFloat(latInput.value) || 52.370216;
        let lon = Number.parseFloat(lonInput.value) || 4.895168;
        let zoom = 17;
        let labelsVisible = true;

        const labels = document.createElement('div');
        labels.className = 'brebo-clock-zone-map__labels';
        canvas.appendChild(labels);

        const controls = document.createElement('div');
        controls.className = 'brebo-clock-zone-map__controls';
        controls.innerHTML = '<button type="button" data-map-zoom-in aria-label="Inzoomen">+</button><button type="button" data-map-zoom-out aria-label="Uitzoomen">−</button><button type="button" data-map-labels aria-pressed="true">Straatnamen</button>';
        canvas.appendChild(controls);

        function resolution() {
          return worldSize / (tileSize * Math.pow(2, zoom));
        }

        function renderLabels(width, height, center) {
          labels.replaceChildren();
          labels.hidden = !labelsVisible;
          if (!labelsVisible) return;

          const n = Math.pow(2, zoom);
          const centerPxX = ((center.x + worldSize / 2) / worldSize) * n * tileSize;
          const centerPxY = ((worldSize / 2 - center.y) / worldSize) * n * tileSize;
          const left = centerPxX - width / 2;
          const top = centerPxY - height / 2;
          const firstX = Math.floor(left / tileSize);
          const lastX = Math.floor((left + width) / tileSize);
          const firstY = Math.floor(top / tileSize);
          const lastY = Math.floor((top + height) / tileSize);

          for (let tileY = firstY; tileY <= lastY; tileY++) {
            if (tileY < 0 || tileY >= n) continue;
            for (let tileX = firstX; tileX <= lastX; tileX++) {
              const wrappedX = ((tileX % n) + n) % n;
              const params = new URLSearchParams({
                service: 'WMTS', request: 'GetTile', version: '1.0.0',
                layer: 'labels', style: 'default', format: 'image/png',
                tilematrixset: 'EPSG:3857', tilematrix: `EPSG:3857:${zoom}`,
                tilerow: String(tileY), tilecol: String(wrappedX)
              });
              const tile = document.createElement('img');
              tile.className = 'brebo-clock-zone-map__label-tile';
              tile.alt = '';
              tile.src = `https://service.pdok.nl/kadaster/brt-achtergrondkaart/wmts/v2_0?${params.toString()}`;
              tile.style.left = `${tileX * tileSize - left}px`;
              tile.style.top = `${tileY * tileSize - top}px`;
              labels.appendChild(tile);
            }
          }
        }

        function render() {
          const width = Math.max(canvas.clientWidth, 700);
          const height = Math.max(canvas.clientHeight, 420);
          const metresPerPixel = resolution();
          const halfHeightMetres = metresPerPixel * height / 2;
          const halfWidthMetres = metresPerPixel * width / 2;
          const center = mercator(lat, lon);
          const bbox = [center.x - halfWidthMetres, center.y - halfHeightMetres, center.x + halfWidthMetres, center.y + halfHeightMetres].join(',');
          let image = canvas.querySelector('.brebo-clock-zone-map__image');
          if (!image) {
            image = document.createElement('img');
            image.className = 'brebo-clock-zone-map__image';
            image.alt = '';
            canvas.prepend(image);
          }
          const params = new URLSearchParams({service:'WMS',version:'1.3.0',request:'GetMap',layers:'Actueel_orthoHR',styles:'',crs:'EPSG:3857',bbox,width:String(Math.round(width)),height:String(Math.round(height)),format:'image/jpeg',transparent:'false'});
          image.src = `https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?${params}`;
          renderLabels(width, height, center);

          const radius = Math.min(5000, Math.max(10, Number.parseFloat(radiusInput.value) || 150));
          const diameter = Math.max(18, radius * 2 / metresPerPixel);
          circle.style.width = `${diameter}px`;
          circle.style.height = `${diameter}px`;
          if (readout) readout.textContent = `${Math.round(radius)} m`;
        }

        function syncInputs() {
          latInput.value = lat.toFixed(8);
          lonInput.value = lon.toFixed(8);
          marker.style.left = '50%';
          marker.style.top = '50%';
          render();
        }

        function moveByPixels(dx, dy) {
          const center = mercator(lat, lon);
          const moved = inverseMercator(center.x + dx * resolution(), center.y - dy * resolution());
          lat = moved.lat;
          lon = moved.lon;
          syncInputs();
        }

        let markerDrag = null;
        marker.addEventListener('pointerdown', (event) => {
          event.stopPropagation();
          markerDrag = {pointerId: event.pointerId, startX: event.clientX, startY: event.clientY};
          marker.setPointerCapture(event.pointerId);
        });
        marker.addEventListener('pointermove', (event) => {
          if (!markerDrag || markerDrag.pointerId !== event.pointerId) return;
          const dx = event.clientX - markerDrag.startX;
          const dy = event.clientY - markerDrag.startY;
          marker.style.left = `calc(50% + ${dx}px)`;
          marker.style.top = `calc(50% + ${dy}px)`;
        });
        marker.addEventListener('pointerup', (event) => {
          if (!markerDrag || markerDrag.pointerId !== event.pointerId) return;
          const dx = event.clientX - markerDrag.startX;
          const dy = event.clientY - markerDrag.startY;
          marker.releasePointerCapture(event.pointerId);
          markerDrag = null;
          moveByPixels(dx, dy);
        });

        let mapDrag = null;
        canvas.addEventListener('pointerdown', (event) => {
          if (event.target.closest('.brebo-clock-zone-map__controls') || event.target === marker) return;
          mapDrag = {pointerId: event.pointerId, startX: event.clientX, startY: event.clientY};
          canvas.setPointerCapture(event.pointerId);
          canvas.classList.add('is-dragging');
        });
        canvas.addEventListener('pointerup', (event) => {
          if (!mapDrag || mapDrag.pointerId !== event.pointerId) return;
          const dx = mapDrag.startX - event.clientX;
          const dy = mapDrag.startY - event.clientY;
          canvas.releasePointerCapture(event.pointerId);
          canvas.classList.remove('is-dragging');
          mapDrag = null;
          moveByPixels(dx, dy);
        });
        canvas.addEventListener('pointercancel', () => {
          mapDrag = null;
          canvas.classList.remove('is-dragging');
        });

        canvas.addEventListener('dblclick', (event) => {
          if (event.target.closest('.brebo-clock-zone-map__controls')) return;
          const rect = canvas.getBoundingClientRect();
          moveByPixels(event.clientX - (rect.left + rect.width / 2), event.clientY - (rect.top + rect.height / 2));
        });
        canvas.addEventListener('wheel', (event) => {
          event.preventDefault();
          zoom = Math.min(20, Math.max(14, zoom + (event.deltaY < 0 ? 1 : -1)));
          render();
        }, {passive: false});

        controls.querySelector('[data-map-zoom-in]').addEventListener('click', () => { zoom = Math.min(20, zoom + 1); render(); });
        controls.querySelector('[data-map-zoom-out]').addEventListener('click', () => { zoom = Math.max(14, zoom - 1); render(); });
        controls.querySelector('[data-map-labels]').addEventListener('click', (event) => {
          labelsVisible = !labelsVisible;
          event.currentTarget.setAttribute('aria-pressed', labelsVisible ? 'true' : 'false');
          render();
        });

        radiusInput.addEventListener('input', render);
        latInput.addEventListener('change', () => { const value = Number.parseFloat(latInput.value); if (Number.isFinite(value)) lat = value; render(); });
        lonInput.addEventListener('change', () => { const value = Number.parseFloat(lonInput.value); if (Number.isFinite(value)) lon = value; render(); });
        window.addEventListener('resize', render);
        syncInputs();
      });
    }
  };
})(Drupal, once);
