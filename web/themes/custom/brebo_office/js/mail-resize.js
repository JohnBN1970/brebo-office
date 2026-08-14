(() => {
  'use strict';

  const KEY = 'brebo-mail-column-widths-v1';
  const DEFAULTS = {folders: 220, messages: 470};
  const LIMITS = {
    folders: [160, 360],
    messages: [300, 760],
  };

  function clamp(value, [min, max]) {
    return Math.min(max, Math.max(min, value));
  }

  function load() {
    try {
      return {...DEFAULTS, ...JSON.parse(localStorage.getItem(KEY) || '{}')};
    }
    catch (e) {
      return {...DEFAULTS};
    }
  }

  function save(widths) {
    try { localStorage.setItem(KEY, JSON.stringify(widths)); }
    catch (e) {}
  }

  function init() {
    const layout = document.querySelector('.brebo-mail-layout');
    if (!layout || layout.dataset.resizable === '1' || window.matchMedia('(max-width: 1100px)').matches) return;
    const folders = layout.querySelector('.brebo-mail-folders');
    const messages = layout.querySelector('.brebo-mail-list');
    const reader = layout.querySelector('.brebo-mail-reader');
    if (!folders || !messages || !reader) return;
    layout.dataset.resizable = '1';

    const widths = load();
    widths.folders = clamp(Number(widths.folders) || DEFAULTS.folders, LIMITS.folders);
    widths.messages = clamp(Number(widths.messages) || DEFAULTS.messages, LIMITS.messages);

    const apply = () => {
      layout.style.setProperty('--brebo-mail-folders-width', widths.folders + 'px');
      layout.style.setProperty('--brebo-mail-messages-width', widths.messages + 'px');
    };
    apply();

    function makeHandle(kind, before, after) {
      const handle = document.createElement('div');
      handle.className = 'brebo-mail-resizer brebo-mail-resizer--' + kind;
      handle.setAttribute('role', 'separator');
      handle.setAttribute('aria-orientation', 'vertical');
      handle.setAttribute('tabindex', '0');
      handle.title = 'Sleep om kolombreedte aan te passen. Dubbelklik voor standaard.';
      before.after(handle);

      const widthKey = kind === 'folders' ? 'folders' : 'messages';
      handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        handle.setPointerCapture(event.pointerId);
        document.body.classList.add('brebo-mail-is-resizing');
        const startX = event.clientX;
        const startWidth = widths[widthKey];

        const move = (moveEvent) => {
          widths[widthKey] = clamp(startWidth + moveEvent.clientX - startX, LIMITS[widthKey]);
          apply();
        };
        const end = () => {
          document.body.classList.remove('brebo-mail-is-resizing');
          save(widths);
          handle.removeEventListener('pointermove', move);
          handle.removeEventListener('pointerup', end);
          handle.removeEventListener('pointercancel', end);
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
      });

      handle.addEventListener('dblclick', () => {
        widths[widthKey] = DEFAULTS[widthKey];
        apply();
        save(widths);
      });

      handle.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        widths[widthKey] = clamp(widths[widthKey] + (event.key === 'ArrowRight' ? 20 : -20), LIMITS[widthKey]);
        apply();
        save(widths);
      });

      return handle;
    }

    makeHandle('folders', folders, messages);
    makeHandle('messages', messages, reader);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
})();
