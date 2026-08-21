(function (Drupal, once) {
  Drupal.behaviors.breboOfficeCockpit = {
    attach(context) {
      once('brebo-office-cockpit', '[data-brebo-cockpit]', context).forEach(function (cockpit) {
        var type = cockpit.getAttribute('data-brebo-cockpit') || 'default';
        var toggle = cockpit.querySelector('[data-brebo-cockpit-toggle]');
        if (!toggle) return;
        var key = 'breboOfficeCockpitCollapsed:' + type;
        var collapsed = window.localStorage.getItem(key) === '1';
        function apply() {
          cockpit.classList.toggle('is-collapsed', collapsed);
          toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
          toggle.setAttribute('title', collapsed ? 'Cockpit uitklappen' : 'Cockpit inklappen');
        }
        apply();
        toggle.addEventListener('click', function () {
          collapsed = !collapsed;
          window.localStorage.setItem(key, collapsed ? '1' : '0');
          apply();
        });
      });
    }
  };
})(Drupal, once);
