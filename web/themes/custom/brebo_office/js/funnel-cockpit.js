(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboFunnelCockpit = {
    attach(context) {
      once('brebo-funnel-cockpit', '.brebo-kanban', context).forEach((kanban) => {
        const root = kanban.closest('main, .region-content, .layout-content, .page-content') || kanban.parentElement;
        if (!root) return;
        root.classList.add('brebo-funnel-cockpit');

        // Keep list and kanban as equal views; the current query state remains
        // authoritative and this behavior changes presentation only.
        const actions = root.querySelectorAll('.brebo-list-actions');
        actions.forEach((element) => element.classList.add('brebo-actions'));

        const summaries = root.querySelectorAll('.brebo-calc-summary');
        summaries.forEach((table, index) => {
          if (index === 0) table.classList.add('brebo-funnel-summary');
        });

        kanban.querySelectorAll('.brebo-kanban-column').forEach((column) => {
          const heading = column.querySelector('h3');
          if (heading) heading.classList.add('brebo-kanban-column__title');
        });
        kanban.querySelectorAll('.brebo-kanban-card').forEach((card) => {
          card.setAttribute('role', 'article');
        });

        root.querySelectorAll('details').forEach((details) => {
          details.classList.add('brebo-funnel-details');
        });
      });
    }
  };
})(Drupal, once);
