(() => {
  'use strict';

  const STORAGE_PREFIX = 'brebo-mail-tabs-v2:';

  function parseMailPath(pathname) {
    const parts = String(pathname || '').split('/').filter(Boolean);
    if (parts[0] !== 'mail' || parts.length < 4) return null;
    const mailboxId = Number(parts[1]);
    const communicationId = Number(parts[3]);
    if (!Number.isInteger(mailboxId) || mailboxId <= 0 || !Number.isInteger(communicationId) || communicationId <= 0) return null;
    return {mailboxId, communicationId};
  }

  function storageKey(mailboxId) {
    return STORAGE_PREFIX + mailboxId;
  }

  function loadTabs(mailboxId) {
    try {
      const parsed = JSON.parse(localStorage.getItem(storageKey(mailboxId)) || '[]');
      return Array.isArray(parsed) ? parsed.filter((tab) => tab && Number(tab.id) > 0 && tab.href) : [];
    }
    catch (e) {
      return [];
    }
  }

  function saveTabs(mailboxId, tabs) {
    try {
      localStorage.setItem(storageKey(mailboxId), JSON.stringify(tabs.slice(-12)));
    }
    catch (e) {
      // A blocked storage implementation should never break Mail itself.
    }
  }

  function remember(mailboxId, tab) {
    const tabs = loadTabs(mailboxId).filter((item) => Number(item.id) !== Number(tab.id));
    tabs.push(tab);
    saveTabs(mailboxId, tabs);
  }

  function labelFromListLink(link, id) {
    const text = (link.textContent || '').replace(/^[★●⚑\s]+/, '').trim();
    const subject = text.includes(' — ') ? text.split(' — ').slice(1).join(' — ').split(' · ')[0].trim() : text;
    return subject || 'E-mail ' + id;
  }

  function render(workspace, mailboxId, activeId) {
    if (!workspace) return;
    const old = workspace.querySelector(':scope > .brebo-mail-tabs');
    if (old) old.remove();

    const tabs = loadTabs(mailboxId);
    if (!tabs.length) return;

    const bar = document.createElement('nav');
    bar.className = 'brebo-mail-tabs';
    bar.setAttribute('aria-label', 'Open e-mails');

    tabs.forEach((tab) => {
      const item = document.createElement('div');
      item.className = 'brebo-mail-tab' + (Number(tab.id) === Number(activeId) ? ' is-active' : '');

      const link = document.createElement('a');
      link.className = 'brebo-mail-tab__link';
      link.href = tab.href;
      link.textContent = tab.label || 'E-mail ' + tab.id;
      link.title = tab.label || '';

      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'brebo-mail-tab__close';
      close.textContent = '×';
      close.setAttribute('aria-label', 'Tab sluiten');
      close.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const remaining = loadTabs(mailboxId).filter((entry) => Number(entry.id) !== Number(tab.id));
        saveTabs(mailboxId, remaining);
        if (Number(tab.id) === Number(activeId)) {
          const next = remaining[remaining.length - 1];
          window.location.assign(next ? next.href : '/mail/' + mailboxId + '/inbox');
          return;
        }
        render(workspace, mailboxId, activeId);
      });

      item.append(link, close);
      bar.append(item);
    });

    const layout = workspace.querySelector('.brebo-mail-layout');
    if (layout) workspace.insertBefore(bar, layout);
    else workspace.prepend(bar);
  }

  function init() {
    const workspace = document.querySelector('.brebo-mail-workspace');
    if (!workspace) return;

    const current = parseMailPath(window.location.pathname);
    const reader = workspace.querySelector('.brebo-mail-reader');

    if (current && reader) {
      const title = reader.querySelector('h2');
      remember(current.mailboxId, {
        id: current.communicationId,
        href: window.location.pathname + window.location.search,
        label: title && title.textContent.trim() ? title.textContent.trim() : 'E-mail ' + current.communicationId,
      });
      render(workspace, current.mailboxId, current.communicationId);
    }

    const list = workspace.querySelector('.brebo-mail-list');
    if (!list) return;

    list.addEventListener('click', (event) => {
      const link = event.target.closest('a');
      if (!link || !list.contains(link)) return;
      const parsed = parseMailPath(link.pathname);
      if (!parsed) return;
      remember(parsed.mailboxId, {
        id: parsed.communicationId,
        href: link.pathname + link.search,
        label: labelFromListLink(link, parsed.communicationId),
      });
    }, true);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
})();
