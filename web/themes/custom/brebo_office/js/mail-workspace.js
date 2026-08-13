(() => {
  'use strict';

  function action(icon, label, href = '', primary = false) {
    const el = document.createElement(href ? 'a' : 'span');
    el.className = 'brebo-mail-action' + (primary ? ' brebo-mail-action--primary' : '') + (!href ? ' brebo-mail-action--disabled' : '');
    if (href) el.href = href; else el.setAttribute('aria-disabled', 'true');
    el.innerHTML = '<span class="brebo-mail-action__icon"></span><span class="brebo-mail-action__label"></span>';
    el.querySelector('.brebo-mail-action__icon').textContent = icon;
    el.querySelector('.brebo-mail-action__label').textContent = label;
    return el;
  }

  function routeParts() {
    return window.location.pathname.split('/').filter(Boolean);
  }

  function selectedCommunicationId() {
    const parts = routeParts();
    if (parts[0] !== 'mail' || parts.length < 4) return 0;
    const id = Number(parts[3]);
    return Number.isInteger(id) && id > 0 ? id : 0;
  }

  function selectedMailboxId() {
    const parts = routeParts();
    if (parts[0] !== 'mail' || parts.length < 2) return 0;
    const id = Number(parts[1]);
    return Number.isInteger(id) && id > 0 ? id : 0;
  }

  function enhance() {
    const workspace = document.querySelector('.brebo-mail-workspace');
    if (!workspace || workspace.dataset.officeEnhanced === '1') return;
    workspace.dataset.officeEnhanced = '1';

    const layout = Array.from(workspace.children).find((el) => (el.getAttribute('style') || '').includes('grid-template-columns'));
    if (layout) {
      layout.removeAttribute('style');
      layout.classList.add('brebo-mail-layout');
    }

    const folders = workspace.querySelector('.brebo-mail-folders');
    if (folders) {
      Array.from(folders.children).forEach((child) => {
        if (child.querySelector('strong')) child.classList.add('brebo-mail-mailbox-name');
        else if (child.querySelector('a')) child.classList.add('brebo-mail-folder');
      });
    }

    const list = workspace.querySelector('.brebo-mail-list');
    const selectedId = selectedCommunicationId();
    const mailboxId = selectedMailboxId();
    if (list) {
      Array.from(list.children).forEach((child) => {
        const link = child.querySelector('a');
        if (!link) return;
        child.classList.add('brebo-mail-item');
        if (selectedId && link.pathname.endsWith('/' + selectedId)) child.classList.add('is-selected');
        if (link.textContent.trim().startsWith('●')) child.classList.add('is-unread');
      });
    }

    const composeHref = mailboxId ? '/mail/' + mailboxId + '/opstellen' : '';
    const replyHref = mailboxId && selectedId ? '/mail/' + mailboxId + '/opstellen/reply/' + selectedId : '';
    const forwardHref = mailboxId && selectedId ? '/mail/' + mailboxId + '/opstellen/forward/' + selectedId : '';
    const processHref = selectedId ? '/communicatie/' + selectedId + '/verwerken' : '';
    const toolbar = document.createElement('div');
    toolbar.className = 'brebo-mail-toolbar';
    const compose = document.createElement('div'); compose.className = 'brebo-mail-toolbar__group'; compose.append(action('+', 'Nieuw bericht', composeHref, true));
    const message = document.createElement('div'); message.className = 'brebo-mail-toolbar__group'; message.append(action('↩', 'Beantwoorden', replyHref), action('↪', 'Doorsturen', forwardHref), action('★', 'Markeren'), action('▣', 'Archiveren'), action('⌫', 'Verwijderen'));
    const office = document.createElement('div'); office.className = 'brebo-mail-toolbar__group'; office.append(action('✓', 'Verwerken in Office', processHref), action('↗', 'Open communicatie', selectedId ? '/node/' + selectedId : ''), action('✎', 'Bewerken', selectedId ? '/node/' + selectedId + '/edit' : ''), action('☷', 'Beoordelingswerkbak', '/admin/brebo/mail-intake'));
    toolbar.append(compose, message, office);
    if (layout) workspace.insertBefore(toolbar, layout); else workspace.prepend(toolbar);

    const reader = workspace.querySelector('.brebo-mail-reader');
    const article = reader ? reader.querySelector('article') : null;
    if (article) {
      const actions = document.createElement('div');
      actions.className = 'brebo-mail-office-actions';
      const label = document.createElement('div'); label.className = 'brebo-mail-office-actions__label'; label.textContent = 'BREBO Office'; actions.append(label);
      actions.append(action('✓', 'Verwerken en koppelen', processHref), action('↩', 'Beantwoorden', replyHref), action('↪', 'Doorsturen', forwardHref), action('↗', 'Communicatiedossier', selectedId ? '/node/' + selectedId : ''), action('⌂', 'Koppel gebouw'), action('◆', 'Koppel project'), action('✓', 'Maak taak'), action('▤', 'Naar dossier'));
      article.prepend(actions);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance, {once: true}); else enhance();
})();