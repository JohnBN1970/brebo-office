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
        + (item.node_type === 'paragraph' && state.editable ? '<button type="button" data-command="add-row" data-paragraph="' + esc(key) + '">+ Regel</button>' : '')
        + '</header>';

      (rowsByParagraph.get(key) || []).forEach((row) => {
        const qty = Number(row.actual_quantity ?? row.contract_quantity ?? 0);
        const unitCost = ['labour_unit_cost','material_unit_cost','equipment_unit_cost','subcontracting_unit_cost','other_unit_cost']
          .reduce((sum, field) => sum + Number(row[field] || 0), 0);
        body += '<div class="brebo-sw-row" data-row-id="' + esc(row.row_id) + '">'
          + '<span class="brebo-sw-row__code">' + esc(row.code || '') + '</span>'
          + '<span class="brebo-sw-row__description">' + esc(row.description || '') + '</span>'
          + '<span>' + esc(row.unit || '') + '</span>'
          + '<span>' + esc(qty) + '</span>'
          + '<span>' + money.format(unitCost) + '</span>'
          + '<strong>' + money.format(qty * unitCost) + '</strong>'
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
      + '<nav class="brebo-sw-tabs"><button class="is-active">Calculatie</button><button>Deelcalculaties</button><button>Recepten</button><button>Prijsbronnen</button><button>Controle</button></nav>'
      + '<div class="brebo-sw-kpis"><div><small>Directe kostprijs</small><strong>' + money.format(direct) + '</strong></div>'
      + '<div><small>Verkoopprijs</small><strong>' + money.format(sales) + '</strong></div>'
      + '<div><small>Marge</small><strong>' + money.format(sales - direct) + '</strong></div>'
      + '<div><small>Readiness</small><strong>' + esc(readiness.status || '—') + '</strong></div></div>'
      + '<main class="brebo-sw-main"><div class="brebo-sw-grid-head"><span>Code</span><span>Omschrijving</span><span>Eenh.</span><span>Aantal</span><span>Eenheidsprijs</span><span>Totaal</span></div>'
      + (body || '<div class="brebo-sw-empty">Nog geen calculatiestructuur.</div>') + '</main></div>';
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

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('brebo-calculation-workspace-v2');
    if (!root) return;
    load(root).catch((error) => {
      root.innerHTML = '<div class="messages messages--error">' + esc(error.message) + '</div>';
    });
  });
}());
