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
        toggle.setAttribute('title', collapsed ? 'Menu uitklappen' : 'Menu inklappen');

        toggle.addEventListener('click', function () {
          collapsed = !layout.classList.contains('is-nav-collapsed');
          layout.classList.toggle('is-nav-collapsed', collapsed);
          toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
          toggle.setAttribute('title', collapsed ? 'Menu uitklappen' : 'Menu inklappen');
          window.localStorage.setItem('breboOfficeNavCollapsed', collapsed ? '1' : '0');
        });

        var groups = Array.from(nav.querySelectorAll('[data-brebo-nav-group]'));
        function closeOtherGroups(current) {
          groups.forEach(function (candidate) {
            if (candidate === current || !candidate.classList.contains('is-open')) return;
            candidate.classList.remove('is-open');
            var candidateToggle = candidate.querySelector('[data-brebo-nav-group-toggle]');
            if (candidateToggle) candidateToggle.setAttribute('aria-expanded', 'false');
            var candidateName = candidate.getAttribute('data-brebo-nav-group');
            if (candidateName) window.localStorage.setItem('breboOfficeNavGroup:' + candidateName, '0');
          });
        }

        groups.forEach(function (group) {
          var groupName = group.getAttribute('data-brebo-nav-group');
          var groupToggle = group.querySelector('[data-brebo-nav-group-toggle]');
          if (!groupToggle || !groupName) return;

          var stored = window.localStorage.getItem('breboOfficeNavGroup:' + groupName);
          var expanded = stored === null ? group.classList.contains('is-active') : stored === '1';
          if (expanded) closeOtherGroups(group);
          group.classList.toggle('is-open', expanded);
          groupToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

          groupToggle.addEventListener('click', function () {
            var open = !group.classList.contains('is-open');
            if (open) closeOtherGroups(group);
            group.classList.toggle('is-open', open);
            groupToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            window.localStorage.setItem('breboOfficeNavGroup:' + groupName, open ? '1' : '0');
          });
        });

        document.addEventListener('click', function (event) {
          if (!layout.classList.contains('is-nav-collapsed') || nav.contains(event.target)) return;
          groups.forEach(function (group) {
            if (!group.classList.contains('is-open')) return;
            group.classList.remove('is-open');
            var groupToggle = group.querySelector('[data-brebo-nav-group-toggle]');
            if (groupToggle) groupToggle.setAttribute('aria-expanded', 'false');
          });
        });
      });
    }
  };
})(Drupal, once);
