(function (Drupal) {
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

  function initialiseMap(map) {
    if (map.dataset.breboClockZoneInitialised === 'true') return;

    const form = map.closest('form') || document;
    const latInput = form.querySelector('[name="latitude"], #edit-latitude');
    const lonInput = form.querySelector('[name="longitude"], #edit-longitude');
    const radiusInput = form.querySelector('[name="radius"], #edit-radius');
    const canvas = map.querySelector('.brebo-clock-zone-map__canvas');
    const circle = map.querySelector('.brebo-clock-zone-map__circle');
    const readout = map.querySelector('.brebo-clock-zone-map__readout');
    if (!latInput || !lonInput || !radiusInput || !canvas || !circle) return;

    let marker = map.querySelector('.brebo-clock-zone-map__marker');
    if (!marker) {
      marker = document.createElement('button');
      marker.type = 'button';
      marker.className = 'brebo-clock-zone-map__marker';
      marker.setAttribute('aria-label', 'Versleep middelpunt kloklocatie');
      canvas.appendChild(marker);
    }
    marker.title = 'Middelpunt klokzone';

    const radiusHandle = document.createElement('button');
    radiusHandle.type = 'button';
    radiusHandle.className = 'brebo-clock-zone-map__radius-handle';
    radiusHandle.setAttribute('aria-label', 'Versleep om de klokzone groter of kleiner te maken');
    radiusHandle.title = 'Versleep om de klokzone groter of kleiner te maken';
    canvas.appendChild(radiusHandle);

    map.dataset.breboClockZoneInitialised = 'true';
    let lat = Number.parseFloat(latInput.value) || 52.370216;
    let lon = Number.parseFloat(lonInput.value) || 4.895168;
    let zoom = 18;
    let labelsVisible = true;
    let houseNumbersVisible = true;

    const labels = document.createElement('div');
    labels.className = 'brebo-clock-zone-map__labels';
    canvas.appendChild(labels);

    const houseNumbers = document.createElement('img');
    houseNumbers.className = 'brebo-clock-zone-map__house-numbers';
    houseNumbers.alt = '';
    canvas.appendChild(houseNumbers);

    const radiusBadge = document.createElement('div');
    radiusBadge.className = 'brebo-clock-zone-map__radius-badge';
    canvas.appendChild(radiusBadge);

    const controls = document.createElement('div');
    controls.className = 'brebo-clock-zone-map__controls';
    controls.innerHTML = '<button type="button" data-map-zoom-in aria-label="Inzoomen">+</button><button type="button" data-map-zoom-out aria-label="Uitzoomen">−</button><button type="button" data-map-labels aria-pressed="true">Straatnamen</button><button type="button" data-map-house-numbers aria-pressed="true">Huisnummers</button>';
    const buildingLat = Number.parseFloat(map.dataset.buildingLatitude || '');
    const buildingLon = Number.parseFloat(map.dataset.buildingLongitude || '');
    if (Number.isFinite(buildingLat) && Number.isFinite(buildingLon)) {
      controls.insertAdjacentHTML('beforeend', '<button type="button" data-map-reset-building>Terug naar gebouw</button>');
    }
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
          const params = new URLSearchParams({service:'WMTS',request:'GetTile',version:'1.0.0',layer:'labels',style:'default',format:'image/png',tilematrixset:'EPSG:3857',tilematrix:`EPSG:3857:${zoom}`,tilerow:String(tileY),tilecol:String(wrappedX)});
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

    function currentRadius() {
      return Math.min(5000, Math.max(10, Number.parseFloat(radiusInput.value) || 150));
    }

    function updateRadiusPresentation(metresPerPixel) {
      const radius = currentRadius();
      const radiusPixels = Math.max(9, radius / metresPerPixel);
      const diameter = radiusPixels * 2;
      circle.style.width = `${diameter}px`;
      circle.style.height = `${diameter}px`;
      radiusHandle.style.left = `calc(50% + ${radiusPixels}px)`;
      radiusHandle.style.top = '50%';
      radiusBadge.innerHTML = `Klokzone <strong>${Math.round(radius)} m</strong>`;
      if (readout) readout.textContent = `${Math.round(radius)} m`;
    }

    function render() {
      const width = Math.max(canvas.clientWidth, 600);
      const height = Math.max(canvas.clientHeight, 600);
      const metresPerPixel = resolution();
      const halfHeightMetres = metresPerPixel * height / 2;
      const halfWidthMetres = metresPerPixel * width / 2;
      const center = mercator(lat, lon);
      const bbox = [center.x-halfWidthMetres,center.y-halfHeightMetres,center.x+halfWidthMetres,center.y+halfHeightMetres].join(',');
      let image = canvas.querySelector('.brebo-clock-zone-map__image');
      if (!image) {
        image = document.createElement('img');
        image.className = 'brebo-clock-zone-map__image';
        image.alt = '';
        canvas.prepend(image);
      }
      const params = new URLSearchParams({service:'WMS',version:'1.3.0',request:'GetMap',layers:'Actueel_orthoHR',styles:'',crs:'EPSG:3857',bbox,width:String(Math.round(width)),height:String(Math.round(height)),format:'image/jpeg',transparent:'false'});
      image.src = `https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?${params.toString()}`;

      const houseParams = new URLSearchParams({service:'WMS',version:'1.3.0',request:'GetMap',layers:'Kadastralekaart',styles:'Default',crs:'EPSG:3857',bbox,width:String(Math.round(width)),height:String(Math.round(height)),format:'image/png',transparent:'true'});
      houseNumbers.src = `https://service.pdok.nl/kadaster/kadastralekaart/wms/v5_0?${houseParams.toString()}`;
      houseNumbers.hidden = !houseNumbersVisible || zoom < 17;

      renderLabels(width, height, center);
      updateRadiusPresentation(metresPerPixel);
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
      markerDrag = {pointerId:event.pointerId,startX:event.clientX,startY:event.clientY};
      marker.setPointerCapture(event.pointerId);
    });
    marker.addEventListener('pointermove', (event) => {
      if (!markerDrag || markerDrag.pointerId !== event.pointerId) return;
      marker.style.left = `calc(50% + ${event.clientX-markerDrag.startX}px)`;
      marker.style.top = `calc(50% + ${event.clientY-markerDrag.startY}px)`;
    });
    marker.addEventListener('pointerup', (event) => {
      if (!markerDrag || markerDrag.pointerId !== event.pointerId) return;
      const dx = event.clientX-markerDrag.startX;
      const dy = event.clientY-markerDrag.startY;
      marker.releasePointerCapture(event.pointerId);
      markerDrag = null;
      moveByPixels(dx,dy);
    });

    let radiusDrag = null;
    radiusHandle.addEventListener('pointerdown', (event) => {
      event.preventDefault();
      event.stopPropagation();
      radiusDrag = {pointerId:event.pointerId};
      radiusHandle.setPointerCapture(event.pointerId);
      radiusHandle.classList.add('is-dragging');
    });
    radiusHandle.addEventListener('pointermove', (event) => {
      if (!radiusDrag || radiusDrag.pointerId !== event.pointerId) return;
      const rect = canvas.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const pixels = Math.hypot(event.clientX - centerX, event.clientY - centerY);
      const metres = Math.min(5000, Math.max(10, pixels * resolution()));
      radiusInput.value = String(Math.round(metres));
      updateRadiusPresentation(resolution());
      radiusInput.dispatchEvent(new Event('input', {bubbles:true}));
    });
    radiusHandle.addEventListener('pointerup', (event) => {
      if (!radiusDrag || radiusDrag.pointerId !== event.pointerId) return;
      const snapped = Math.min(5000, Math.max(10, Math.round(currentRadius() / 5) * 5));
      radiusInput.value = String(snapped);
      radiusHandle.releasePointerCapture(event.pointerId);
      radiusHandle.classList.remove('is-dragging');
      radiusDrag = null;
      render();
      radiusInput.dispatchEvent(new Event('change', {bubbles:true}));
    });
    radiusHandle.addEventListener('pointercancel', () => {
      radiusDrag = null;
      radiusHandle.classList.remove('is-dragging');
    });

    let mapDrag = null;
    canvas.addEventListener('pointerdown', (event) => {
      if (event.target.closest('.brebo-clock-zone-map__controls') || event.target === marker || event.target === radiusHandle) return;
      mapDrag = {pointerId:event.pointerId,startX:event.clientX,startY:event.clientY};
      canvas.setPointerCapture(event.pointerId);
      canvas.classList.add('is-dragging');
    });
    canvas.addEventListener('pointerup', (event) => {
      if (!mapDrag || mapDrag.pointerId !== event.pointerId) return;
      const dx = mapDrag.startX-event.clientX;
      const dy = mapDrag.startY-event.clientY;
      canvas.releasePointerCapture(event.pointerId);
      canvas.classList.remove('is-dragging');
      mapDrag = null;
      moveByPixels(dx,dy);
    });
    canvas.addEventListener('pointercancel', () => { mapDrag=null; canvas.classList.remove('is-dragging'); });
    canvas.addEventListener('dblclick', (event) => {
      if (event.target.closest('.brebo-clock-zone-map__controls') || event.target === radiusHandle) return;
      const rect = canvas.getBoundingClientRect();
      moveByPixels(event.clientX-(rect.left+rect.width/2),event.clientY-(rect.top+rect.height/2));
    });
    canvas.addEventListener('wheel', (event) => {
      event.preventDefault();
      zoom = Math.min(20,Math.max(14,zoom+(event.deltaY<0?1:-1)));
      render();
    }, {passive:false});
    controls.querySelector('[data-map-zoom-in]').addEventListener('click', () => { zoom=Math.min(20,zoom+1); render(); });
    controls.querySelector('[data-map-zoom-out]').addEventListener('click', () => { zoom=Math.max(14,zoom-1); render(); });
    controls.querySelector('[data-map-labels]').addEventListener('click', (event) => {
      labelsVisible=!labelsVisible;
      event.currentTarget.setAttribute('aria-pressed',labelsVisible?'true':'false');
      render();
    });
    controls.querySelector('[data-map-house-numbers]').addEventListener('click', (event) => {
      houseNumbersVisible=!houseNumbersVisible;
      event.currentTarget.setAttribute('aria-pressed',houseNumbersVisible?'true':'false');
      render();
    });
    const resetBuilding = controls.querySelector('[data-map-reset-building]');
    if (resetBuilding) {
      resetBuilding.addEventListener('click', () => {
        lat = buildingLat;
        lon = buildingLon;
        zoom = 18;
        syncInputs();
      });
    }
    radiusInput.addEventListener('input',render);
    latInput.addEventListener('change',() => { const value=Number.parseFloat(latInput.value); if(Number.isFinite(value)) lat=value; render(); });
    lonInput.addEventListener('change',() => { const value=Number.parseFloat(lonInput.value); if(Number.isFinite(value)) lon=value; render(); });
    window.addEventListener('resize',render);
    syncInputs();
  }

  function attach(context) {
    (context || document).querySelectorAll('[data-brebo-clock-zone-map="true"]').forEach(initialiseMap);
  }

  Drupal.behaviors.breboClockZoneMap = {attach};
  window.setTimeout(() => attach(document), 0);
})(Drupal);
