(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboWorkPackageCockpit = {
    attach(context) {
      once('brebo-work-package-cockpit', '.brebo-list-actions', context).forEach((actions) => {
        const addGate = Array.from(actions.querySelectorAll('a')).find((link) =>
          link.textContent.trim().toLowerCase().includes('vrijgavepoort toevoegen')
        );
        if (!addGate) return;

        const root = actions.parentElement;
        if (!root) return;
        root.classList.add('brebo-work-package-cockpit');

        const tables = Array.from(root.querySelectorAll(':scope > table'));
        const headings = Array.from(root.querySelectorAll(':scope > h2'));
        const findTable = (needle) => tables.find((table) =>
          Array.from(table.querySelectorAll('thead th')).some((th) => th.textContent.trim().includes(needle))
        );
        const packageTable = findTable('Verantwoordelijke');
        const operatingTable = findTable('Effectieve waarde');
        const summaryTable = findTable('Vrijgave mogelijk');
        const gatesTable = findTable('Onderbouwing');
        const positionsTable = findTable('Producttype');

        if (packageTable) packageTable.classList.add('brebo-work-package-summary');
        if (operatingTable) operatingTable.classList.add('brebo-work-package-table');
        if (gatesTable) gatesTable.classList.add('brebo-work-package-table');
        if (positionsTable) positionsTable.classList.add('brebo-work-package-table');

        if (summaryTable) {
          summaryTable.hidden = true;
          const cells = Array.from(summaryTable.querySelectorAll('tbody tr:first-child td'));
          const values = cells.map((cell) => cell.textContent.trim());
          const [positions = '0', gates = '0', approved = '0', blocked = '0', release = 'Nee'] = values;
          const ready = release.toLowerCase() === 'ja';
          const decision = document.createElement('section');
          decision.className = `brebo-work-package-decision brebo-work-package-decision--${ready ? 'positive' : blocked !== '0' ? 'critical' : 'neutral'}`;
          decision.setAttribute('aria-label', Drupal.t('Startbesluit werkpakket'));
          decision.innerHTML = `
            <div class="brebo-work-package-decision__state">
              <span class="brebo-work-package-decision__label">${Drupal.t('Vrijgave')}</span>
              <strong class="brebo-work-package-decision__value">${ready ? Drupal.t('STARTGEREED') : Drupal.t('NIET STARTGEREED')}</strong>
              <span class="brebo-work-package-decision__reason">${ready ? Drupal.t('Geen blokkerende vrijgavepoorten.') : blocked !== '0' ? Drupal.t('@count blokkerende vrijgavepoort(en).', {'@count': blocked}) : Drupal.t('Vrijgave is nog niet compleet.')}</span>
            </div>
            <div class="brebo-work-package-decision__facts">
              <div class="brebo-work-package-decision__fact"><strong>${blocked}</strong><span>${Drupal.t('Blokkerend')}</span></div>
              <div class="brebo-work-package-decision__fact"><strong>${approved}/${gates}</strong><span>${Drupal.t('Poorten akkoord')}</span></div>
              <div class="brebo-work-package-decision__fact"><strong>${positions}</strong><span>${Drupal.t('Productposities')}</span></div>
              <div class="brebo-work-package-decision__fact"><strong>${release}</strong><span>${Drupal.t('Vrijgave mogelijk')}</span></div>
            </div>`;
          actions.insertAdjacentElement('afterend', decision);
        }

        headings.forEach((heading) => heading.classList.add('brebo-section-heading'));
      });
    }
  };
})(Drupal, once);
