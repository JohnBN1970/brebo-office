(function (Drupal, once, drupalSettings) {
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

  Drupal.behaviors.breboProjectCockpitRichCards = {
    attach(context) {
      const data = drupalSettings.breboProjectCockpit && drupalSettings.breboProjectCockpit.richCards;
      if (!data) {
        return;
      }

      once('brebo-project-cockpit-rich-card', '.brebo-project-cockpit__status', context).forEach((card) => {
        const labelElement = card.querySelector('span');
        const statusElement = card.querySelector('strong');
        if (!labelElement) {
          return;
        }

        const label = labelElement.textContent.trim();
        const cardData = data[label];
        if (!cardData) {
          return;
        }

        card.classList.add('brebo-project-cockpit__status--rich');
        labelElement.classList.add('brebo-project-cockpit__card-label');
        if (statusElement) {
          statusElement.classList.add('brebo-project-cockpit__card-status');
        }

        if (cardData.headline) {
          const headline = document.createElement('div');
          headline.className = 'brebo-project-cockpit__card-headline';
          headline.textContent = cardData.headline;
          card.appendChild(headline);
        }

        const lines = Array.isArray(cardData.lines) ? cardData.lines.filter(Boolean) : [];
        if (lines.length) {
          const details = document.createElement('div');
          details.className = 'brebo-project-cockpit__card-details';
          lines.forEach((text) => {
            const line = document.createElement('div');
            line.className = 'brebo-project-cockpit__card-line';
            line.textContent = text;
            details.appendChild(line);
          });
          card.appendChild(details);
        }
      });
    }
  };
})(Drupal, once, drupalSettings);
