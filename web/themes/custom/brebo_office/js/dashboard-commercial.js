(function (Drupal, once) {
  'use strict';

  function money(value) {
    return new Intl.NumberFormat('nl-NL', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(Number(value || 0));
  }

  function pill(label, value, extraClass) {
    const className = ['brebo-dashboard-signal-pill', extraClass || ''].filter(Boolean).join(' ');
    return '<span class="' + className + '"><span>' + label + '</span><b>' + value + '</b></span>';
  }

  Drupal.behaviors.breboDashboardCommercial = {
    attach(context) {
      once('brebo-dashboard-commercial', '.brebo-dashboard', context).forEach((dashboard) => {
        fetch(Drupal.url('brebo-office/dashboard/commercial-summary'), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error('Commercial dashboard summary unavailable.');
            }
            return response.json();
          })
          .then((summary) => {
            const panel = document.createElement('section');
            panel.className = 'brebo-dashboard-panel';
            panel.setAttribute('aria-label', 'Commercie en funnel');
            panel.innerHTML =
              '<div class="brebo-section-heading">' +
                '<div><p class="brebo-page-header__eyebrow">COMMERCIE & FUNNEL</p><h2>Pipeline & opvolging</h2></div>' +
                '<a href="' + Drupal.url('relaties/funnel') + '">Open funnel</a>' +
              '</div>' +
              '<div class="brebo-dashboard-signal-grid">' +
                '<div class="brebo-dashboard-signal-group"><strong>Pipeline</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Actieve kansen', Number(summary.active || 0)) +
                  pill('Verwachte omzet', money(summary.pipeline_value)) +
                '</div></div>' +
                '<div class="brebo-dashboard-signal-group"><strong>Gewogen waarde</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Op scoringskans', money(summary.weighted_value)) +
                '</div></div>' +
                '<div class="brebo-dashboard-signal-group"><strong>Opvolging</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Termijn verlopen', Number(summary.overdue_follow_up || 0), Number(summary.overdue_follow_up || 0) > 0 ? 'brebo-bg--danger' : '') +
                  pill('Sluiting ≤ 30 dagen', Number(summary.closing_30d || 0)) +
                '</div></div>' +
                '<div class="brebo-dashboard-signal-group"><strong>Beslisfase</strong><div class="brebo-dashboard-signal-pills">' +
                  pill('Onderhandeling', Number(summary.negotiation || 0), Number(summary.negotiation || 0) > 0 ? 'brebo-bg--warning' : '') +
                '</div></div>' +
              '</div>';

            const regie = dashboard.querySelector('[aria-label="Regie en aandacht"]');
            if (regie) {
              regie.before(panel);
            }
            else {
              const kpis = dashboard.querySelector('.brebo-dashboard-kpis');
              if (kpis) {
                kpis.after(panel);
              }
            }
          })
          .catch(() => {
            // Dashboard remains usable when the optional commercial summary fails.
          });
      });
    },
  };
})(Drupal, once);
