(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.breboFinanceNotificationBell = {
    attach: function (context) {
      once('brebo-finance-notification-bell', 'body', context).forEach(function () {
        var settings = drupalSettings.breboFinanceNotifications || {};
        if (!settings.notificationsUrl || !settings.inboxUrl) return;

        var root = document.createElement('div');
        root.className = 'brebo-finance-bell';
        root.innerHTML = '<button class="brebo-finance-bell__button" type="button" aria-label="Financiële beslissingen" aria-expanded="false">' +
          '<span class="brebo-finance-bell__icon" aria-hidden="true">&#128276;</span><span class="brebo-finance-bell__risk" hidden></span>' +
          '<span class="brebo-finance-bell__badge" hidden>0</span></button>' +
          '<div class="brebo-finance-bell__panel" hidden><div class="brebo-finance-bell__head"><strong>Financiële beslissingen</strong><a href="' + settings.inboxUrl + '">Open inbox</a></div>' +
          '<div class="brebo-finance-bell__summary"></div><div class="brebo-finance-bell__items">Laden…</div></div>';
        document.body.appendChild(root);

        var button = root.querySelector('.brebo-finance-bell__button');
        var badge = root.querySelector('.brebo-finance-bell__badge');
        var risk = root.querySelector('.brebo-finance-bell__risk');
        var panel = root.querySelector('.brebo-finance-bell__panel');
        var summary = root.querySelector('.brebo-finance-bell__summary');
        var items = root.querySelector('.brebo-finance-bell__items');

        function esc(value) { var node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; }
        function money(value) { var amount = Number(value); return Number.isFinite(amount) ? new Intl.NumberFormat('nl-NL', { style:'currency', currency:'EUR', maximumFractionDigits:0 }).format(amount) : 'Exposure onbekend'; }
        function gateLabel(gate) { return ({procurement_release:'Inkoop',execution_start:'Uitvoering',billing_release:'Facturatie',payment_release:'Betaling',project_closeout:'Afsluiting'}[gate] || gate); }

        function render(data) {
          var unread = Number(data.unread_count || 0);
          var s = data.decision_summary || {};
          var now = Number(s.now || 0), today = Number(s.today || 0), week = Number(s.this_week || 0), decisionCount = Number(s.count || 0);
          badge.textContent = unread > 99 ? '99+' : String(unread); badge.hidden = unread === 0;
          risk.textContent = now > 0 ? now + ' NU · ' + money(s.total_exposure) : (today > 0 ? today + ' VANDAAG · ' + money(s.total_exposure) : '');
          risk.hidden = decisionCount === 0;
          root.classList.toggle('brebo-finance-bell--attention', now > 0 || unread > 0);
          root.classList.toggle('brebo-finance-bell--urgent', now > 0);
          summary.innerHTML = decisionCount ? '<strong>' + now + ' NU</strong><span>' + today + ' vandaag</span><span>' + week + ' deze week</span><strong>' + esc(money(s.total_exposure)) + ' exposure</strong>' : '<span>Geen financiële besluiten voor jou.</span>';

          var rows = Array.isArray(s.top_decisions) ? s.top_decisions : [];
          if (!rows.length) { items.innerHTML = '<div class="brebo-finance-bell__empty">Geen open financiële beslissingen.</div>'; return; }
          items.innerHTML = rows.map(function (item) {
            var p = item.priority || {};
            return '<a class="brebo-finance-bell__item" href="' + esc(settings.inboxUrl + '?exception_id=' + item.exception_id) + '">' +
              '<span><strong>' + esc(p.label || gateLabel(item.gate)) + ' · ' + esc(gateLabel(item.gate)) + '</strong>' +
              '<small>Project ' + esc(item.project_nid) + ' · ' + esc(money(item.exposure_amount)) + '</small></span><span class="brebo-finance-bell__arrow">→</span></a>';
          }).join('');
        }

        function refresh() {
          fetch(settings.notificationsUrl, {credentials:'same-origin', headers:{Accept:'application/json'}})
            .then(function (response) { if (!response.ok) throw new Error('notifications'); return response.json(); }).then(render)
            .catch(function () { items.innerHTML = '<div class="brebo-finance-bell__empty">Meldingen konden niet worden geladen.</div>'; });
        }

        button.addEventListener('click', function () { var open = panel.hidden; panel.hidden = !open; button.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) refresh(); });
        document.addEventListener('click', function (event) { if (!root.contains(event.target)) { panel.hidden = true; button.setAttribute('aria-expanded', 'false'); } });
        refresh(); window.setInterval(refresh, 60000);
      });
    }
  };
})(Drupal, once, drupalSettings);
