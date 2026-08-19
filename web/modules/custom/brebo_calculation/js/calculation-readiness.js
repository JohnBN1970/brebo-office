(function (Drupal, once) {
  'use strict';

  function label(status) {
    if (status === 'ready') return 'Gereed voor offerte';
    if (status === 'review') return 'Controle nodig';
    return 'Geblokkeerd';
  }

  function icon(status) {
    if (status === 'ready') return '✓';
    if (status === 'review') return '⚠';
    return '✕';
  }

  function render(workbench, data) {
    var existing = workbench.querySelector('.brebo-calc-readiness');
    if (existing) existing.remove();

    var section = document.createElement('section');
    section.className = 'brebo-calc-readiness is-' + data.status;
    section.setAttribute('data-readiness-status', data.status);

    var checks = Array.isArray(data.checks) ? data.checks : [];
    var visibleChecks = checks.slice(0, 8);
    var list = visibleChecks.map(function (check) {
      return '<li class="is-' + check.level + '"><span>' + (check.level === 'error' ? '✕' : '⚠') + '</span><span>' + Drupal.checkPlain(check.label || 'Controlepunt') + '</span></li>';
    }).join('');

    var extra = checks.length > visibleChecks.length
      ? '<li class="brebo-calc-readiness__more">+' + (checks.length - visibleChecks.length) + ' overige controlepunten</li>'
      : '';

    section.innerHTML =
      '<div class="brebo-calc-readiness__summary">' +
        '<div class="brebo-calc-readiness__status">' +
          '<span class="brebo-calc-readiness__icon">' + icon(data.status) + '</span>' +
          '<div><small>Calculatiecontrole</small><strong>' + label(data.status) + '</strong></div>' +
        '</div>' +
        '<div class="brebo-calc-readiness__counts">' +
          '<span><strong>' + Number(data.blocking || 0) + '</strong> blokkades</span>' +
          '<span><strong>' + Number(data.warnings || 0) + '</strong> waarschuwingen</span>' +
        '</div>' +
      '</div>' +
      (checks.length ? '<details class="brebo-calc-readiness__details"><summary>Controlepunten bekijken</summary><ul>' + list + extra + '</ul></details>' : '<p class="brebo-calc-readiness__clean">Geen blokkades of waarschuwingen gevonden.</p>');

    var navigation = workbench.querySelector('.brebo-calc-workbench__navigation');
    if (navigation) workbench.insertBefore(section, navigation.nextSibling);
    else workbench.insertBefore(section, workbench.firstChild);
  }

  Drupal.behaviors.breboCalculationReadiness = {
    attach: function (context) {
      once('brebo-calculation-readiness', '#brebo-calculation-workbench', context).forEach(function (workbench) {
        var match = window.location.pathname.match(/\/admin\/brebo\/calculations\/(\d+)\/workbench/);
        if (!match) return;

        fetch('/admin/brebo/calculations/' + match[1] + '/readiness', {
          headers: {'Accept': 'application/json'},
          credentials: 'same-origin'
        })
          .then(function (response) {
            if (!response.ok) throw new Error('Readiness request failed');
            return response.json();
          })
          .then(function (data) { render(workbench, data); })
          .catch(function () {
            render(workbench, {
              status: 'review',
              blocking: 0,
              warnings: 1,
              checks: [{level: 'warning', label: 'Calculatiecontrole kon niet worden geladen.'}]
            });
          });
      });
    }
  };
})(Drupal, once);
