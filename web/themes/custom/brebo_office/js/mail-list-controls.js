(() => {
  'use strict';

  const normalize = (value) => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  function parseItem(item) {
    const link = item.querySelector('a');
    const text = (link?.textContent || '').trim();
    const unread = text.startsWith('●') || item.classList.contains('is-unread');
    const starred = text.includes('★');
    const needsAction = text.includes('⚑');
    const cleaned = text.replace(/^[★●⚑\s]+/, '').trim();
    const [left = '', date = ''] = cleaned.split(' · ');
    const split = left.indexOf(' — ');
    const from = split >= 0 ? left.slice(0, split).trim() : left.trim();
    const subject = split >= 0 ? left.slice(split + 3).trim() : '';
    const thread = subject.replace(/^\s*((re|fw|fwd|aw|sv)\s*:\s*)+/i, '').trim() || '(geen onderwerp)';
    const day = date ? date.slice(0, 10) : 'Onbekende datum';
    return {item, link, text, from, subject, date, unread, starred, needsAction, thread, day};
  }

  function makeSelect(label, options) {
    const wrap = document.createElement('label');
    wrap.className = 'brebo-mail-list-control';
    const span = document.createElement('span');
    span.textContent = label;
    const select = document.createElement('select');
    Object.entries(options).forEach(([value, title]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = title;
      select.append(option);
    });
    wrap.append(span, select);
    return {wrap, select};
  }

  function enhance() {
    const workspace = document.querySelector('.brebo-mail-workspace');
    const list = workspace?.querySelector('.brebo-mail-list');
    const layout = workspace?.querySelector('.brebo-mail-layout');
    if (!workspace || !list || list.dataset.controlsEnhanced === '1') return;
    const rows = Array.from(list.children).filter((child) => child.querySelector('a')).map(parseItem);
    if (!rows.length) return;
    list.dataset.controlsEnhanced = '1';

    const bar = document.createElement('div');
    bar.className = 'brebo-mail-list-controls';

    const searchWrap = document.createElement('label');
    searchWrap.className = 'brebo-mail-list-search';
    const searchLabel = document.createElement('span');
    searchLabel.textContent = 'Zoeken';
    const search = document.createElement('input');
    search.type = 'search';
    search.placeholder = 'Afzender, onderwerp of tag';
    search.autocomplete = 'off';
    searchWrap.append(searchLabel, search);

    const filter = makeSelect('Filter', {
      all: 'Alles',
      unread: 'Ongelezen',
      starred: 'Met ster',
      action: 'Actie nodig',
    });
    const group = makeSelect('Groeperen', {
      none: 'Niet groeperen',
      thread: 'Conversatie',
      sender: 'Afzender',
      date: 'Datum',
      status: 'Status',
    });
    const sort = makeSelect('Sorteren', {
      date_desc: 'Nieuwste eerst',
      date_asc: 'Oudste eerst',
      sender: 'Afzender A-Z',
      subject: 'Onderwerp A-Z',
      unread: 'Ongelezen eerst',
      action: 'Actie nodig eerst',
    });

    const count = document.createElement('span');
    count.className = 'brebo-mail-list-count';
    bar.append(searchWrap, filter.wrap, group.wrap, sort.wrap, count);
    if (layout) workspace.insertBefore(bar, layout);
    else workspace.append(bar);

    function statusLabel(row) {
      if (row.needsAction) return 'Actie nodig';
      if (row.unread) return 'Ongelezen';
      if (row.starred) return 'Met ster';
      return 'Overig';
    }

    function matches(row) {
      const query = normalize(search.value.trim());
      if (query && !normalize(row.from + ' ' + row.subject + ' ' + row.text).includes(query)) return false;
      if (filter.select.value === 'unread' && !row.unread) return false;
      if (filter.select.value === 'starred' && !row.starred) return false;
      if (filter.select.value === 'action' && !row.needsAction) return false;
      return true;
    }

    function sorter(a, b) {
      switch (sort.select.value) {
        case 'date_asc': return a.date.localeCompare(b.date);
        case 'sender': return a.from.localeCompare(b.from, 'nl');
        case 'subject': return a.subject.localeCompare(b.subject, 'nl');
        case 'unread': return Number(b.unread) - Number(a.unread) || b.date.localeCompare(a.date);
        case 'action': return Number(b.needsAction) - Number(a.needsAction) || b.date.localeCompare(a.date);
        default: return b.date.localeCompare(a.date);
      }
    }

    function groupKey(row) {
      switch (group.select.value) {
        case 'thread': return row.thread;
        case 'sender': return row.from || 'Onbekende afzender';
        case 'date': return row.day;
        case 'status': return statusLabel(row);
        default: return '';
      }
    }

    function render() {
      list.querySelectorAll('.brebo-mail-list-group').forEach((el) => el.remove());
      const visible = rows.filter(matches).sort(sorter);
      rows.forEach((row) => { row.item.hidden = true; });
      count.textContent = visible.length + (visible.length === 1 ? ' bericht' : ' berichten');

      if (group.select.value === 'none') {
        visible.forEach((row) => {
          row.item.hidden = false;
          list.append(row.item);
        });
        return;
      }

      const groups = new Map();
      visible.forEach((row) => {
        const key = groupKey(row);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(row);
      });
      groups.forEach((groupRows, key) => {
        const section = document.createElement('section');
        section.className = 'brebo-mail-list-group';
        const header = document.createElement('button');
        header.type = 'button';
        header.className = 'brebo-mail-list-group__header';
        header.setAttribute('aria-expanded', 'true');
        header.innerHTML = '<span></span><small></small>';
        header.querySelector('span').textContent = key;
        header.querySelector('small').textContent = groupRows.length;
        const body = document.createElement('div');
        body.className = 'brebo-mail-list-group__body';
        groupRows.forEach((row) => {
          row.item.hidden = false;
          body.append(row.item);
        });
        header.addEventListener('click', () => {
          const collapsed = section.classList.toggle('is-collapsed');
          header.setAttribute('aria-expanded', String(!collapsed));
        });
        section.append(header, body);
        list.append(section);
      });
    }

    search.addEventListener('input', render);
    filter.select.addEventListener('change', render);
    group.select.addEventListener('change', render);
    sort.select.addEventListener('change', render);
    render();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance, {once: true});
  else enhance();
})();
