(function (Drupal, once) {
  'use strict';

  const TILE_SIZE = 256;

  function project(lat, lon, zoom) {
    const scale = TILE_SIZE * Math.pow(2, zoom);
    const latitude = Math.max(-85.05112878, Math.min(85.05112878, lat));
    const sin = Math.sin(latitude * Math.PI / 180);
    return {
      x: ((lon + 180) / 360) * scale,
      y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale,
    };
  }

  function chooseZoom(markers) {
    if (markers.length < 2) {
      return 13;
    }
    const lats = markers.map((m) => Number(m.lat));
    const lons = markers.map((m) => Number(m.lon));
    const span = Math.max(Math.max(...lats) - Math.min(...lats), Math.max(...lons) - Math.min(...lons));
    if (span > 4) return 6;
    if (span > 2) return 7;
    if (span > 1) return 8;
    if (span > 0.5) return 9;
    if (span > 0.2) return 10;
    if (span > 0.08) return 11;
    if (span > 0.03) return 12;
    return 13;
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function renderMap(element, markers) {
    let zoom = chooseZoom(markers);
    const center = {
      lat: markers.reduce((sum, marker) => sum + Number(marker.lat), 0) / markers.length,
      lon: markers.reduce((sum, marker) => sum + Number(marker.lon), 0) / markers.length,
    };

    const canvas = document.createElement('div');
    canvas.className = 'brebo-buildings-map__canvas';
    element.replaceChildren(canvas);

    const tiles = document.createElement('div');
    tiles.className = 'brebo-buildings-map__tiles';
    canvas.appendChild(tiles);

    const markersLayer = document.createElement('div');
    markersLayer.className = 'brebo-buildings-map__markers';
    canvas.appendChild(markersLayer);

    const controls = document.createElement('div');
    controls.className = 'brebo-buildings-map__controls';
    controls.innerHTML = '<button type="button" data-map-zoom-in aria-label="Inzoomen">+</button><button type="button" data-map-zoom-out aria-label="Uitzoomen">−</button>';
    canvas.appendChild(controls);

    const attribution = document.createElement('a');
    attribution.className = 'brebo-buildings-map__attribution';
    attribution.href = 'https://www.openstreetmap.org/copyright';
    attribution.target = '_blank';
    attribution.rel = 'noopener noreferrer';
    attribution.textContent = '© OpenStreetMap';
    canvas.appendChild(attribution);

    function draw() {
      tiles.replaceChildren();
      markersLayer.replaceChildren();
      const width = element.clientWidth || 900;
      const height = element.clientHeight || 440;
      const centerPoint = project(center.lat, center.lon, zoom);
      const left = centerPoint.x - width / 2;
      const top = centerPoint.y - height / 2;
      const maxTile = Math.pow(2, zoom);
      const startX = Math.floor(left / TILE_SIZE);
      const endX = Math.floor((left + width) / TILE_SIZE);
      const startY = Math.floor(top / TILE_SIZE);
      const endY = Math.floor((top + height) / TILE_SIZE);

      for (let x = startX; x <= endX; x++) {
        for (let y = startY; y <= endY; y++) {
          if (y < 0 || y >= maxTile) continue;
          const wrappedX = ((x % maxTile) + maxTile) % maxTile;
          const image = document.createElement('img');
          image.className = 'brebo-buildings-map__tile';
          image.alt = '';
          image.loading = 'lazy';
          image.src = `https://tile.openstreetmap.org/${zoom}/${wrappedX}/${y}.png`;
          image.style.left = `${x * TILE_SIZE - left}px`;
          image.style.top = `${y * TILE_SIZE - top}px`;
          tiles.appendChild(image);
        }
      }

      markers.forEach((marker) => {
        const point = project(Number(marker.lat), Number(marker.lon), zoom);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'brebo-buildings-map__marker';
        button.style.left = `${point.x - left}px`;
        button.style.top = `${point.y - top}px`;
        button.setAttribute('aria-label', marker.title || 'Gebouw');
        button.innerHTML = '<span></span>';
        button.addEventListener('click', () => {
          markersLayer.querySelectorAll('.brebo-buildings-map__popup').forEach((popup) => popup.remove());
          const popup = document.createElement('div');
          popup.className = 'brebo-buildings-map__popup';
          popup.style.left = `${point.x - left}px`;
          popup.style.top = `${point.y - top}px`;
          popup.innerHTML = `<strong>${escapeHtml(marker.title)}</strong><span>${escapeHtml(marker.address)}${marker.city ? ', ' + escapeHtml(marker.city) : ''}</span><span>${Number(marker.units) || 0} BAG-eenheden</span><a href="${escapeHtml(marker.url)}">Gebouw openen</a>`;
          markersLayer.appendChild(popup);
        });
        markersLayer.appendChild(button);
      });
    }

    controls.querySelector('[data-map-zoom-in]').addEventListener('click', () => {
      zoom = Math.min(18, zoom + 1);
      draw();
    });
    controls.querySelector('[data-map-zoom-out]').addEventListener('click', () => {
      zoom = Math.max(5, zoom - 1);
      draw();
    });

    draw();
    window.addEventListener('resize', Drupal.debounce(draw, 120));
  }

  Drupal.behaviors.breboBuildingsDashboardMap = {
    attach(context) {
      once('brebo-buildings-dashboard-map', '[data-brebo-buildings-map]', context).forEach((element) => {
        let markers = [];
        try {
          markers = JSON.parse(element.dataset.markers || '[]');
        }
        catch (error) {
          markers = [];
        }
        markers = markers.filter((marker) => Number.isFinite(Number(marker.lat)) && Number.isFinite(Number(marker.lon)));
        if (markers.length > 0) {
          renderMap(element, markers);
        }
      });
    },
  };
})(Drupal, once);
