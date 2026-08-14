(() => {
  'use strict';

  function communicationId() {
    const parts = window.location.pathname.split('/').filter(Boolean);
    if (parts[0] !== 'mail' || parts.length < 4) return 0;
    const id = Number(parts[3]);
    return Number.isInteger(id) && id > 0 ? id : 0;
  }

  function activateContextActions() {
    const id = communicationId();
    if (!id) return;

    const destination = window.location.pathname + window.location.search;
    const href = '/communicatie/' + id + '/koppelen?destination=' + encodeURIComponent(destination);
    document.querySelectorAll('.brebo-mail-office-actions .brebo-mail-action--disabled').forEach((element) => {
      const label = element.querySelector('.brebo-mail-action__label')?.textContent?.trim() || '';
      if (label !== 'Koppelen in Office') return;

      const link = document.createElement('a');
      link.className = element.className.replace(/\s*brebo-mail-action--disabled\b/g, '');
      link.href = href;
      link.innerHTML = element.innerHTML;
      element.replaceWith(link);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(activateContextActions, 0), {once: true});
  }
  else {
    window.setTimeout(activateContextActions, 0);
  }
})();
