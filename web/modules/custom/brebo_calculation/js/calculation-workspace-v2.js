(function () {
  'use strict';

  const money = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' });

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function render(root, state) {
    const calculation = state.calculation || {};
    const version = state.version || {};
    const result = state.result || {};
    const commercial = result.commercial_result || {};
    const readiness = state.readiness || {};
    const structure = Array.isArray(state.structure) ? state.structure : [];
    const rows = Array.isArray(state.rows) ? state.rows : [];
    const recipes = Array.isArray(state.recipes) ? state.recipes : [];

    const rowsByParagraph = new Map();
    rows.forEach((row) => {
      const key = String(row.paragraph_key || '');
      if (!rowsByParagraph.has(key)) rowsByParagraph.set(key, []);
      rowsByParagraph.get(key).push(row);
    });

    const recipeByParagraph = new Map();
    recipes.forEach((recipe) => {
      const key = String(recipe.paragraph_key || '');
      if (!recipeByParagraph.has(key)) recipeByParagraph.set(key, []);
      recipeByParagraph.get(key).push(recipe);
    });

    const direct = Number(result.priced_direct_cost || 0);
    const sales = Number(commercial.sales_price || 0);

    let body = '';
    structure.forEach((item) => {
      const key = String(item.node_key || '');
      body += '<section class="brebo-sw-section" data-structure-key="' + esc(key) + '">'
        + '<header><span>' + esc(item.code || '') + '</span><strong>' + esc(item.label || '') + '</strong>'
        + (item.node_type === 'main_group' && state.editable ? '<button type="button" data-command="add-paragraph" data-parent="' + esc(key) + '">+ Paragraaf</button>' : '')
        + (item.node_type === 'paragraph' && state.editable ? '<button type="button" data-command="add-row" data-paragraph="' + esc(key) + '">+ Regel</button>' : '')
        + '</header>';

      (rowsByParagraph.get(key) || []).forEach((row) => {
        const qty = Number(row.actual_quantity ?? row.contract_quantity ?? 0);
        const unitCost = ['labour_unit_cost','material_unit_cost','equipment_unit_cost','subcontracting_unit_cost','other_unit_cost']
          .reduce((sum, field) => sum + Number(row[field] || 0), 0);
        const editable = Boolean(state.editable);
        const input = (field, value, type = 'text', step = '') => editable
          ? '<input data-row-field="' + field + '" type="' + type + '"' + (step ? ' step="' + step + '"' : '') + ' value="' + esc(value) + '">'
          : '<span>' + esc(value) + '</span>';
        body += '<div class="brebo-sw-row" data-row-id="' + esc(row.row_id) + '">'
          + '<span class="brebo-sw-row__code">' + esc(row.code || '') + '</span>'
          + '<span class="brebo-sw-row__description">' + input('description', row.description || '') + '</span>'
          + '<span>' + input('unit', row.unit || '') + '</span>'
          + '<span>' + input('quantity', qty, 'number', '0.0001') + '</span>'
          + '<span class="brebo-sw-costs">'
          + input('labour_unit_cost', Number(row.labour_unit_cost || 0), 'number', '0.01')
          + input('material_unit_cost', Number(row.material_unit_cost || 0), 'number', '0.01')
          + input('equipment_unit_cost', Number(row.equipment_unit_cost || 0), 'number', '0.01')
          + input('subcontracting_unit_cost', Number(row.subcontracting_unit_cost || 0), 'number', '0.01')
          + input('other_unit_cost', Number(row.other_unit_cost || 0), 'number', '0.01')
          + '</span>'
          + '<strong>' + money.format(qty * unitCost) + '</strong>'
          + (editable ? '<button type="button" class="brebo-sw-delete" data-command="delete-row" title="Regel verwijderen">×</button>' : '')
          + '</div>';
      });

      (recipeByParagraph.get(key) || []).forEach((recipe) => {
        body += '<div class="brebo-sw-row brebo-sw-row--recipe">'
          + '<span>RECEPT</span><span>' + esc(recipe.label || recipe.recipe_code || ('Recept ' + recipe.id)) + '</span>'
          + '<span></span><span>' + esc(recipe.quantity || '') + '</span><span></span><strong></strong></div>';
      });
      body += '</section>';
    });

    root.innerHTML = '<div class="brebo-sw">'
      + '<header class="brebo-sw-header"><div><small>' + esc(calculation.code || ('CALC-' + calculation.calculation_id)) + '</small>'
      + '<h1>' + esc(calculation.label || 'Calculatie') + '</h1><span>' + esc(calculation.project_label || 'Geen project gekoppeld') + '</span></div>'
      + '<div class="brebo-sw-status"><span>Versie ' + esc(version.version || '') + '</span><strong>' + esc(version.status || '') + '</strong></div></header>'
      + '<nav class="brebo-sw-tabs"><button class="is-active">Calculatie</button><button>Deelcalculaties</button><button>Recepten</button><button>Prijsbronnen</button><button>Controle</button>'
      + (state.editable ? '<span class="brebo-sw-tabs__spacer"></span><button data-command="add-group">+ Hoofdgroep</button>' : '') + '</nav>'
      + '<div class="brebo-sw-kpis"><div><small>Directe kostprijs</small><strong>' + money.format(direct) + '</strong></div>'
      + '<div><small>Verkoopprijs</small><strong>' + money.format(sales) + '</strong></div>'
      + '<div><small>Marge</small><strong>' + money.format(sales - direct) + '</strong></div>'
      + '<div><small>Readiness</small><strong>' + esc(readiness.status || '—') + '</strong></div></div>'
      + '<main class="brebo-sw-main"><div class="brebo-sw-grid-head"><span>Code</span><span>Omschrijving</span><span>Eenh.</span><span>Aantal</span><span>Kostendragers</span><span>Totaal</span><span></span></div>'
      + (body || '<div class="brebo-sw-empty">Nog geen calculatiestructuur.</div>') + '</main></div>';
  }

  function rowPayload(root, row) {
    const value = (field) => {
      const element = row.querySelector('[data-row-field="' + field + '"]');
      return element ? element.value : '';
    };
    const numeric = (field) => Number(value(field) || 0);
    return {
      version: root.dataset.version,
      description: value('description'),
      unit: value('unit'),
      quantity: numeric('quantity'),
      unit_costs: {
        labour: numeric('labour_unit_cost'),
        material: numeric('material_unit_cost'),
        equipment: numeric('equipment_unit_cost'),
        subcontracting: numeric('subcontracting_unit_cost'),
        other: numeric('other_unit_cost'),
      },
    };
  }

  async function load(root) {
    const response = await fetch(root.dataset.stateUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('Werkruimte kon niet worden geladen.');
    const state = await response.json();
    render(root, state);
    root.dataset.version = state.version && state.version.version ? state.version.version : '';
  }

  async function command(root, url, method, payload) {
    const response = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Handeling geweigerd.');
    await load(root);
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-command="add-row"]');
    if (!button) return;
    const root = button.closest('#brebo-calculation-workspace-v2');
    if (!root) return;
    button.disabled = true;
    try {
      const base = root.dataset.stateUrl.replace(/\/$/, '');
      await command(root, base + '/rows', 'POST', {
        version: root.dataset.version,
        paragraph_key: button.dataset.paragraph,
      });
    }
    catch (error) {
      window.alert(error.message);
      button.disabled = false;
    }
  });

  const rowSaveTimers = new Map();

  document.addEventListener('input', (event) => {
    const field = event.target.closest('[data-row-field]');
    if (!field) return;
    const row = field.closest('[data-row-id]');
    const root = field.closest('#brebo-calculation-workspace-v2');
    if (!row || !root) return;

    row.classList.add('is-dirty');
    const rowId = row.dataset.rowId;
    window.clearTimeout(rowSaveTimers.get(rowId));
    rowSaveTimers.set(rowId, window.setTimeout(async () => {
      const payload = rowPayload(root, row);
      if (!payload.description.trim() || !payload.unit.trim()) {
        row.classList.add('is-incomplete');
        return;
      }
      row.classList.remove('is-incomplete');
      row.classList.add('is-saving');
      try {
        const base = root.dataset.stateUrl.replace(/\/$/, '');
        await command(root, base + '/rows/' + rowId, 'PATCH', payload);
      }
      catch (error) {
        row.classList.remove('is-saving');
        window.alert(error.message);
      }
    }, 650));
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-command="add-group"], [data-command="add-paragraph"]');
    if (!button) return;
    const root = button.closest('#brebo-calculation-workspace-v2');
    if (!root) return;
    const isGroup = button.dataset.command === 'add-group';
    const label = window.prompt(isGroup ? 'Naam hoofdgroep' : 'Naam paragraaf');
    if (!label || !label.trim()) return;
    const code = window.prompt('Code (optioneel)') || '';
    const base = root.dataset.stateUrl.replace(/\/$/, '');
    const url = isGroup ? base + '/structure/groups' : base + '/structure/paragraphs';
    const payload = { version: root.dataset.version, label: label.trim(), code: code.trim() };
    if (!isGroup) payload.parent_key = button.dataset.parent;
    button.disabled = true;
    try {
      await command(root, url, 'POST', payload);
    }
    catch (error) {
      window.alert(error.message);
      button.disabled = false;
    }
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-command="delete-row"]');
    if (!button) return;
    const row = button.closest('[data-row-id]');
    const root = button.closest('#brebo-calculation-workspace-v2');
    if (!row || !root || !window.confirm('Deze calculatieregel verwijderen?')) return;
    button.disabled = true;
    try {
      const base = root.dataset.stateUrl.replace(/\/$/, '');
      await command(root, base + '/rows/' + row.dataset.rowId, 'DELETE', { version: root.dataset.version });
    }
    catch (error) {
      window.alert(error.message);
      button.disabled = false;
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('brebo-calculation-workspace-v2');
    if (!root) return;
    load(root).catch((error) => {
      root.innerHTML = '<div class="messages messages--error">' + esc(error.message) + '</div>';
    });
  });
}());
