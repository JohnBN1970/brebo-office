(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboGuidedTour = {
    attach(context, settings) {
      const config = settings.breboGuidedTour;
      if (!config || !Array.isArray(config.steps) || config.steps.length === 0) {
        return;
      }

      once('brebo-guided-tour', 'body', context).forEach(() => {
        let index = Math.max(0, Number(config.currentStep || 0));
        const steps = config.steps;
        const root = document.createElement('div');
        root.className = 'brebo-tour';
        root.innerHTML = '<div class="brebo-tour__backdrop"></div><div class="brebo-tour__bubble" role="dialog" aria-live="polite"><button class="brebo-tour__close" type="button" aria-label="Rondleiding sluiten">×</button><div class="brebo-tour__counter"></div><h2 class="brebo-tour__title"></h2><div class="brebo-tour__text"></div><div class="brebo-tour__actions"><button type="button" class="button brebo-tour__previous">Vorige</button><button type="button" class="button brebo-tour__skip">Later</button><button type="button" class="button button--primary brebo-tour__next">Volgende</button></div></div>';
        document.body.appendChild(root);

        const bubble = root.querySelector('.brebo-tour__bubble');
        const title = root.querySelector('.brebo-tour__title');
        const text = root.querySelector('.brebo-tour__text');
        const counter = root.querySelector('.brebo-tour__counter');
        const previous = root.querySelector('.brebo-tour__previous');
        const next = root.querySelector('.brebo-tour__next');
        const skip = root.querySelector('.brebo-tour__skip');
        const close = root.querySelector('.brebo-tour__close');
        let highlighted = null;

        const notify = (action) => {
          if (!config.progressUrl) return;
          fetch(config.progressUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin',
            body: JSON.stringify({tour_id: config.tourId, action, step: index}),
          }).catch(() => {});
        };

        const position = (target) => {
          const margin = 16;
          if (!target) {
            bubble.style.position = 'fixed';
            bubble.style.left = '50%';
            bubble.style.top = '50%';
            bubble.style.transform = 'translate(-50%, -50%)';
            return;
          }
          const rect = target.getBoundingClientRect();
          bubble.style.position = 'fixed';
          bubble.style.transform = 'none';
          const width = Math.min(380, window.innerWidth - 32);
          bubble.style.width = width + 'px';
          let left = Math.min(Math.max(margin, rect.left), window.innerWidth - width - margin);
          let top = rect.bottom + 12;
          if (top + 260 > window.innerHeight) top = Math.max(margin, rect.top - 260);
          bubble.style.left = left + 'px';
          bubble.style.top = top + 'px';
        };

        const render = () => {
          if (highlighted) highlighted.classList.remove('brebo-tour-target');
          const step = steps[index];
          if (!step) return;
          const target = step.selector ? document.querySelector(step.selector) : null;
          if (target) {
            target.classList.add('brebo-tour-target');
            highlighted = target;
            target.scrollIntoView({behavior: 'smooth', block: 'center'});
          }
          else {
            highlighted = null;
          }
          title.textContent = step.title || '';
          text.textContent = step.text || '';
          counter.textContent = (index + 1) + ' / ' + steps.length;
          previous.disabled = index === 0;
          next.textContent = index === steps.length - 1 ? 'Afronden' : 'Volgende';
          window.setTimeout(() => position(target), 150);
          notify('advance');
        };

        const finish = (action) => {
          if (highlighted) highlighted.classList.remove('brebo-tour-target');
          notify(action);
          root.remove();
        };

        previous.addEventListener('click', () => { if (index > 0) { index -= 1; render(); } });
        next.addEventListener('click', () => {
          if (index >= steps.length - 1) finish('complete');
          else { index += 1; render(); }
        });
        skip.addEventListener('click', () => finish('skip'));
        close.addEventListener('click', () => finish('skip'));
        window.addEventListener('resize', () => render(), {passive: true});
        notify('start');
        render();
      });
    }
  };
})(Drupal, once);
