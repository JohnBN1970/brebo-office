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

  function decodeQuotedPrintable(value) {
    const input = value.replace(/=\r?\n/g, '');
    const bytes = [];
    const encoder = new TextEncoder();
    for (let i = 0; i < input.length; i++) {
      if (input[i] === '=' && /^[0-9A-F]{2}$/i.test(input.slice(i + 1, i + 3))) {
        bytes.push(parseInt(input.slice(i + 1, i + 3), 16));
        i += 2;
        continue;
      }
      bytes.push(...encoder.encode(input[i]));
    }
    try {
      return new TextDecoder('utf-8').decode(new Uint8Array(bytes));
    }
    catch (e) {
      return input.replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
    }
  }

  function decodeBase64Utf8(value) {
    try {
      const binary = atob(value.replace(/\s+/g, ''));
      const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
      return new TextDecoder('utf-8').decode(bytes);
    }
    catch (e) {
      return value;
    }
  }

  function htmlToText(value) {
    const parsed = new DOMParser().parseFromString(value, 'text/html');
    return (parsed.body.innerText || parsed.body.textContent || value)
      .replace(/\u00a0/g, ' ')
      .replace(/[ \t]+\n/g, '\n')
      .replace(/\n[ \t]+/g, '\n');
  }

  function cleanLegacyMime(raw) {
    let text = String(raw || '').replace(/\r\n/g, '\n').trim();
    if (!text) return text;

    const looksLikeMime = /Content-(?:Type|Transfer-Encoding|Disposition):/i.test(text) || /^--[-_=A-Za-z0-9]{8,}/m.test(text);
    if (looksLikeMime) {
      const boundaryMatch = text.match(/^--([^\n]+)$/m);
      if (boundaryMatch) {
        const boundary = boundaryMatch[1].replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const parts = text.split(new RegExp('^--' + boundary + '(?:--)?\\s*$', 'm')).map((part) => part.trim()).filter(Boolean);
        const plain = parts.find((part) => /Content-Type:\s*text\/plain/i.test(part));
        const html = parts.find((part) => /Content-Type:\s*text\/html/i.test(part));
        text = plain || html || parts[0] || text;
      }

      const transfer = (text.match(/Content-Transfer-Encoding:\s*([^\s;]+)/i) || [])[1]?.toLowerCase() || '';
      const split = text.match(/\n\s*\n/);
      if (split && split.index !== undefined) text = text.slice(split.index + split[0].length);

      if (transfer === 'quoted-printable' || /=([0-9A-F]{2})/i.test(text)) text = decodeQuotedPrintable(text);
      else if (transfer === 'base64') text = decodeBase64Utf8(text);

      text = text
        .replace(/^Content-(?:Type|Transfer-Encoding|Disposition):.*$/gmi, '')
        .replace(/^MIME-Version:.*$/gmi, '')
        .replace(/^--[^\n]+(?:--)?$/gm, '')
        .trim();
    }

    if (/<\/?[a-z][^>]*>/i.test(text) || /&(?:nbsp|amp|lt|gt|quot|#\d+|#x[0-9a-f]+);/i.test(text)) text = htmlToText(text);

    // Some legacy imports contain a second quoted-printable layer after HTML decoding.
    for (let pass = 0; pass < 2 && /=([0-9A-F]{2})/i.test(text); pass++) {
      const decoded = decodeQuotedPrintable(text);
      if (decoded === text) break;
      text = decoded;
    }

    return text
      .replace(/\n{3,}/g, '\n\n')
      .replace(/[ \t]{2,}/g, ' ')
      .trim();
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
    const destination = window.location.pathname + window.location.search;
    const contextHref = selectedId ? '/communicatie/' + selectedId + '/koppelen?destination=' + encodeURIComponent(destination) : '';
    const toolbar = document.createElement('div');
    toolbar.className = 'brebo-mail-toolbar';

    const compose = document.createElement('div');
    compose.className = 'brebo-mail-toolbar__group';
    compose.append(action('+', 'Nieuw bericht', composeHref, true));

    const message = document.createElement('div');
    message.className = 'brebo-mail-toolbar__group';
    message.append(action('↩', 'Beantwoorden', replyHref), action('↪', 'Doorsturen', forwardHref));
    const stateForm = workspace.querySelector('form.brebo-mail-state-actions');
    if (stateForm) {
      stateForm.classList.add('brebo-mail-state-actions--toolbar');
      message.append(stateForm);
    }

    const office = document.createElement('div');
    office.className = 'brebo-mail-toolbar__group';
    office.append(action('🔗', 'Koppelen in Office', contextHref), action('✓', 'Inhoud verwerken', processHref));
    toolbar.append(compose, message, office);
    if (layout) workspace.insertBefore(toolbar, layout); else workspace.prepend(toolbar);

    const reader = workspace.querySelector('.brebo-mail-reader');
    const article = reader ? reader.querySelector('article') : null;
    const readerBody = reader ? reader.querySelector('.brebo-mail-reader__body') : null;
    if (readerBody) {
      const cleaned = cleanLegacyMime(readerBody.innerText || readerBody.textContent || '');
      if (cleaned) readerBody.textContent = cleaned;
    }

    if (article) {
      const title = article.querySelector('h2');
      const meta = article.querySelector('.brebo-mail-reader__meta');
      const sticky = document.createElement('div');
      sticky.className = 'brebo-mail-reader__sticky';
      if (title) sticky.append(title);
      if (meta) sticky.append(meta);
      article.prepend(sticky);
    }

  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance, {once: true}); else enhance();
})();