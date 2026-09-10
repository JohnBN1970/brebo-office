(() => {
  'use strict';

  function mailboxIdFromHref(href) {
    try {
      const parts = new URL(href, window.location.origin).pathname.split('/').filter(Boolean);
      return parts[0] === 'mail' && /^\d+$/.test(parts[1] || '') ? Number(parts[1]) : 0;
    }
    catch (e) {
      return 0;
    }
  }

  function currentMailboxId() {
    const parts = window.location.pathname.split('/').filter(Boolean);
    return parts[0] === 'mail' && /^\d+$/.test(parts[1] || '') ? Number(parts[1]) : 0;
  }

  function structureMessageRows(list) {
    if (!list) return;
    list.querySelectorAll('.brebo-mail-message-row > a, .brebo-mail-item > a').forEach((link) => {
      if (link.dataset.compactStructured === '1') return;
      const raw = (link.textContent || '').trim();
      if (!raw) return;

      let left = raw;
      let date = '';
      const dateIndex = raw.lastIndexOf(' · ');
      if (dateIndex >= 0) {
        left = raw.slice(0, dateIndex).trim();
        date = raw.slice(dateIndex + 3).trim();
      }

      const flagMatch = left.match(/^([★●⚑🔗\s]+)/u);
      const flags = flagMatch ? flagMatch[1].trim() : '';
      if (flagMatch) left = left.slice(flagMatch[1].length).trim();

      const split = left.indexOf(' — ');
      const from = split >= 0 ? left.slice(0, split).trim() : left;
      const subject = split >= 0 ? left.slice(split + 3).trim() : '';

      const top = document.createElement('span');
      top.className = 'brebo-mail-item__topline';

      const sender = document.createElement('span');
      sender.className = 'brebo-mail-item__from';
      sender.textContent = from || 'Onbekende afzender';
      top.append(sender);

      if (date) {
        const when = document.createElement('span');
        when.className = 'brebo-mail-item__date';
        when.textContent = date;
        top.append(when);
      }

      const bottom = document.createElement('span');
      bottom.className = 'brebo-mail-item__bottomline';

      if (flags) {
        const status = document.createElement('span');
        status.className = 'brebo-mail-item__flags';
        status.textContent = flags;
        bottom.append(status);
      }

      const subjectEl = document.createElement('span');
      subjectEl.className = 'brebo-mail-item__subject';
      subjectEl.textContent = subject || '(geen onderwerp)';
      bottom.append(subjectEl);

      link.replaceChildren(top, bottom);
      link.dataset.compactStructured = '1';
    });
  }

  function enhance() {
    const workspace = document.querySelector('.brebo-mail-workspace');
    const folders = workspace?.querySelector('.brebo-mail-folders');
    const layout = workspace?.querySelector('.brebo-mail-layout');
    const list = workspace?.querySelector('.brebo-mail-list');
    const reader = workspace?.querySelector('.brebo-mail-reader');
    if (!workspace || !folders || !layout || folders.dataset.compactNavigation === '1') return;
    folders.dataset.compactNavigation = '1';

    const compose = workspace.querySelector('.brebo-mail-toolbar .brebo-mail-action--primary');
    if (compose) {
      const shortcut = document.createElement('div');
      shortcut.className = 'brebo-mail-compose-shortcut';
      shortcut.append(compose);
      folders.prepend(shortcut);
      const emptyGroup = workspace.querySelector('.brebo-mail-toolbar__group:empty');
      if (emptyGroup) emptyGroup.remove();
    }

    const toolbar = workspace.querySelector('.brebo-mail-toolbar');
    if (toolbar && reader) {
      toolbar.classList.add('brebo-mail-reader-toolbar');
      reader.prepend(toolbar);
    }

    const controls = workspace.querySelector('.brebo-mail-list-controls');
    if (controls && list) {
      controls.classList.add('brebo-mail-list-controls--local');
      list.prepend(controls);
    }

    structureMessageRows(list);

    const children = Array.from(folders.children).filter((child) => !child.classList.contains('brebo-mail-compose-shortcut'));
    const groups = [];
    let group = null;

    children.forEach((child) => {
      if (child.querySelector('strong')) {
        group = {heading: child, folders: []};
        groups.push(group);
        return;
      }
      if (group && child.querySelector('a')) group.folders.push(child);
    });

    const selectedMailbox = currentMailboxId();
    let selectedGroup = groups.find((candidate) => candidate.folders.some((item) => mailboxIdFromHref(item.querySelector('a')?.href || '') === selectedMailbox));
    if (!selectedGroup) selectedGroup = groups[0] || null;

    function show(target) {
      groups.forEach((candidate) => {
        const active = candidate === target;
        candidate.heading.classList.toggle('is-current', active);
        candidate.folders.forEach((item) => { item.hidden = !active; });
      });
    }

    groups.forEach((candidate) => {
      const strong = candidate.heading.querySelector('strong');
      if (!strong) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'brebo-mail-mailbox-switch';
      button.textContent = strong.textContent.trim();
      strong.replaceWith(button);
      button.addEventListener('click', () => show(candidate));

      candidate.folders.forEach((item) => {
        const link = item.querySelector('a');
        if (!link) return;
        const active = mailboxIdFromHref(link.href) === selectedMailbox && link.pathname === window.location.pathname.replace(/\/\d+$/, '');
        item.classList.toggle('is-current-folder', active);
      });
    });

    if (selectedGroup) show(selectedGroup);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance, {once: true});
  else enhance();
})();
