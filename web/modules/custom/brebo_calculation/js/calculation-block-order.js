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
        const liveRegion = document.createElement('div');
        liveRegion.className = 'brebo-calc-block-order-live';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        grid.before(liveRegion);

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
        const labelFor = (row) => {
          const description = row.querySelector('td:nth-child(2)')?.textContent?.trim();
          return description || (row.dataset.blockType === 'recipe' ? 'Recept' : 'Calculatieregel');
        };
        const announce = (message) => {
          liveRegion.textContent = '';
          window.setTimeout(() => { liveRegion.textContent = message; }, 0);
        };

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

        const siblingPrimaryRows = (row) => primaryRows().filter((candidate) => candidate.dataset.structureKey === row.dataset.structureKey);

        const moveWithKeyboard = (row, direction) => {
          const siblings = siblingPrimaryRows(row);
          const currentIndex = siblings.indexOf(row);
          const targetIndex = currentIndex + direction;
          if (currentIndex < 0 || targetIndex < 0 || targetIndex >= siblings.length) {
            announce(direction < 0 ? 'Dit blok staat al bovenaan de paragraaf.' : 'Dit blok staat al onderaan de paragraaf.');
            return;
          }

          const target = siblings[targetIndex];
          const rowGroup = group(row);
          const targetGroup = group(target);
          const anchor = direction < 0 ? target : targetGroup[targetGroup.length - 1].nextSibling;
          rowGroup.forEach((node) => row.parentNode.insertBefore(node, anchor));
          persist();
          announce(`${labelFor(row)} ${direction < 0 ? 'omhoog' : 'omlaag'} verplaatst.`);
          row.querySelector('.brebo-calc-block-handle')?.focus();
        };

        primaryRows().forEach((row) => {
          row.classList.add('brebo-calc-block--draggable');

          const firstCell = row.querySelector('td');
          if (firstCell && !firstCell.querySelector('.brebo-calc-block-handle')) {
            const handle = document.createElement('button');
            handle.type = 'button';
            handle.className = 'brebo-calc-block-handle';
            handle.draggable = true;
            handle.setAttribute('aria-label', `${labelFor(row)} verplaatsen. Sleep of gebruik Alt+pijl omhoog/omlaag.`);
            handle.setAttribute('title', 'Sleep om te verplaatsen · Alt + ↑/↓');
            handle.textContent = '⋮⋮';
            firstCell.prepend(handle);

            handle.addEventListener('keydown', (event) => {
              if (!event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return;
              event.preventDefault();
              moveWithKeyboard(row, event.key === 'ArrowUp' ? -1 : 1);
            });

            handle.addEventListener('dragstart', (event) => {
              dragged = row;
              row.classList.add('is-dragging');
              event.dataTransfer.effectAllowed = 'move';
              event.dataTransfer.setData('text/plain', `${row.dataset.blockType}:${row.dataset.lineId || row.dataset.recipeInstanceId}`);
            });

            handle.addEventListener('dragend', () => {
              row.classList.remove('is-dragging');
              grid.querySelectorAll('.is-drag-target').forEach((target) => target.classList.remove('is-drag-target'));
              dragged = null;
            });
          }

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
            const movedLabel = labelFor(dragged);
            draggedGroup.forEach((node) => row.parentNode.insertBefore(node, anchor));
            persist();
            announce(`${movedLabel} verplaatst.`);
          });
        });
      });
    }
  };
})(Drupal, once);
