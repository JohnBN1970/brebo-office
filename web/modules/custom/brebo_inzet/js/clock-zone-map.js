(function (Drupal, once) {
  'use strict';

  const earthRadius = 6378137;

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
        const halfHeightMetres = 350;

        function render() {
          const width = Math.max(canvas.clientWidth, 700);
          const height = Math.max(canvas.clientHeight, 420);
          const halfWidthMetres = halfHeightMetres * (width / height);
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
          const radius = Math.min(5000, Math.max(10, Number.parseFloat(radiusInput.value) || 150));
          const pxPerMetre = height / (halfHeightMetres * 2);
          const diameter = Math.max(18, radius * 2 * pxPerMetre);
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

        let drag = null;
        marker.addEventListener('pointerdown', (event) => {
          drag = {pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, startLat: lat, startLon: lon};
          marker.setPointerCapture(event.pointerId);
        });
        marker.addEventListener('pointermove', (event) => {
          if (!drag || drag.pointerId !== event.pointerId) return;
          const rect = canvas.getBoundingClientRect();
          const dx = event.clientX - drag.startX;
          const dy = event.clientY - drag.startY;
          marker.style.left = `calc(50% + ${dx}px)`;
          marker.style.top = `calc(50% + ${dy}px)`;
        });
        marker.addEventListener('pointerup', (event) => {
          if (!drag || drag.pointerId !== event.pointerId) return;
          const rect = canvas.getBoundingClientRect();
          const metresPerPixel = (halfHeightMetres * 2) / rect.height;
          const dx = event.clientX - drag.startX;
          const dy = event.clientY - drag.startY;
          const center = mercator(drag.startLat, drag.startLon);
          const moved = inverseMercator(center.x + dx * metresPerPixel, center.y - dy * metresPerPixel);
          lat = moved.lat;
          lon = moved.lon;
          marker.releasePointerCapture(event.pointerId);
          drag = null;
          syncInputs();
        });
        marker.addEventListener('pointercancel', () => {
          drag = null;
          marker.style.left = '50%';
          marker.style.top = '50%';
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
