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
