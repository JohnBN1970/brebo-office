(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboCalculationWorkbench = {
    attach(context) {
      once('brebo-calc-workbench', '.brebo-calc-workbench__grid--editable', context).forEach((grid) => {
        const form = grid.closest('form');
        if (!form) return;
        const saveButton = form.querySelector('[data-drupal-selector="edit-workbench-actions-save"]');
        if (!saveButton || saveButton.disabled) return;

        let timer = null;
        let dirty = false;
        let saving = false;
        const money = (value) => new Intl.NumberFormat('nl-NL', {style:'currency',currency:'EUR',minimumFractionDigits:2,maximumFractionDigits:2}).format(Number.isFinite(value) ? value : 0);
        const num = (input) => { if (!input) return 0; const value = parseFloat(String(input.value).replace(',', '.')); return Number.isFinite(value) ? value : 0; };
        const structureRows = Array.from(grid.querySelectorAll('tr.brebo-calc-workbench__structure'));

        const lineTotal = (row) => {
          const quantity = num(row.querySelector('input[name$="[quantity]"]'));
          const unitTotal = ['labour_unit_cost','material_unit_cost','equipment_unit_cost','subcontracting_unit_cost','other_unit_cost']
            .reduce((sum, field) => sum + num(row.querySelector(`input[name$="[${field}]"]`)), 0);
          const type = row.querySelector('select[name$="[rule_type]"]')?.value || 'normal';
          return {unitTotal, total: quantity * unitTotal, included: type !== 'option' && type !== 'note', type};
        };

        const childRows = (structureRow) => {
          const rows = [];
          const isMainGroup = structureRow.classList.contains('type-main_group');
          let current = structureRow.nextElementSibling;
          while (current) {
            if (current.classList.contains('brebo-calc-workbench__structure')) {
              if (isMainGroup && current.classList.contains('depth-0')) break;
              if (!isMainGroup) break;
            }
            rows.push(current);
            current = current.nextElementSibling;
          }
          return rows;
        };

        const refreshClientTotals = () => {
          let grandTotal = 0;
          grid.querySelectorAll('tr.brebo-calc-workbench__line').forEach((row) => {
            const calc = lineTotal(row);
            const derived = row.querySelectorAll('.brebo-calc-derived');
            if (derived[0]) derived[0].textContent = money(calc.unitTotal);
            if (derived[1]) derived[1].textContent = money(calc.total);
            row.className = row.className.replace(/\brule-[^\s]+/g, '').trim() + ` rule-${calc.type}`;
            if (calc.included) grandTotal += calc.total;
          });

          structureRows.forEach((row) => {
            let subtotal = 0;
            childRows(row).forEach((child) => {
              if (!child.classList.contains('brebo-calc-workbench__line')) return;
              const calc = lineTotal(child);
              if (calc.included) subtotal += calc.total;
            });
            const totalCell = row.cells[12];
            if (totalCell) {
              let subtotalEl = totalCell.querySelector('.brebo-calc-structure-subtotal');
              if (!subtotalEl) {
                subtotalEl = document.createElement('strong');
                subtotalEl.className = 'brebo-calc-structure-subtotal';
                totalCell.appendChild(subtotalEl);
              }
              subtotalEl.textContent = money(subtotal);
              subtotalEl.title = 'Subtotaal excl. opties en notities';
            }
          });

          const totalEl = form.querySelector('.brebo-calc-workbench__total strong');
          if (totalEl) totalEl.textContent = money(grandTotal);
        };

        const setState = (state) => {
          const workbench = form.querySelector('#brebo-calculation-workbench');
          if (!workbench) return;
          workbench.dataset.saveState = state;
          workbench.classList.toggle('is-dirty', state === 'dirty');
          workbench.classList.toggle('is-saving', state === 'saving');
        };
        const autosave = () => {
          if (!dirty || saving || saveButton.disabled) return;
          dirty = false; saving = true; setState('saving'); saveButton.click();
          window.setTimeout(() => { saving = false; setState('saved'); if (dirty) autosave(); }, 900);
        };
        const schedule = () => { dirty = true; setState('dirty'); refreshClientTotals(); window.clearTimeout(timer); timer = window.setTimeout(autosave, 650); };

        const collapseStorageKey = `brebo-calc-collapse:${window.location.pathname}`;
        const collapsed = new Set(JSON.parse(window.localStorage.getItem(collapseStorageKey) || '[]'));
        const applyCollapse = () => {
          structureRows.forEach((row) => {
            const key = row.dataset.structureKey;
            if (!key) return;
            const isCollapsed = collapsed.has(key);
            row.classList.toggle('is-collapsed', isCollapsed);
            const toggle = row.querySelector('.brebo-calc-collapse-toggle');
            if (toggle) { toggle.textContent = isCollapsed ? '▸' : '▾'; toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true'); }
            if (isCollapsed) childRows(row).forEach((child) => child.classList.add('is-collapsed-child'));
          });
          structureRows.forEach((row) => {
            if (collapsed.has(row.dataset.structureKey)) return;
            childRows(row).forEach((child) => {
              const hiddenByParent = structureRows.some((parent) => collapsed.has(parent.dataset.structureKey) && childRows(parent).includes(child));
              if (!hiddenByParent) child.classList.remove('is-collapsed-child');
            });
          });
        };

        structureRows.forEach((row) => {
          const descriptionCell = row.cells[1];
          const key = row.dataset.structureKey;
          if (!descriptionCell || !key || descriptionCell.querySelector('.brebo-calc-collapse-toggle')) return;
          const button = document.createElement('button');
          button.type = 'button'; button.className = 'brebo-calc-collapse-toggle'; button.setAttribute('aria-label', 'In- of uitklappen');
          button.addEventListener('click', () => {
            if (collapsed.has(key)) collapsed.delete(key); else collapsed.add(key);
            window.localStorage.setItem(collapseStorageKey, JSON.stringify(Array.from(collapsed)));
            grid.querySelectorAll('.is-collapsed-child').forEach((rowEl) => rowEl.classList.remove('is-collapsed-child'));
            applyCollapse();
          });
          descriptionCell.prepend(button);
        });

        refreshClientTotals();
        applyCollapse();
        grid.addEventListener('input', (event) => { if (event.target.matches('input.brebo-calc-cell')) schedule(); });
        grid.addEventListener('change', (event) => { if (event.target.matches('select.brebo-calc-cell, input.brebo-calc-cell')) schedule(); });
        grid.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' && event.target.matches('input.brebo-calc-cell')) {
            event.preventDefault(); autosave();
            const inputs = Array.from(grid.querySelectorAll('input.brebo-calc-cell, select.brebo-calc-cell')).filter((input) => input.offsetParent !== null);
            const index = inputs.indexOf(event.target);
            if (index >= 0 && inputs[index + 1]) { inputs[index + 1].focus(); inputs[index + 1].select?.(); }
          }
        });
        grid.addEventListener('click', (event) => {
          const button = event.target.closest('[data-brebo-confirm-delete]');
          if (!button) return;
          if (!window.confirm(button.dataset.breboConfirmDelete || 'Deze regel verwijderen?')) { event.preventDefault(); event.stopImmediatePropagation(); }
        }, true);
      });
    }
  };
})(Drupal, once);
