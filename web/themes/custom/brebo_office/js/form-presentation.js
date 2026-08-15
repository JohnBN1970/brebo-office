(function (Drupal, once) {
  'use strict';

  const fieldContainer = (element) => element.closest('[data-drupal-selector$="-wrapper"], .field--widget-string-textfield, .field--widget-number, .form-item') || element.parentElement;

  const hideByName = (form, prefixes) => {
    prefixes.forEach((prefix) => {
      form.querySelectorAll('[name^="' + prefix + '"]').forEach((element) => {
        const container = fieldContainer(element);
        if (container) {
          container.hidden = true;
          container.classList.add('brebo-form__system-field');
        }
      });
    });
  };

  const lockByName = (form, prefixes, note) => {
    prefixes.forEach((prefix) => {
      form.querySelectorAll('input[name^="' + prefix + '"], textarea[name^="' + prefix + '"]').forEach((element) => {
        element.readOnly = true;
        element.setAttribute('aria-readonly', 'true');
        const container = fieldContainer(element);
        if (!container) return;
        container.classList.add('brebo-form__readonly');
        if (!container.querySelector('.brebo-form__readonly-note')) {
          const label = document.createElement('div');
          label.className = 'brebo-form__readonly-note';
          label.textContent = note;
          container.appendChild(label);
        }
      });
    });
  };

  Drupal.behaviors.breboFormPresentation = {
    attach(context) {
      once('brebo-form-presentation', 'form.node-form, form.node-edit-form', context).forEach((form) => {
        form.classList.add('brebo-form');

        const bundle = (form.getAttribute('class') || '').match(/node-([a-z0-9-]+)-(?:form|edit-form)/);
        if (bundle) form.classList.add('brebo-form--' + bundle[1]);

        form.querySelectorAll('details').forEach((details) => details.classList.add('brebo-form__group'));
        form.querySelectorAll('.form-item').forEach((item) => item.classList.add('brebo-form__item'));

        // BREBO Office is an operational application, not a Drupal administration
        // form. Generic CMS metadata is deliberately removed from normal workflows.
        hideByName(form, ['created[', 'uid[', 'langcode[', 'status[']);

        // Canonical identifiers may be entered/generated at creation but should not
        // casually drift afterwards. They remain visible as information on edit.
        if (form.classList.contains('node-edit-form')) {
          lockByName(form, [
            'field_brebo_building_code[',
            'field_brebo_project_code[',
            'field_brebo_package_code['
          ], 'Vast objectkenmerk · wijzigen via beheer/migratie, niet in de dagelijkse workflow.');
        }

        // Building coordinates are output from the dedicated location resolution
        // flow. Keep them inspectable but prevent manual coordinate drift.
        if (form.classList.contains('node-brebo-building-form') || form.classList.contains('node-brebo-building-edit-form')) {
          lockByName(form, ['field_brebo_latitude[', 'field_brebo_longitude['], 'Informatief · wordt beheerd via Locatie bepalen / BAG-PDOK.');
        }

        const actions = form.querySelector('.form-actions');
        if (actions) {
          actions.classList.add('brebo-form__actions');
          const submits = actions.querySelectorAll('input[type="submit"], button[type="submit"]');
          submits.forEach((button, index) => {
            if (index === 0) button.classList.add('brebo-form__primary-action');
            else button.classList.add('brebo-form__secondary-action');
          });
        }

        form.querySelectorAll('.description').forEach((description) => description.classList.add('brebo-form__help'));
        form.querySelectorAll('[aria-invalid="true"], .error').forEach((element) => {
          const item = element.closest('.form-item');
          if (item) item.classList.add('brebo-form__item--error');
        });
      });
    }
  };
})(Drupal, once);
