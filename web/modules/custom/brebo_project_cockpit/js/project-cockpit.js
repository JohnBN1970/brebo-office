(function (Drupal, once, drupalSettings) {
  'use strict';

  function removeLegacyProjectMenu() {
    document.querySelectorAll('.brebo-list-actions').forEach((menu) => menu.remove());
  }

  function projectIdFromPath() {
    const projectMatch = window.location.pathname.match(/^\/projecten\/(\d+)(?:\/|$)/);
    if (projectMatch) {
      return projectMatch[1];
    }
    const legacyDocumentMatch = window.location.pathname.match(/^\/node\/(\d+)\/documents(?:\/|$)/);
    return legacyDocumentMatch ? legacyDocumentMatch[1] : null;
  }

  function activateTab(link) {
    document.querySelectorAll('.brebo-context-tabs a.is-active').forEach((activeLink) => {
      activeLink.classList.remove('is-active');
      activeLink.removeAttribute('aria-current');
    });
    link.classList.add('is-active');
    link.setAttribute('aria-current', 'page');
  }

  function wireProjectTabs() {
    const projectId = projectIdFromPath();
    if (!projectId) {
      return;
    }

    document.querySelectorAll('.brebo-context-tabs a').forEach((link) => {
      const label = link.textContent.trim();
      if (label === 'Documenten') {
        link.href = `/projecten/${projectId}/documenten`;
        const active = window.location.pathname === `/projecten/${projectId}/documenten` ||
          window.location.pathname.startsWith(`/projecten/${projectId}/documenten/`) ||
          window.location.pathname === `/node/${projectId}/documents` ||
          window.location.pathname.startsWith(`/node/${projectId}/documents/`);
        if (active) {
          activateTab(link);
        }
        return;
      }

      if (label === 'Calculatie' || label === 'Begroting') {
        link.textContent = 'Begroting';
        link.href = `/projecten/${projectId}/begroting`;
        const active = window.location.pathname === `/projecten/${projectId}/begroting` ||
          window.location.pathname.startsWith(`/projecten/${projectId}/begroting/`);
        if (active) {
          activateTab(link);
        }
        return;
      }

      if (label === 'Inkoop') {
        link.href = `/projecten/${projectId}/inkoop`;
        const active = window.location.pathname === `/projecten/${projectId}/inkoop` ||
          window.location.pathname.startsWith(`/projecten/${projectId}/inkoop/`);
        if (active) {
          activateTab(link);
        }
        return;
      }

      if (label === 'Contracten') {
        link.href = `/projecten/${projectId}/contracten`;
        const active = window.location.pathname === `/projecten/${projectId}/contracten` ||
          window.location.pathname.startsWith(`/projecten/${projectId}/contracten/`);
        if (active) {
          activateTab(link);
        }
      }
    });
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
    removeLegacyProjectMenu();
    wireProjectTabs();
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
