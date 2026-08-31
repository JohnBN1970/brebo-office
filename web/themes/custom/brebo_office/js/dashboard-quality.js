(function (Drupal, once) {
  'use strict';

  function pill(label, value, extraClass) {
    const className = ['brebo-dashboard-signal-pill', extraClass || ''].filter(Boolean).join(' ');
    return '<span class="' + className + '"><span>' + label + '</span><b>' + Number(value || 0) + '</b></span>';
  }

  Drupal.behaviors.breboDashboardQuality = {
    attach(context) {
      once('brebo-dashboard-quality', '.brebo-dashboard', context).forEach((dashboard) => {
        fetch(Drupal.url('brebo-office/dashboard/quality-summary'), {
          credentials: 'same-origin',
          headers: {Accept: 'application/json'},
        })
          .then((response) => {
            if (!response.ok) throw new Error('Quality dashboard summary unavailable.');
            return response.json();
          })
          .then((summary) => {
            const panel = document.createElement('section');
            panel.className = 'brebo-dashboard-panel';
            panel.setAttribute('aria-label', 'Kwaliteit en oplevering');
            panel.dataset.breboDashboardBlock = 'quality';
            panel.innerHTML =
              '<div class="brebo-section-heading">' +
                '<div><p class="brebo-page-header__eyebrow">KWALITEIT & OPLEVERING</p><h2>Controles & afwijkingen</h2></div>' +
                '<a href="' + Drupal.url('kwaliteit') + '">Open kwaliteit</a>' +
              '</div>' +
              '<div class="brebo-dashboard-signal-grid">' +
                '<div class="brebo-dashboard-signal-group"><strong>Controles</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Totaal', summary.controls) + pill('Akkoord', summary.approved, Number(summary.approved || 0) > 0 ? 'brebo-bg--success' : '') +
                '</div></div>' +
                '<div class="brebo-dashboard-signal-group"><strong>Afwijkingen</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Afwijkend', summary.deviating, Number(summary.deviating || 0) > 0 ? 'brebo-bg--warning' : '') + pill('Open', summary.open_deviations, Number(summary.open_deviations || 0) > 0 ? 'brebo-bg--danger' : '') +
                '</div></div>' +
                '<div class="brebo-dashboard-signal-group"><strong>Vrijgave</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Geblokkeerd', summary.blocked_release, Number(summary.blocked_release || 0) > 0 ? 'brebo-bg--danger' : '') +
                '</div></div>' +
                '<div class="brebo-dashboard-signal-group"><strong>Termijn</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Te laat', summary.overdue, Number(summary.overdue || 0) > 0 ? 'brebo-bg--warning' : '') +
                '</div></div>' +
              '</div>';

            const managementSignals = dashboard.querySelector('[aria-label="Managementsignalen"]');
            const finance = dashboard.querySelector('[aria-label="Financieel management"]');
            const planning = dashboard.querySelector('[aria-label="Projectplanning en deadlines"]');
            if (managementSignals) managementSignals.before(panel);
            else if (finance) finance.after(panel);
            else if (planning) planning.after(panel);
            else dashboard.appendChild(panel);
          })
          .catch(() => {});
      });
    },
  };
})(Drupal, once);
