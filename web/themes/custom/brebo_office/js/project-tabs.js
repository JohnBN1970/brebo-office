(function (Drupal) {
  'use strict';

  Drupal.behaviors.breboProjectTabs = {
    attach: function (context) {
      context.querySelectorAll('[data-brebo-tabs]:not([data-brebo-tabs-ready])').forEach(function (tablist) {
        tablist.setAttribute('data-brebo-tabs-ready', 'true');
        var root = tablist.closest('.brebo-content') || document;
        var buttons = Array.from(tablist.querySelectorAll('[data-brebo-tab]'));
        var panels = Array.from(root.querySelectorAll('[data-brebo-tab-panel]'));

        function activate(tabId, moveFocus) {
          buttons.forEach(function (button) {
            var active = button.getAttribute('data-brebo-tab') === tabId;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.setAttribute('tabindex', active ? '0' : '-1');
            if (active && moveFocus) {
              button.focus();
            }
          });
          panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-brebo-tab-panel') !== tabId;
          });
          if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#tab-' + tabId);
          }
        }

        buttons.forEach(function (button, index) {
          button.addEventListener('click', function () {
            activate(button.getAttribute('data-brebo-tab'), false);
          });
          button.addEventListener('keydown', function (event) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
              return;
            }
            event.preventDefault();
            var targetIndex = index;
            if (event.key === 'ArrowLeft') targetIndex = (index - 1 + buttons.length) % buttons.length;
            if (event.key === 'ArrowRight') targetIndex = (index + 1) % buttons.length;
            if (event.key === 'Home') targetIndex = 0;
            if (event.key === 'End') targetIndex = buttons.length - 1;
            activate(buttons[targetIndex].getAttribute('data-brebo-tab'), true);
          });
        });

        var requested = window.location.hash.replace('#tab-', '');
        var initial = buttons.some(function (button) {
          return button.getAttribute('data-brebo-tab') === requested;
        }) ? requested : 'overview';
        activate(initial, false);
      });
    }
  };
})(Drupal);
