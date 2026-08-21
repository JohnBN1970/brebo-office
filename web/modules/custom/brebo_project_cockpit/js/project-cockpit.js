(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboProjectCockpitNavigation = {
    attach(context) {
      once('brebo-project-cockpit-navigation', '.brebo-list-actions', context).forEach((menu) => {
        const hero = document.querySelector('.brebo-project-cockpit__hero');
        if (!hero || !hero.parentNode) {
          return;
        }
        menu.setAttribute('aria-label', Drupal.t('Project navigation'));
        hero.parentNode.insertBefore(menu, hero);
      });
    }
  };
})(Drupal, once);
