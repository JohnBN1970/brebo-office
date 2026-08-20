(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboCalculationAssignmentLink = {
    attach(context) {
      once('brebo-calculation-assignment-link', '#brebo-calculation-workbench .brebo-calc-workbench__navigation', context).forEach((navigation) => {
        const workbench = navigation.closest('#brebo-calculation-workbench');
        const form = workbench ? workbench.closest('form') : null;
        const calculationInput = form ? form.querySelector('input[name="calculation_id"]') : null;
        const calculationId = calculationInput ? Number(calculationInput.value) : 0;
        if (!Number.isInteger(calculationId) || calculationId <= 0) {
          return;
        }
        const link = document.createElement('a');
        link.className = 'button';
        link.href = `/admin/brebo/calculations/${encodeURIComponent(calculationId)}/workbench/assignment`;
        link.textContent = Drupal.t('Toewijzen');
        navigation.appendChild(link);
      });
    }
  };
})(Drupal, once);
