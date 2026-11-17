(() => {
  'use strict';
  const list = document.querySelector('[data-widget-order]');
  let dragged;
  list?.addEventListener('dragstart', event => { dragged = event.target.closest('li'); });
  list?.addEventListener('dragover', event => event.preventDefault());
  list?.addEventListener('drop', event => { event.preventDefault(); const target = event.target.closest('li'); if (dragged && target && dragged !== target) list.insertBefore(dragged, target); dragged = null; });
  list?.addEventListener('click', event => {
    const button = event.target.closest('[data-move]'); if (!button) return;
    const row = button.closest('li');
    if (button.dataset.move === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
    if (button.dataset.move === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
    button.focus();
  });
  const monitor = document.querySelector('[data-wallboard]');
  if (!monitor) return;
  const status = monitor.querySelector('[role=status]');
  document.querySelector('[data-fullscreen]')?.addEventListener('click', async () => {
    try { if (!document.fullscreenElement) await monitor.requestFullscreen(); else await document.exitFullscreen(); }
    catch (_) { status.textContent = 'Volledig scherm niet beschikbaar in deze browser.'; }
  });
  let refreshing = false;
  let cycle = false;
  document.querySelector('[data-present]')?.addEventListener('click', event => { cycle = !cycle; event.currentTarget.setAttribute('aria-pressed', String(cycle)); });
  const refresh = async () => {
    if (refreshing || document.hidden) return;
    refreshing = true;
    const grid = monitor.querySelector('.office-dash-grid');
    // Clear stale values before network I/O, including after revoked sessions.
    grid.replaceChildren(); status.textContent = 'Gegevens vernieuwen…';
    try {
      const url = new URL(location.href); url.searchParams.set('format', 'json');
      if (cycle) {
        const ids = (monitor.dataset.boards || '').split(',').filter(Boolean);
        const next = ids[(ids.indexOf(url.searchParams.get('board') || 'main') + 1) % ids.length];
        if (next) url.searchParams.set('board', next);
      }
      const response = await fetch(url, {cache: 'no-store', credentials: 'same-origin', redirect: 'error', signal: AbortSignal.timeout(15000)});
      if (!response.ok) throw new Error('unavailable');
      const data = await response.json();
      if (!Array.isArray(data.widgets)) throw new Error('invalid');
      for (const widget of data.widgets) {
        const article = document.createElement('article'); article.className = 'office-dash-card'; article.dataset.tone = widget.tone;
        for (const [tag, text] of [['span', widget.label], ['strong', widget.value], ['small', widget.note]]) { const el = document.createElement(tag); el.textContent = text; article.append(el); }
        grid.append(article);
      }
      grid.dataset.columns = String(data.columns);
      url.searchParams.delete('format'); history.replaceState(null, '', url);
      status.textContent = data.updated;
    } catch (_) { status.textContent = 'Niet beschikbaar. De sessie kan verlopen zijn; open Office opnieuw.'; }
    finally { refreshing = false; }
  };
  setInterval(refresh, 60000);
  document.addEventListener('visibilitychange', () => { if (document.hidden) monitor.querySelector('.office-dash-grid').replaceChildren(); else refresh(); });
})();
