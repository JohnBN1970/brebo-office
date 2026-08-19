(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboCalculationBlockOrder = {
    attach(context) {
      once('brebo-calc-block-order', '.brebo-calc-workbench__grid--reorderable', context).forEach((grid) => {
        const form = grid.closest('form');
        const payloadInput = form?.querySelector('[data-brebo-block-order-payload]');
        const saveButton = form?.querySelector('[data-brebo-save-block-order]');
        if (!form || !payloadInput || !saveButton) return;

        let dragged = null;

        const primaryRows = () => Array.from(grid.querySelectorAll('tr[data-block-type="row"], tr[data-block-type="recipe"]'));
        const recipeChildren = (row) => {
          if (row.dataset.blockType !== 'recipe') return [];
          const id = row.dataset.recipeInstanceId;
          const children = [];
          let cursor = row.nextElementSibling;
          while (cursor && cursor.dataset.blockType === 'recipe-line' && cursor.dataset.recipeInstanceId === id) {
            children.push(cursor);
            cursor = cursor.nextElementSibling;
          }
          return children;
        };
        const group = (row) => [row, ...recipeChildren(row)];

        const orderPayload = () => {
          const byParagraph = {};
          primaryRows().forEach((row) => {
            const paragraph = row.dataset.structureKey || '';
            if (!paragraph) return;
            byParagraph[paragraph] ||= [];
            if (row.dataset.blockType === 'row') {
              byParagraph[paragraph].push({type: 'row', id: Number(row.dataset.lineId)});
            }
            else {
              byParagraph[paragraph].push({type: 'recipe', id: Number(row.dataset.recipeInstanceId)});
            }
          });
          return byParagraph;
        };

        const persist = () => {
          payloadInput.value = JSON.stringify(orderPayload());
          saveButton.click();
        };

        primaryRows().forEach((row) => {
          row.draggable = true;
          row.classList.add('brebo-calc-block--draggable');

          row.addEventListener('dragstart', (event) => {
            dragged = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', `${row.dataset.blockType}:${row.dataset.lineId || row.dataset.recipeInstanceId}`);
          });

          row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            grid.querySelectorAll('.is-drag-target').forEach((target) => target.classList.remove('is-drag-target'));
            dragged = null;
          });

          row.addEventListener('dragover', (event) => {
            if (!dragged || dragged === row || dragged.dataset.structureKey !== row.dataset.structureKey) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            row.classList.add('is-drag-target');
          });

          row.addEventListener('dragleave', () => row.classList.remove('is-drag-target'));

          row.addEventListener('drop', (event) => {
            row.classList.remove('is-drag-target');
            if (!dragged || dragged === row || dragged.dataset.structureKey !== row.dataset.structureKey) return;
            event.preventDefault();

            const draggedGroup = group(dragged);
            const targetGroup = group(row);
            const targetRect = row.getBoundingClientRect();
            const insertAfter = event.clientY > targetRect.top + (targetRect.height / 2);
            const anchor = insertAfter ? targetGroup[targetGroup.length - 1].nextSibling : row;
            draggedGroup.forEach((node) => row.parentNode.insertBefore(node, anchor));
            persist();
          });
        });
      });
    }
  };
})(Drupal, once);
