(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboCalculationClassificationMaster = {
    attach(context) {
      once('brebo-classification-master', '#brebo-calculation-structure-editor', context).forEach(async (editor) => {
        const codeInput = editor.querySelector('input[name="editor[create][main_group][code]"]');
        const labelInput = editor.querySelector('input[name="editor[create][main_group][label]"]');
        if (!codeInput || !labelInput) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'form-item brebo-classification-master';

        const label = document.createElement('label');
        label.textContent = 'NL/SfB hoofdcode';
        wrapper.appendChild(label);

        const select = document.createElement('select');
        select.className = 'form-select';
        select.innerHTML = '<option value="">Vrije invoer / geen NL/SfB-code</option>';
        wrapper.appendChild(select);

        const help = document.createElement('div');
        help.className = 'description';
        help.textContent = 'Kies een 2-cijferige NL/SfB-hoofdcode of gebruik de vrije velden Code en Omschrijving.';
        wrapper.appendChild(help);

        codeInput.closest('.form-item')?.before(wrapper);

        try {
          const response = await fetch('/brebo-office/api/classifications/nlsfb', {credentials: 'same-origin'});
          if (!response.ok) throw new Error('classification_api_failed');
          const data = await response.json();
          (data.items || [])
            .filter((item) => /^\d{2}$/.test(String(item.code || '')))
            .forEach((item) => {
              const option = document.createElement('option');
              option.value = String(item.code);
              option.textContent = `${item.code} — ${item.description}`;
              option.dataset.description = String(item.description || '');
              select.appendChild(option);
            });

          const current = String(codeInput.value || '').trim();
          if (/^\d{2}$/.test(current) && [...select.options].some((option) => option.value === current)) {
            select.value = current;
          }
        }
        catch (error) {
          help.textContent = 'NL/SfB-stamdata is nog niet beschikbaar; vrije invoer blijft volledig bruikbaar.';
        }

        select.addEventListener('change', () => {
          if (!select.value) return;
          const option = select.selectedOptions[0];
          codeInput.value = select.value;
          labelInput.value = option.dataset.description || '';
          codeInput.dispatchEvent(new Event('change', {bubbles: true}));
          labelInput.dispatchEvent(new Event('change', {bubbles: true}));
        });

        codeInput.addEventListener('input', () => {
          if (select.value && codeInput.value !== select.value) select.value = '';
        });
        labelInput.addEventListener('input', () => {
          const option = select.selectedOptions[0];
          if (select.value && option && labelInput.value !== option.dataset.description) select.value = '';
        });
      });
    }
  };
})(Drupal, once);
