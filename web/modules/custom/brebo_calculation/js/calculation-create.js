(function (Drupal, once) {
  'use strict';

  const fieldContainer = (element) => element.closest(
    '[data-drupal-selector$="-wrapper"], .field--widget-string-textfield, .field--widget-list-string, .field--widget-datetime-default, .form-item'
  ) || element.parentElement;

  const hideFieldByName = (form, prefix) => {
    form.querySelectorAll('[name^="' + prefix + '"]').forEach((element) => {
      const container = fieldContainer(element);
      if (container) {
        container.hidden = true;
        container.classList.add('brebo-calc-create__automatic-field');
      }
    });
  };

  Drupal.behaviors.breboCalculationCreate = {
    attach(context) {
      once(
        'brebo-calculation-create',
        'form.node-brebo-calculation-form, form.node-brebo-calculation-edit-form',
        context
      ).forEach((form) => {
        if (!form.querySelector('.brebo-calc-onboarding')) {
          return;
        }

        form.classList.add('brebo-calc-create-form');

        // These values are governed by BREBO Office and do not belong in the
        // calculator's operational start flow.
        hideFieldByName(form, 'field_brebo_calc_version[');
        hideFieldByName(form, 'field_brebo_calc_status[');
        hideFieldByName(form, 'field_brebo_package_ref[');

        // The assumptions field has one governed BREBO text format. Showing the
        // Drupal text-format selector only exposes implementation details.
        form.querySelectorAll('.filter-wrapper').forEach((wrapper) => {
          wrapper.hidden = true;
          wrapper.classList.add('brebo-calc-create__technical-control');
        });

        // Remove the remaining Claro/Drupal metadata card from this operational
        // start flow and let the main form use the available page width.
        form.querySelectorAll('.layout-region-node-secondary, .entity-meta').forEach((region) => {
          region.hidden = true;
          region.classList.add('brebo-calc-create__cms-meta');
        });
        form.querySelectorAll('.layout-region-node-main').forEach((region) => {
          region.classList.add('brebo-calc-create__main');
        });
        form.querySelectorAll('.layout-region-node-footer').forEach((region) => {
          region.classList.add('brebo-calc-create__footer');
        });

        const priceDate = form.querySelector('[name^="field_brebo_price_date["]');
        const priceDateContainer = priceDate ? fieldContainer(priceDate) : null;
        const priceDateDescription = priceDateContainer
          ? priceDateContainer.querySelector('.description')
          : null;
        if (priceDateDescription) {
          priceDateDescription.textContent = 'Datum waarop de gebruikte prijzen zijn opgehaald of ontvangen. Standaard vandaag; pas aan wanneer je met een andere prijsbasis werkt.';
        }
      });
    }
  };
})(Drupal, once);
