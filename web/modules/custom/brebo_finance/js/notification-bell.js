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
          '<span class="brebo-finance-bell__icon" aria-hidden="true">&#128276;</span>' +
          '<span class="brebo-finance-bell__badge" hidden>0</span></button>' +
          '<div class="brebo-finance-bell__panel" hidden><div class="brebo-finance-bell__head"><strong>Financiële beslissingen</strong>' +
          '<a href="' + settings.inboxUrl + '">Open inbox</a></div><div class="brebo-finance-bell__items">Laden…</div></div>';
        document.body.appendChild(root);

        var button = root.querySelector('.brebo-finance-bell__button');
        var badge = root.querySelector('.brebo-finance-bell__badge');
        var panel = root.querySelector('.brebo-finance-bell__panel');
        var items = root.querySelector('.brebo-finance-bell__items');

        function esc(value) {
          var node = document.createElement('div');
          node.textContent = value == null ? '' : String(value);
          return node.innerHTML;
        }

        function money(value) {
          var amount = Number(value);
          return Number.isFinite(amount) ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount) : 'Exposure onbekend';
        }

        function render(data) {
          var count = Number(data.unread_count || 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.hidden = count === 0;
          root.classList.toggle('brebo-finance-bell--attention', count > 0);
          var rows = Array.isArray(data.items) ? data.items.slice(0, 5) : [];
          if (!rows.length) {
            items.innerHTML = '<div class="brebo-finance-bell__empty">Geen open financiële beslissingen.</div>';
            return;
          }
          items.innerHTML = rows.map(function (item) {
            var payload = item.payload || {};
            var exposure = payload.exposure && (payload.exposure.amount != null ? payload.exposure.amount : payload.exposure);
            return '<a class="brebo-finance-bell__item" href="' + esc(settings.inboxUrl + '?exception_id=' + item.exception_id) + '">' +
              '<span><strong>' + esc(payload.gate || item.attention || 'Financieel besluit') + '</strong>' +
              '<small>Project ' + esc(item.project_nid) + ' · ' + esc(money(exposure)) + '</small></span>' +
              '<span class="brebo-finance-bell__arrow">→</span></a>';
          }).join('');
        }

        function refresh() {
          fetch(settings.notificationsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) { if (!response.ok) throw new Error('notifications'); return response.json(); })
            .then(render)
            .catch(function () { items.innerHTML = '<div class="brebo-finance-bell__empty">Meldingen konden niet worden geladen.</div>'; });
        }

        button.addEventListener('click', function () {
          var open = panel.hidden;
          panel.hidden = !open;
          button.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) refresh();
        });
        document.addEventListener('click', function (event) {
          if (!root.contains(event.target)) {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
          }
        });
        refresh();
        window.setInterval(refresh, 60000);
      });
    }
  };
})(Drupal, once, drupalSettings);
