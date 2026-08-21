(function (Drupal, once) {
  Drupal.behaviors.breboOfficeSidebar = {
    attach(context) {
      once('brebo-office-sidebar', '[data-brebo-office-nav]', context).forEach(function (nav) {
        var layout = nav.closest('.brebo-app-layout');
        var toggle = nav.querySelector('[data-brebo-office-nav-toggle]');
        if (!layout || !toggle) return;
        var collapsed = window.localStorage.getItem('breboOfficeNavCollapsed') === '1';
        layout.classList.toggle('is-nav-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.addEventListener('click', function () {
          collapsed = !layout.classList.contains('is-nav-collapsed');
          layout.classList.toggle('is-nav-collapsed', collapsed);
          toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
          toggle.setAttribute('title', collapsed ? 'Menu uitklappen' : 'Menu inklappen');
          window.localStorage.setItem('breboOfficeNavCollapsed', collapsed ? '1' : '0');
        });
      });
    }
  };
})(Drupal, once);
