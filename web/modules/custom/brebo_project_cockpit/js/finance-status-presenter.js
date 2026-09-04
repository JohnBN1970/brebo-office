(function (Drupal, once) {
  'use strict';

  const labels = {
    planned: 'Gepland',
    billable: 'Factureerbaar',
    approved: 'Goedgekeurd',
    client_approved: 'Door klant goedgekeurd',
    draft: 'Concept',
    released: 'Vrijgegeven',
    queued: 'In wachtrij',
    processing: 'Wordt verwerkt',
    completed: 'Verwerkt',
    sent: 'Verzonden',
    received: 'Ontvangen',
    invoiced: 'Gefactureerd',
    paid: 'Betaald',
    overdue: 'Vervallen',
    disputed: 'In geschil',
    credited: 'Gecrediteerd',
    cancelled: 'Geannuleerd',
    canceled: 'Geannuleerd',
    executed: 'Uitgevoerd',
    rejected: 'Afgewezen',
    verified: 'Geverifieerd',
    matched: 'Gematcht',
    unmatched: 'Niet gematcht',
    exception: 'Afwijking',
    closed: 'Afgesloten',
    pending: 'In behandeling',
    open: 'Open',
  };

  const tones = {
    paid: 'green', completed: 'green', approved: 'green', client_approved: 'green', verified: 'green', closed: 'green', invoiced: 'green', received: 'green', matched: 'green', executed: 'green',
    planned: 'neutral', draft: 'neutral', released: 'orange', queued: 'orange', processing: 'orange', pending: 'orange', billable: 'orange', sent: 'orange', open: 'orange', unmatched: 'orange',
    overdue: 'red', disputed: 'red', rejected: 'red', exception: 'red',
    credited: 'neutral', cancelled: 'neutral', canceled: 'neutral',
  };

  const statusColumnIndexes = (table) => Array.from(table.querySelectorAll('thead th')).reduce((indexes, th, index) => {
    const heading = th.textContent.trim().toLowerCase();
    if (heading.includes('status') || heading === 'integratie' || heading.includes('match')) indexes.push(index);
    return indexes;
  }, []);

  Drupal.behaviors.breboFinanceStatusPresenter = {
    attach(context) {
      once('brebo-finance-status-table', 'table', context).forEach((table) => {
        const indexes = statusColumnIndexes(table);
        if (!indexes.length) return;
        table.querySelectorAll('tbody tr').forEach((row) => {
          const cells = row.querySelectorAll('td');
          indexes.forEach((index) => {
            const cell = cells[index];
            if (!cell) return;
            const raw = cell.textContent.trim();
            if (!Object.prototype.hasOwnProperty.call(labels, raw)) return;
            const tone = tones[raw] || 'neutral';
            cell.textContent = '';
            const badge = document.createElement('span');
            badge.className = `brebo-finance-status brebo-finance-status--${tone}`;
            badge.setAttribute('data-finance-status', raw);
            badge.innerHTML = '<span class="brebo-finance-status__light" aria-hidden="true"></span><span class="brebo-finance-status__label"></span>';
            badge.querySelector('.brebo-finance-status__label').textContent = labels[raw];
            cell.appendChild(badge);
            row.classList.add(`brebo-finance-row--${tone}`);
          });
        });
      });
    },
  };
})(Drupal, once);
