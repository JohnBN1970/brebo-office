(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboResidenceCockpit = {
    attach(context) {
      once('brebo-residence-cockpit', 'details', context).forEach((identity) => {
        const summary = identity.querySelector(':scope > summary');
        if (!summary || !summary.textContent.includes('Woning / gebruiksobject')) return;
        const root = identity.parentElement;
        if (!root) return;
        root.classList.add('brebo-residence-cockpit', 'brebo-cockpit');
        identity.classList.add('brebo-residence-identity');

        const text = identity.textContent;
        const valueAfter = (label) => {
          const match = text.match(new RegExp(label + ':\\s*([^\\n]+)', 'i'));
          return match ? match[1].trim() : '—';
        };
        const occupancy = valueAfter('Bewoning');
        const access = valueAfter('Toegang');
        const accessClass = /gereed|akkoord|toegang/i.test(access) && !/geen|niet|geweigerd|onbekend/i.test(access) ? 'positive' : /geweigerd|geen|niet/i.test(access) ? 'critical' : 'attention';

        const tables = Array.from(root.querySelectorAll(':scope > table'));
        const byCaption = (needle) => tables.find((table) => {
          const caption = table.querySelector('caption');
          return caption && caption.textContent.includes(needle);
        });
        const residents = byCaption('Bewoners / contactpersonen');
        const cases = byCaption('Meldingen, klachten, schade & service');
        const photos = byCaption('Foto');
        const timeline = byCaption('Dossierhistorie');
        const rowCount = (table) => table ? table.querySelectorAll('tbody tr').length : 0;

        const kpis = document.createElement('section');
        kpis.className = 'brebo-kpis brebo-residence-kpis';
        kpis.setAttribute('aria-label', Drupal.t('Woningsamenvatting'));
        kpis.innerHTML = `
          <div class="brebo-kpi brebo-kpi--neutral"><strong class="brebo-kpi__value brebo-kpi__value--text">${occupancy}</strong><span class="brebo-kpi__label">${Drupal.t('Bewoning')}</span></div>
          <div class="brebo-kpi brebo-kpi--${accessClass}"><strong class="brebo-kpi__value brebo-kpi__value--text">${access}</strong><span class="brebo-kpi__label">${Drupal.t('Toegang')}</span></div>
          <div class="brebo-kpi brebo-kpi--neutral"><strong class="brebo-kpi__value">${rowCount(residents)}</strong><span class="brebo-kpi__label">${Drupal.t('Contactpersonen')}</span></div>
          <div class="brebo-kpi brebo-kpi--${rowCount(cases) ? 'attention' : 'positive'}"><strong class="brebo-kpi__value">${rowCount(cases)}</strong><span class="brebo-kpi__label">${Drupal.t('Dossiers')}</span></div>`;
        identity.insertAdjacentElement('afterend', kpis);

        [residents, cases, photos, timeline].forEach((table) => {
          if (table) table.classList.add('brebo-residence-table');
        });
        if (timeline) timeline.classList.add('brebo-residence-history');
      });
    }
  };
})(Drupal, once);
