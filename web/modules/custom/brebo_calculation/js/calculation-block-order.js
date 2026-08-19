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
        const structureRows = () => Array.from(grid.querySelectorAll('tr.brebo-calc-workbench__structure[data-structure-key]'));
        const paragraphRows = () => structureRows().filter((row) => row.querySelector('input[type="submit"][value="+ Regel"], button[type="submit"]'));
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
        const labelFor = (row) => row.querySelector('td:nth-child(2)')?.textContent?.trim() || (row.dataset.blockType === 'recipe' ? 'Recept' : 'Calculatieregel');
        const paragraphLabel = (key) => structureRows().find((row) => row.dataset.structureKey === key)?.querySelector('td:nth-child(2)')?.textContent?.trim() || 'paragraaf';
        const announce = (message) => {
          liveRegion.textContent = '';
          window.setTimeout(() => { liveRegion.textContent = message; }, 0);
        };

        const orderPayload = () => ({
          __workspace__: primaryRows().map((row) => ({
            type: row.dataset.blockType,
            id: Number(row.dataset.lineId || row.dataset.recipeInstanceId),
            paragraph: row.dataset.structureKey || '',
          })),
        });

        const persist = () => {
          payloadInput.value = JSON.stringify(orderPayload());
          saveButton.click();
        };

        const siblingPrimaryRows = (row) => primaryRows().filter((candidate) => candidate.dataset.structureKey === row.dataset.structureKey);
        const blocksForParagraph = (key) => primaryRows().filter((candidate) => candidate.dataset.structureKey === key);
        const paragraphKeys = () => paragraphRows().map((row) => row.dataset.structureKey).filter(Boolean);

        const moveGroupBefore = (row, anchor, targetParagraph) => {
          group(row).forEach((node) => {
            node.dataset.structureKey = targetParagraph;
            row.parentNode.insertBefore(node, anchor);
          });
        };

        const moveToParagraphEnd = (row, targetParagraph) => {
          const targets = blocksForParagraph(targetParagraph).filter((candidate) => candidate !== row);
          const anchor = targets.length ? group(targets[targets.length - 1]).slice(-1)[0].nextSibling : structureRows().find((candidate) => candidate.dataset.structureKey === targetParagraph)?.nextSibling;
          moveGroupBefore(row, anchor, targetParagraph);
        };

        const moveWithKeyboard = (row, direction, crossParagraph) => {
          if (crossParagraph) {
            const keys = paragraphKeys();
            const currentIndex = keys.indexOf(row.dataset.structureKey);
            const targetIndex = currentIndex + direction;
            if (currentIndex < 0 || targetIndex < 0 || targetIndex >= keys.length) {
              announce(direction < 0 ? 'Er is geen vorige paragraaf.' : 'Er is geen volgende paragraaf.');
              return;
            }
            const targetParagraph = keys[targetIndex];
            moveToParagraphEnd(row, targetParagraph);
            persist();
            announce(`${labelFor(row)} verplaatst naar ${paragraphLabel(targetParagraph)}.`);
            row.querySelector('.brebo-calc-block-handle')?.focus();
            return;
          }

          const siblings = siblingPrimaryRows(row);
          const currentIndex = siblings.indexOf(row);
          const targetIndex = currentIndex + direction;
          if (currentIndex < 0 || targetIndex < 0 || targetIndex >= siblings.length) {
            announce(direction < 0 ? 'Dit blok staat al bovenaan de paragraaf.' : 'Dit blok staat al onderaan de paragraaf.');
            return;
          }
          const target = siblings[targetIndex];
          const targetGroup = group(target);
          const anchor = direction < 0 ? target : targetGroup[targetGroup.length - 1].nextSibling;
          moveGroupBefore(row, anchor, row.dataset.structureKey);
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
            handle.setAttribute('aria-label', `${labelFor(row)} verplaatsen. Alt+pijl verplaatst binnen de paragraaf; Alt+Shift+pijl verplaatst naar vorige of volgende paragraaf.`);
            handle.setAttribute('title', 'Sleep om te verplaatsen · Alt + ↑/↓ · Alt + Shift + ↑/↓ tussen paragrafen');
            handle.textContent = '⋮⋮';
            firstCell.prepend(handle);

            handle.addEventListener('keydown', (event) => {
              if (!event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return;
              event.preventDefault();
              moveWithKeyboard(row, event.key === 'ArrowUp' ? -1 : 1, event.shiftKey);
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
            if (!dragged || dragged === row) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            row.classList.add('is-drag-target');
          });
          row.addEventListener('dragleave', () => row.classList.remove('is-drag-target'));
          row.addEventListener('drop', (event) => {
            row.classList.remove('is-drag-target');
            if (!dragged || dragged === row) return;
            event.preventDefault();
            const targetParagraph = row.dataset.structureKey;
            const targetGroup = group(row);
            const targetRect = row.getBoundingClientRect();
            const insertAfter = event.clientY > targetRect.top + (targetRect.height / 2);
            const anchor = insertAfter ? targetGroup[targetGroup.length - 1].nextSibling : row;
            const movedLabel = labelFor(dragged);
            moveGroupBefore(dragged, anchor, targetParagraph);
            persist();
            announce(`${movedLabel} verplaatst naar ${paragraphLabel(targetParagraph)}.`);
          });
        });

        paragraphRows().forEach((paragraphRow) => {
          paragraphRow.addEventListener('dragover', (event) => {
            if (!dragged || dragged.dataset.structureKey === paragraphRow.dataset.structureKey) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            paragraphRow.classList.add('is-drag-target');
          });
          paragraphRow.addEventListener('dragleave', () => paragraphRow.classList.remove('is-drag-target'));
          paragraphRow.addEventListener('drop', (event) => {
            paragraphRow.classList.remove('is-drag-target');
            if (!dragged) return;
            event.preventDefault();
            const targetParagraph = paragraphRow.dataset.structureKey;
            const movedLabel = labelFor(dragged);
            moveToParagraphEnd(dragged, targetParagraph);
            persist();
            announce(`${movedLabel} verplaatst naar ${paragraphLabel(targetParagraph)}.`);
          });
        });
      });
    }
  };
})(Drupal, once);
