(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboFormPresentation = {
    attach(context) {
      once('brebo-form-presentation', 'form.node-form, form.node-edit-form', context).forEach((form) => {
        form.classList.add('brebo-form');

        const bundle = (form.getAttribute('class') || '').match(/node-([a-z0-9-]+)-(?:form|edit-form)/);
        if (bundle) form.classList.add('brebo-form--' + bundle[1]);

        form.querySelectorAll('details').forEach((details) => details.classList.add('brebo-form__group'));
        form.querySelectorAll('.form-item').forEach((item) => item.classList.add('brebo-form__item'));

        const actions = form.querySelector('.form-actions');
        if (actions) {
          actions.classList.add('brebo-form__actions');
          const submits = actions.querySelectorAll('input[type="submit"], button[type="submit"]');
          submits.forEach((button, index) => {
            if (index === 0) button.classList.add('brebo-form__primary-action');
            else button.classList.add('brebo-form__secondary-action');
          });
        }

        // Help users scan long forms without changing Drupal field behavior.
        form.querySelectorAll('.description').forEach((description) => description.classList.add('brebo-form__help'));
        form.querySelectorAll('[aria-invalid="true"], .error').forEach((element) => {
          const item = element.closest('.form-item');
          if (item) item.classList.add('brebo-form__item--error');
        });
      });
    }
  };
})(Drupal, once);
