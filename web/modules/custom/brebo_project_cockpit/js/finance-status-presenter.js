(function (Drupal, once) {
  'use strict';

  const labels = {
    planned: 'Gepland',
    billable: 'Factureerbaar',
    approved: 'Goedgekeurd',
    client_approved: 'Door klant goedgekeurd',
    draft: 'Concept',
    queued: 'In wachtrij',
    processing: 'Wordt verwerkt',
    completed: 'Verwerkt',
    sent: 'Verzonden',
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
    closed: 'Afgesloten',
    pending: 'In behandeling',
    open: 'Open',
  };

  const tones = {
    paid: 'green', completed: 'green', approved: 'green', client_approved: 'green', verified: 'green', closed: 'green', invoiced: 'green',
    planned: 'neutral', draft: 'neutral', queued: 'orange', processing: 'orange', pending: 'orange', billable: 'orange', sent: 'orange', open: 'orange',
    overdue: 'red', disputed: 'red', rejected: 'red',
    credited: 'neutral', cancelled: 'neutral', canceled: 'neutral', executed: 'green',
  };

  Drupal.behaviors.breboFinanceStatusPresenter = {
    attach(context) {
      once('brebo-finance-status', 'table td', context).forEach((cell) => {
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
        const row = cell.closest('tr');
        if (row) row.classList.add(`brebo-finance-row--${tone}`);
      });
    },
  };
})(Drupal, once);
