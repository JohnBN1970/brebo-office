(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.breboControlRiskDashboard = {
    attach: function (context) {
      if (context !== document || document.querySelector('[data-brebo-control-risk]')) {
        return;
      }

      var data = drupalSettings.breboControlRisk;
      if (!data || !data.visible) {
        return;
      }

      var dashboard = document.querySelector('.brebo-dashboard');
      if (!dashboard) {
        return;
      }

      var panel = document.createElement('section');
      panel.className = 'brebo-dashboard-panel';
      panel.setAttribute('aria-label', 'Controle en risico');
      panel.setAttribute('data-brebo-control-risk', '');

      var heading = document.createElement('div');
      heading.className = 'brebo-section-heading';
      var headingMain = document.createElement('div');
      var eyebrow = document.createElement('p');
      eyebrow.className = 'brebo-page-header__eyebrow';
      eyebrow.textContent = 'CONTROLE & RISICO';
      var title = document.createElement('h2');
      title.textContent = data.status || 'Portefeuillebewaking';
      headingMain.appendChild(eyebrow);
      headingMain.appendChild(title);
      var note = document.createElement('span');
      note.className = 'brebo-dashboard-signal-note';
      note.textContent = 'Risicoscore ' + Number(data.score || 0) + '/100 · ' + String(data.level || 'laag');
      heading.appendChild(headingMain);
      heading.appendChild(note);
      panel.appendChild(heading);

      var grid = document.createElement('div');
      grid.className = 'brebo-dashboard-signal-grid';
      [
        ['Portefeuille', 'Actieve projecten', data.project_count || 0, ''],
        ['Risicostatus', 'Hoog / kritiek', data.critical_or_high || 0, Number(data.critical_or_high || 0) > 0 ? ' brebo-bg--danger' : ' brebo-bg--success'],
        ['Trend', 'Verslechterend', data.deteriorating || 0, Number(data.deteriorating || 0) > 0 ? ' brebo-bg--warning' : ' brebo-bg--success'],
        ['Patronen', 'Actieve risicopatronen', data.pattern_count || 0, Number(data.pattern_count || 0) > 0 ? ' brebo-bg--warning' : '']
      ].forEach(function (item) {
        var group = document.createElement('div');
        group.className = 'brebo-dashboard-signal-group';
        var groupTitle = document.createElement('strong');
        groupTitle.textContent = item[0];
        var pills = document.createElement('div');
        pills.className = 'brebo-dashboard-signal-pills';
        var pill = document.createElement('span');
        pill.className = 'brebo-dashboard-signal-pill' + item[3];
        var label = document.createElement('span');
        label.textContent = item[1];
        var value = document.createElement('b');
        value.textContent = String(item[2]);
        pill.appendChild(label);
        pill.appendChild(value);
        pills.appendChild(pill);
        group.appendChild(groupTitle);
        group.appendChild(pills);
        grid.appendChild(group);
      });
      panel.appendChild(grid);

      var finance = dashboard.querySelector('[aria-label="Financieel management"]');
      var management = dashboard.querySelector('[aria-label="Managementsignalen"]');
      if (finance && finance.nextSibling) {
        finance.parentNode.insertBefore(panel, finance.nextSibling);
      }
      else if (management) {
        management.parentNode.insertBefore(panel, management);
      }
      else {
        dashboard.appendChild(panel);
      }
    }
  };
})(Drupal, drupalSettings);
