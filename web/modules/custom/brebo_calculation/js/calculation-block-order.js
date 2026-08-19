(function (Drupal, once) {
  'use strict';

  const topLevelSelector = 'tr[data-block-type="row"], tr[data-block-type="recipe"]';

  function blockIdentity(row) {
    if (row.dataset.blockType === 'row') {
      return {type: 'row', id: Number(row.dataset.lineId || 0)};
    }
    return {type: 'recipe', id: Number(row.dataset.recipeInstanceId || 0)};
  }

  function recipeGroup(row) {
    if (row.dataset.blockType !== 'recipe') return [row];
    const id = row.dataset.recipeInstanceId;
    const rows = [row];
    let current = row.nextElementSibling;
    while (current && current.dataset.blockType === 'recipe-line' && current.dataset.recipeInstanceId === id) {
      rows.push(current);
      current = current.nextElementSibling;
    }
    return rows;
  }

  function moveGroupBefore(source, target) {
    const group = recipeGroup(source);
    group.forEach((row) => target.parentNode.insertBefore(row, target));
  }

  function moveGroupAfter(source, target) {
    const targetGroup = recipeGroup(target);
    const reference = targetGroup[targetGroup.length - 1].nextElementSibling;
    const group = recipeGroup(source);
    group.forEach((row) => target.parentNode.insertBefore(row, reference));
  }

  function rowsForParagraph(grid, paragraphKey) {
    return Array.from(grid.querySelectorAll(topLevelSelector))
      .filter((row) => row.dataset.structureKey === paragraphKey);
  }

  function findStructureRow(grid, paragraphKey) {
    return Array.from(grid.querySelectorAll('tr.brebo-calc-workbench__structure'))
      .find((row) => row.dataset.structureKey === paragraphKey) || null;
  }

  function applyServerOrder(grid, paragraphs) {
    Object.entries(paragraphs || {}).forEach(([paragraphKey, order]) => {
      const structureRow = findStructureRow(grid, paragraphKey);
      if (!structureRow || !Array.isArray(order)) return;
      const rows = rowsForParagraph(grid, paragraphKey);
      const byKey = new Map(rows.map((row) => {
        const block = blockIdentity(row);
        return [`${block.type}:${block.id}`, row];
      }));
      let anchor = structureRow;
      order.forEach((block) => {
        const row = byKey.get(`${block.type}:${Number(block.id)}`);
        if (!row) return;
        recipeGroup(row).forEach((groupRow) => {
          anchor.after(groupRow);
          anchor = groupRow;
        });
      });
    });
  }

  Drupal.behaviors.breboCalculationBlockOrdering = {
    attach(context) {
      once('brebo-calculation-block-order', '#brebo-calculation-workbench', context).forEach((workbench) => {
        const grid = workbench.querySelector('.brebo-calc-workbench__grid');
        if (!grid || !grid.classList.contains('brebo-calc-workbench__grid--editable')) return;

        const match = window.location.pathname.match(/\/admin\/brebo\/calculations\/(\d+)\/workbench/);
        if (!match) return;
        const calculationId = match[1];
        const statusUrl = `/admin/brebo/calculations/${calculationId}/workbench/block-order`;
        const saveUrl = `/admin/brebo/calculations/${calculationId}/workbench/block-order/save`;
        let csrfToken = null;
        let dragged = null;
        let saving = false;

        const message = (text, error) => {
          let box = workbench.querySelector('.brebo-calc-block-order-message');
          if (!box) {
            box = document.createElement('div');
            box.className = 'brebo-calc-block-order-message';
            grid.parentNode.insertBefore(box, grid);
          }
          box.className = `brebo-calc-block-order-message messages ${error ? 'messages--error' : 'messages--status'}`;
          box.textContent = text;
          window.setTimeout(() => { if (box.textContent === text) box.textContent = ''; }, 2600);
        };

        const token = () => {
          if (csrfToken) return Promise.resolve(csrfToken);
          return fetch('/session/token', {credentials: 'same-origin'})
            .then((response) => {
              if (!response.ok) throw new Error('CSRF-token kon niet worden geladen.');
              return response.text();
            })
            .then((value) => { csrfToken = value; return value; });
        };

        const persist = (paragraphKey) => {
          if (saving) return;
          const blocks = rowsForParagraph(grid, paragraphKey).map(blockIdentity);
          saving = true;
          workbench.classList.add('is-order-saving');
          token()
            .then((csrf) => fetch(saveUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf,
              },
              body: JSON.stringify({paragraphKey, blocks}),
            }))
            .then((response) => {
              if (!response.ok) return response.text().then((text) => { throw new Error(text || 'Volgorde kon niet worden opgeslagen.'); });
              return response.json();
            })
            .then(() => message('Volgorde opgeslagen.', false))
            .catch(() => {
              message('Volgorde kon niet veilig worden opgeslagen. Werkbank wordt vernieuwd.', true);
              window.setTimeout(() => window.location.reload(), 900);
            })
            .finally(() => {
              saving = false;
              workbench.classList.remove('is-order-saving');
            });
        };

        const enableRows = () => {
          grid.querySelectorAll(topLevelSelector).forEach((row) => {
            if (row.querySelector('.brebo-calc-drag-handle')) return;
            row.draggable = true;
            row.classList.add('brebo-calc-sortable-block');
            const cell = row.cells[0];
            if (cell) {
              const handle = document.createElement('span');
              handle.className = 'brebo-calc-drag-handle';
              handle.title = 'Sleep om deze regel of dit recept binnen de paragraaf te verplaatsen';
              handle.setAttribute('aria-label', 'Slepen om volgorde te wijzigen');
              handle.textContent = '⋮⋮';
              cell.prepend(handle);
            }
          });
        };

        grid.addEventListener('dragstart', (event) => {
          const row = event.target.closest(topLevelSelector);
          if (!row || !event.target.closest('.brebo-calc-drag-handle')) {
            event.preventDefault();
            return;
          }
          dragged = row;
          row.classList.add('is-dragging');
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', `${row.dataset.blockType}:${blockIdentity(row).id}`);
        });

        grid.addEventListener('dragover', (event) => {
          if (!dragged) return;
          const target = event.target.closest(topLevelSelector);
          if (!target || target === dragged || target.dataset.structureKey !== dragged.dataset.structureKey) return;
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
          const rect = target.getBoundingClientRect();
          const after = event.clientY > rect.top + (rect.height / 2);
          if (after) moveGroupAfter(dragged, target);
          else moveGroupBefore(dragged, target);
        });

        grid.addEventListener('drop', (event) => {
          if (!dragged) return;
          event.preventDefault();
          const paragraphKey = dragged.dataset.structureKey;
          dragged.classList.remove('is-dragging');
          dragged = null;
          persist(paragraphKey);
        });

        grid.addEventListener('dragend', () => {
          if (dragged) dragged.classList.remove('is-dragging');
          dragged = null;
        });

        fetch(statusUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
          .then((response) => {
            if (!response.ok) throw new Error('Blokvolgorde kon niet worden geladen.');
            return response.json();
          })
          .then((data) => {
            applyServerOrder(grid, data.paragraphs || {});
            enableRows();
          })
          .catch(() => {
            enableRows();
            message('Bestaande blokvolgorde kon niet worden geladen; slepen is tijdelijk uitgeschakeld.', true);
            grid.querySelectorAll(topLevelSelector).forEach((row) => { row.draggable = false; });
          });
      });
    },
  };
})(Drupal, once);
