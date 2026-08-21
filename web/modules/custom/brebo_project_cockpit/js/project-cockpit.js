(function (Drupal, once, drupalSettings) {
  'use strict';

  function buildLayout() {
    const menu = document.querySelector('.brebo-list-actions');
    const hero = document.querySelector('.brebo-project-cockpit__hero');
    if (!menu || !hero || !hero.parentNode) {
      return;
    }
    if (document.querySelector('.brebo-project-cockpit__layout')) {
      return;
    }

    const parent = hero.parentNode;
    const layout = document.createElement('div');
    layout.className = 'brebo-project-cockpit__layout';
    const canvas = document.createElement('div');
    canvas.className = 'brebo-project-cockpit__canvas';

    parent.insertBefore(layout, hero);
    layout.appendChild(menu);
    layout.appendChild(canvas);
    menu.setAttribute('aria-label', Drupal.t('Project navigation'));

    let current = hero;
    while (current) {
      const next = current.nextSibling;
      if (current !== menu) {
        canvas.appendChild(current);
      }
      current = next;
    }
  }

  function enrichRichCards() {
    const data = drupalSettings.breboProjectCockpit && drupalSettings.breboProjectCockpit.richCards;
    if (!data) {
      return false;
    }

    document.querySelectorAll('.brebo-project-cockpit__status').forEach((card) => {
      if (card.dataset.breboRichCard === '1') {
        return;
      }
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

      card.dataset.breboRichCard = '1';
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

    return true;
  }

  function initializeCockpit() {
    buildLayout();
    if (!enrichRichCards()) {
      window.setTimeout(enrichRichCards, 50);
    }
  }

  Drupal.behaviors.breboProjectCockpit = {
    attach() {
      once('brebo-project-cockpit-init', 'body').forEach(() => initializeCockpit());
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCockpit, { once: true });
  }
  else {
    window.setTimeout(initializeCockpit, 0);
  }
})(Drupal, once, drupalSettings);
