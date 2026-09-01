(function (Drupal, once) {
  'use strict';

  async function csrfToken() {
    const response = await fetch(Drupal.url('session/token'), { credentials: 'same-origin' });
    if (!response.ok) throw new Error('CSRF-token kon niet worden geladen.');
    return response.text();
  }

  function columnState(board) {
    const columns = Array.from(board.querySelectorAll('[data-kanban-status]'));
    return {
      columns: columns.map((column) => column.dataset.kanbanStatus),
      hidden: columns.filter((column) => column.dataset.kanbanHidden === 'true').map((column) => column.dataset.kanbanStatus),
    };
  }

  async function postJson(url, payload) {
    const token = await csrfToken();
    const separator = url.includes('?') ? '&' : '?';
    const response = await fetch(`${url}${separator}token=${encodeURIComponent(token)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok === false) throw new Error(result.message || 'Opslaan mislukt.');
    return result;
  }

  function updateCount(column) {
    const count = column.querySelector('.brebo-building-kanban__count');
    if (count) count.textContent = String(column.querySelectorAll('.brebo-building-kanban__card').length);
  }

  Drupal.behaviors.breboBuildingKanban = {
    attach(context) {
      once('brebo-building-kanban', '[data-building-kanban]', context).forEach((board) => {
        const moveUrl = board.dataset.moveUrl;
        const configUrl = board.dataset.configUrl;
        const help = board.parentElement?.querySelector('.brebo-building-kanban__config-help') || document.querySelector('.brebo-building-kanban__config-help');
        const configure = document.querySelector('[data-kanban-configure]');
        let draggedCard = null;
        let draggedColumn = null;
        let configureMode = false;

        board.querySelectorAll('.brebo-building-kanban__card').forEach((card) => {
          card.addEventListener('dragstart', (event) => {
            if (configureMode) {
              event.preventDefault();
              return;
            }
            draggedCard = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
          });
          card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            draggedCard = null;
          });
        });

        board.querySelectorAll('[data-kanban-dropzone]').forEach((zone) => {
          zone.addEventListener('dragover', (event) => {
            if (!draggedCard || configureMode) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            zone.classList.add('is-drop-target');
          });
          zone.addEventListener('dragleave', () => zone.classList.remove('is-drop-target'));
          zone.addEventListener('drop', async (event) => {
            if (!draggedCard || configureMode) return;
            event.preventDefault();
            zone.classList.remove('is-drop-target');
            const sourceColumn = draggedCard.closest('[data-kanban-status]');
            const targetColumn = zone.closest('[data-kanban-status]');
            const status = targetColumn?.dataset.kanbanStatus;
            const buildingId = Number(draggedCard.dataset.buildingId || 0);
            if (!sourceColumn || !targetColumn || !status || buildingId <= 0 || sourceColumn === targetColumn) return;

            zone.prepend(draggedCard);
            updateCount(sourceColumn);
            updateCount(targetColumn);
            try {
              await postJson(moveUrl, { building_id: buildingId, status });
              draggedCard.dataset.buildingStatus = status;
            }
            catch (error) {
              sourceColumn.querySelector('[data-kanban-dropzone]')?.prepend(draggedCard);
              updateCount(sourceColumn);
              updateCount(targetColumn);
              window.alert(error.message);
            }
          });
        });

        board.querySelectorAll('[data-kanban-toggle-column]').forEach((button) => {
          button.addEventListener('click', async () => {
            if (!configureMode) return;
            const column = button.closest('[data-kanban-status]');
            if (!column) return;
            const hidden = column.dataset.kanbanHidden !== 'true';
            column.dataset.kanbanHidden = hidden ? 'true' : 'false';
            column.classList.toggle('is-kanban-column-hidden', hidden);
            button.textContent = hidden ? Drupal.t('Tonen') : Drupal.t('Verbergen');
            try { await postJson(configUrl, columnState(board)); }
            catch (error) { window.alert(error.message); }
          });
        });

        board.querySelectorAll('.brebo-building-kanban__column-handle').forEach((handle) => {
          const column = handle.closest('[data-kanban-status]');
          if (!column) return;
          handle.setAttribute('draggable', 'true');
          handle.addEventListener('dragstart', (event) => {
            if (!configureMode) {
              event.preventDefault();
              return;
            }
            draggedColumn = column;
            column.classList.add('is-column-dragging');
            event.dataTransfer.effectAllowed = 'move';
          });
          handle.addEventListener('dragend', () => {
            column.classList.remove('is-column-dragging');
            draggedColumn = null;
          });
        });

        board.querySelectorAll('[data-kanban-status]').forEach((column) => {
          column.addEventListener('dragover', (event) => {
            if (!configureMode || !draggedColumn || draggedColumn === column) return;
            event.preventDefault();
            const rect = column.getBoundingClientRect();
            if (event.clientX < rect.left + rect.width / 2) board.insertBefore(draggedColumn, column);
            else board.insertBefore(draggedColumn, column.nextSibling);
          });
          column.addEventListener('drop', async () => {
            if (!configureMode || !draggedColumn) return;
            try { await postJson(configUrl, columnState(board)); }
            catch (error) { window.alert(error.message); }
          });
        });

        configure?.addEventListener('click', () => {
          configureMode = !configureMode;
          board.classList.toggle('is-configuring', configureMode);
          configure.classList.toggle('is-active', configureMode);
          configure.textContent = configureMode ? Drupal.t('Indeling gereed') : Drupal.t('Kolommen indelen');
          if (help) help.hidden = !configureMode;
        });
      });
    },
  };
})(Drupal, once);
