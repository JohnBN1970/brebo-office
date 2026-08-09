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
  Drupal.behaviors.breboCommunicationSubtabs = {
    attach: function (context) {
      context.querySelectorAll('.brebo-communication-subtabs:not([data-brebo-comm-tabs-ready])').forEach(function (tablist) {
        tablist.setAttribute('data-brebo-comm-tabs-ready', 'true');
        var root = tablist.closest('.brebo-content') || document;
        var links = Array.from(tablist.querySelectorAll('a'));
        var panels = Array.from(root.querySelectorAll('[data-brebo-comm-view]'));
        var allowed = links.map(function (link) {
          return new URL(link.href, window.location.origin).searchParams.get('comm_view');
        });

        function activate(view, updateUrl) {
          if (!allowed.includes(view)) {
            view = 'overview';
          }
          links.forEach(function (link) {
            var linkView = new URL(link.href, window.location.origin).searchParams.get('comm_view');
            var active = linkView === view;
            link.classList.toggle('is-active', active);
            if (active) {
              link.setAttribute('aria-current', 'page');
            }
            else {
              link.removeAttribute('aria-current');
            }
          });
          panels.forEach(function (panel) {
            var views = (panel.getAttribute('data-brebo-comm-view') || '').split(/\s+/);
            panel.hidden = !views.includes(view);
          });
          if (updateUrl && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('comm_view', view);
            url.hash = 'tab-communication';
            window.history.replaceState(null, '', url);
          }
        }

        links.forEach(function (link) {
          link.addEventListener('click', function (event) {
            event.preventDefault();
            activate(new URL(link.href, window.location.origin).searchParams.get('comm_view'), true);
          });
        });

        activate(new URL(window.location.href).searchParams.get('comm_view') || 'overview', false);
      });
    }
  };
})(Drupal);
