(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboBuildingCockpit = {
    attach(context) {
      once('brebo-building-cockpit', '.brebo-building-map', context).forEach((map) => {
        const root = map.parentElement;
        if (!root) return;
        root.classList.add('brebo-building-cockpit');

        const directTables = Array.from(root.querySelectorAll(':scope > table'));
        const identity = directTables.find((table) =>
          Array.from(table.querySelectorAll('thead th')).some((th) => th.textContent.trim().includes('Gebouwcode'))
        );
        const summary = directTables.find((table) =>
          Array.from(table.querySelectorAll('thead th')).some((th) => th.textContent.trim().includes('Technische zones'))
        );
        if (!summary) return;

        const cells = Array.from(summary.querySelectorAll('tbody tr:first-child td')).map((cell) => cell.textContent.trim());
        const [projects = '0', zones = '0', clusters = '0', dwellings = '0', positions = '0'] = cells;
        summary.hidden = true;

        const kpis = document.createElement('section');
        kpis.className = 'brebo-kpis brebo-building-kpis';
        kpis.setAttribute('aria-label', Drupal.t('Gebouwsamenvatting'));
        kpis.innerHTML = `
          <div class="brebo-kpi brebo-kpi--neutral"><strong class="brebo-kpi__value">${zones}</strong><span class="brebo-kpi__label">${Drupal.t('Technische zones')}</span></div>
          <div class="brebo-kpi brebo-kpi--neutral"><strong class="brebo-kpi__value">${dwellings}</strong><span class="brebo-kpi__label">${Drupal.t('Woningen')}</span></div>
          <div class="brebo-kpi brebo-kpi--neutral"><strong class="brebo-kpi__value">${positions}</strong><span class="brebo-kpi__label">${Drupal.t('Productposities')}</span></div>
          <div class="brebo-kpi brebo-kpi--neutral"><strong class="brebo-kpi__value">${projects}</strong><span class="brebo-kpi__label">${Drupal.t('Projecten historie')}</span></div>`;

        if (identity) {
          identity.classList.add('brebo-building-identity');
          identity.insertAdjacentElement('afterend', kpis);
        }
        else {
          map.insertAdjacentElement('beforebegin', kpis);
        }

        const tabs = root.querySelector('.brebo-project-tabs');
        if (tabs) tabs.classList.add('brebo-building-tabs');

        Array.from(root.querySelectorAll(':scope > h2')).forEach((heading) => heading.classList.add('brebo-section-heading'));
        Array.from(root.querySelectorAll(':scope > table')).forEach((table) => {
          if (table === identity || table.hidden) return;
          table.classList.add('brebo-building-table');
        });
      });
    }
  };
})(Drupal, once);
