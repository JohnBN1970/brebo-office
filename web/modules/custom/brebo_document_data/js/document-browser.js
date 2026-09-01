(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboDocumentBrowser = {
    attach(context) {
      once('brebo-document-browser', '.brebo-document-browser', context).forEach((browser) => {
        const contextKey = browser.dataset.preferenceKey || 'default';
        const storageKey = `breboDocumentView:${contextKey}`;
        const allowedViews = ['list', 'details', 'tiles', 'large-tiles'];
        let view = browser.dataset.view || 'list';
        try {
          const stored = window.localStorage.getItem(storageKey);
          if (stored && allowedViews.includes(stored)) view = stored;
        } catch (e) {}

        const applyView = (nextView) => {
          if (!allowedViews.includes(nextView)) return;
          browser.dataset.view = nextView;
          browser.querySelectorAll('[data-document-view]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.documentView === nextView ? 'true' : 'false');
          });
          try { window.localStorage.setItem(storageKey, nextView); } catch (e) {}
        };
        applyView(view);

        browser.querySelectorAll('[data-document-view]').forEach((button) => {
          button.addEventListener('click', () => applyView(button.dataset.documentView));
        });

        const preview = browser.querySelector('.brebo-document-preview');
        const frame = preview ? preview.querySelector('iframe') : null;
        const title = preview ? preview.querySelector('[data-preview-title]') : null;
        const original = preview ? preview.querySelector('[data-preview-original]') : null;
        const detail = preview ? preview.querySelector('[data-preview-detail]') : null;
        const closePreview = () => {
          if (!preview) return;
          preview.classList.remove('is-open');
          preview.setAttribute('aria-hidden', 'true');
          if (frame) frame.src = 'about:blank';
          document.body.style.overflow = '';
        };
        const openPreview = (trigger) => {
          if (!preview || !frame) return;
          frame.src = trigger.dataset.previewUrl;
          if (title) title.textContent = trigger.dataset.previewTitle || 'Document';
          if (original) original.href = trigger.dataset.previewUrl;
          if (detail) detail.href = trigger.dataset.detailUrl;
          preview.classList.add('is-open');
          preview.setAttribute('aria-hidden', 'false');
          document.body.style.overflow = 'hidden';
          const close = preview.querySelector('[data-preview-close]');
          if (close) close.focus();
        };

        browser.querySelectorAll('[data-document-preview]').forEach((trigger) => {
          trigger.addEventListener('click', (event) => {
            if (event.target.closest('a')) return;
            event.preventDefault();
            openPreview(trigger);
          });
          trigger.addEventListener('keydown', (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a')) {
              event.preventDefault();
              openPreview(trigger);
            }
          });
        });
        if (preview) {
          preview.querySelectorAll('[data-preview-close]').forEach((element) => element.addEventListener('click', closePreview));
          document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && preview.classList.contains('is-open')) closePreview();
          });
        }
      });
    }
  };
})(Drupal, once);
