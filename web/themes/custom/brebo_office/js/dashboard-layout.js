(function (Drupal, once) {
  'use strict';

  const endpoint = 'brebo-office/dashboard-layout';
  const labels = {
    'Kerncijfers': 'core-kpis',
    'Regie en aandacht': 'regie',
    'Projectplanning en deadlines': 'planning',
    'Startgereedheid komende werkpakketten': 'readiness',
    'Financieel management': 'finance',
    'Managementsignalen': 'signals',
    'Bedrijfsinformatie': 'business',
    'Commercie en funnel': 'commercial',
    'Controle en risico': 'control-risk',
    'Kwaliteit en oplevering': 'quality',
  };
  const canonicalOrder = [
    'core-kpis',
    'regie',
    'control-risk',
    'quality',
    'planning',
    'readiness',
    'finance',
    'commercial',
    'signals',
    'business',
  ];

  let layout = {version: 1, density: 'normal', order: [], hidden: [], collapsed: []};
  let csrfToken = null;

  function idFor(block) {
    if (block.dataset.breboDashboardBlock) return block.dataset.breboDashboardBlock;
    const label = block.getAttribute('aria-label') || '';
    const id = labels[label];
    if (id) block.dataset.breboDashboardBlock = id;
    return id || null;
  }

  function blocks(dashboard) {
    return Array.from(dashboard.children).filter((el) => el.matches('section') && idFor(el));
  }

  function reorder(dashboard) {
    const current = blocks(dashboard);
    const order = layout.order.length ? layout.order : canonicalOrder;
    const index = new Map(order.map((id, position) => [id, position]));
    const desired = current.slice().sort((a, b) => {
      const aIndex = index.has(idFor(a)) ? index.get(idFor(a)) : Number.MAX_SAFE_INTEGER;
      const bIndex = index.has(idFor(b)) ? index.get(idFor(b)) : Number.MAX_SAFE_INTEGER;
      return aIndex === bIndex ? current.indexOf(a) - current.indexOf(b) : aIndex - bIndex;
    });
    if (desired.every((block, position) => block === current[position])) return;
    desired.forEach((block) => dashboard.appendChild(block));
  }

  function apply(dashboard) {
    dashboard.classList.toggle('brebo-dashboard--compact', layout.density === 'compact');
    reorder(dashboard);
    blocks(dashboard).forEach((block) => {
      const id = idFor(block);
      const isHidden = layout.hidden.includes(id);
      const isCollapsed = layout.collapsed.includes(id);
      block.classList.toggle('is-dashboard-hidden', isHidden);
      block.classList.toggle('is-dashboard-collapsed', isCollapsed);
      block.hidden = isHidden && !dashboard.classList.contains('is-layout-editing');
      ensureControls(block, dashboard);
    });
  }

  function ensureControls(block, dashboard) {
    if (block.querySelector(':scope > .brebo-dashboard-layout-controls')) return;
    const id = idFor(block);
    const title = block.getAttribute('aria-label') || id;
    const controls = document.createElement('div');
    controls.className = 'brebo-dashboard-layout-controls';
    controls.innerHTML = '<button type="button" class="brebo-dashboard-layout-handle" draggable="true" title="Verslepen">↕</button>' +
      '<strong>' + title + '</strong>' +
      '<span class="brebo-dashboard-layout-spacer"></span>' +
      '<button type="button" data-layout-action="collapse">In-/uitklappen</button>' +
      '<button type="button" data-layout-action="hide">Verbergen/tonen</button>';
    block.prepend(controls);

    controls.querySelector('[data-layout-action="collapse"]').addEventListener('click', () => {
      toggle(layout.collapsed, id);
      apply(dashboard);
      persist(dashboard);
    });
    controls.querySelector('[data-layout-action="hide"]').addEventListener('click', () => {
      toggle(layout.hidden, id);
      apply(dashboard);
      persist(dashboard);
    });

    const handle = controls.querySelector('.brebo-dashboard-layout-handle');
    handle.addEventListener('dragstart', (event) => {
      if (!dashboard.classList.contains('is-layout-editing')) return event.preventDefault();
      event.dataTransfer.setData('text/plain', id);
      event.dataTransfer.effectAllowed = 'move';
      block.classList.add('is-dashboard-dragging');
    });
    handle.addEventListener('dragend', () => block.classList.remove('is-dashboard-dragging'));
    block.addEventListener('dragover', (event) => {
      if (!dashboard.classList.contains('is-layout-editing')) return;
      event.preventDefault();
    });
    block.addEventListener('drop', (event) => {
      if (!dashboard.classList.contains('is-layout-editing')) return;
      event.preventDefault();
      const draggedId = event.dataTransfer.getData('text/plain');
      const dragged = dashboard.querySelector('[data-brebo-dashboard-block="' + draggedId + '"]');
      if (!dragged || dragged === block) return;
      const rect = block.getBoundingClientRect();
      dashboard.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? block : block.nextSibling);
      layout.order = blocks(dashboard).map(idFor);
      persist(dashboard);
    });
  }

  function toggle(list, id) {
    const index = list.indexOf(id);
    if (index >= 0) list.splice(index, 1); else list.push(id);
  }

  async function token() {
    if (csrfToken) return csrfToken;
    const response = await fetch(Drupal.url('session/token'), {credentials: 'same-origin'});
    csrfToken = response.ok ? await response.text() : null;
    return csrfToken;
  }

  async function persist(dashboard, reset) {
    try {
      const value = await token();
      if (!value) return;
      const payload = reset ? {reset: true} : {...layout, order: blocks(dashboard).map(idFor)};
      await fetch(Drupal.url(endpoint), {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': value},
        body: JSON.stringify(payload),
      });
    }
    catch (error) {}
  }

  function toolbar(dashboard) {
    const actions = dashboard.querySelector('.brebo-page-header__actions');
    if (!actions || actions.querySelector('.brebo-dashboard-layout-button')) return;
    const edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'button brebo-dashboard-layout-button';
    edit.textContent = Drupal.t('Indeling aanpassen');
    edit.addEventListener('click', () => {
      const editing = !dashboard.classList.contains('is-layout-editing');
      dashboard.classList.toggle('is-layout-editing', editing);
      edit.textContent = editing ? Drupal.t('Indeling gereed') : Drupal.t('Indeling aanpassen');
      apply(dashboard);
    });

    const density = document.createElement('button');
    density.type = 'button';
    density.className = 'button brebo-dashboard-layout-density';
    density.textContent = Drupal.t('Compact/normaal');
    density.addEventListener('click', () => {
      layout.density = layout.density === 'compact' ? 'normal' : 'compact';
      apply(dashboard);
      persist(dashboard);
    });

    const reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'button brebo-dashboard-layout-reset';
    reset.textContent = Drupal.t('BREBO standaard');
    reset.addEventListener('click', () => {
      layout = {version: 1, density: 'normal', order: canonicalOrder.slice(), hidden: [], collapsed: []};
      dashboard.classList.remove('is-layout-editing');
      blocks(dashboard).forEach((block) => { block.hidden = false; });
      apply(dashboard);
      layout.order = [];
      edit.textContent = Drupal.t('Indeling aanpassen');
      persist(dashboard, true);
    });
    actions.prepend(reset, density, edit);
  }

  Drupal.behaviors.breboDashboardLayout = {
    attach(context) {
      once('brebo-dashboard-layout', '.brebo-dashboard', context).forEach(async (dashboard) => {
        try {
          const response = await fetch(Drupal.url(endpoint), {credentials: 'same-origin', headers: {Accept: 'application/json'}});
          if (response.ok) layout = await response.json();
        }
        catch (error) {}
        toolbar(dashboard);
        apply(dashboard);
        const observer = new MutationObserver(() => apply(dashboard));
        observer.observe(dashboard, {childList: true});
      });
    },
  };
})(Drupal, once);
