(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboCalculationWorkbench = {
    attach(context) {
      once('brebo-calc-workbench', '.brebo-calc-workbench__grid--editable', context).forEach((grid) => {
        const form = grid.closest('form');
        if (!form) {
          return;
        }

        const saveButton = form.querySelector('[data-drupal-selector="edit-workbench-actions-save"]');
        if (!saveButton || saveButton.disabled) {
          return;
        }

        let timer = null;
        let dirty = false;
        let saving = false;

        const money = (value) => new Intl.NumberFormat('nl-NL', {
          style: 'currency',
          currency: 'EUR',
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }).format(Number.isFinite(value) ? value : 0);

        const num = (input) => {
          if (!input) return 0;
          const value = parseFloat(String(input.value).replace(',', '.'));
          return Number.isFinite(value) ? value : 0;
        };

        const refreshClientTotals = () => {
          let grandTotal = 0;
          grid.querySelectorAll('tr.brebo-calc-workbench__line').forEach((row) => {
            const quantity = num(row.querySelector('input[name$="[quantity]"]'));
            const unitTotal = ['labour_unit_cost', 'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost', 'other_unit_cost']
              .reduce((sum, field) => sum + num(row.querySelector(`input[name$="[${field}]"]`)), 0);
            const total = quantity * unitTotal;
            const derived = row.querySelectorAll('.brebo-calc-derived');
            if (derived[0]) derived[0].textContent = money(unitTotal);
            if (derived[1]) derived[1].textContent = money(total);
            const type = row.querySelector('select[name$="[rule_type]"]')?.value || 'normal';
            row.className = row.className.replace(/\brule-[^\s]+/g, '').trim() + ` rule-${type}`;
            if (type !== 'option' && type !== 'note') grandTotal += total;
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
          dirty = false;
          saving = true;
          setState('saving');
          saveButton.click();
          window.setTimeout(() => {
            saving = false;
            setState('saved');
            if (dirty) autosave();
          }, 900);
        };

        const schedule = () => {
          dirty = true;
          setState('dirty');
          refreshClientTotals();
          window.clearTimeout(timer);
          timer = window.setTimeout(autosave, 650);
        };

        grid.addEventListener('input', (event) => {
          if (event.target.matches('input.brebo-calc-cell')) schedule();
        });
        grid.addEventListener('change', (event) => {
          if (event.target.matches('select.brebo-calc-cell, input.brebo-calc-cell')) schedule();
        });
        grid.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' && event.target.matches('input.brebo-calc-cell')) {
            event.preventDefault();
            autosave();
            const inputs = Array.from(grid.querySelectorAll('input.brebo-calc-cell, select.brebo-calc-cell'));
            const index = inputs.indexOf(event.target);
            if (index >= 0 && inputs[index + 1]) {
              inputs[index + 1].focus();
              inputs[index + 1].select?.();
            }
          }
        });
        grid.addEventListener('click', (event) => {
          const button = event.target.closest('[data-brebo-confirm-delete]');
          if (!button) return;
          if (!window.confirm(button.dataset.breboConfirmDelete || 'Deze regel verwijderen?')) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        }, true);
      });
    }
  };
})(Drupal, once);
