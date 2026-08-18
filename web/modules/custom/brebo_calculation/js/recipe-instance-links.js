(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboRecipeInstanceLinks = {
    attach(context) {
      once('brebo-recipe-instance-link', '[data-recipe-instance-id][data-block-type="recipe"]', context).forEach((row) => {
        const instanceId = row.dataset.recipeInstanceId;
        const match = window.location.pathname.match(/\/admin\/brebo\/calculations\/(\d+)\/workbench/);
        if (!instanceId || !match) return;

        const operationsCell = row.lastElementChild;
        if (!operationsCell) return;

        const link = document.createElement('a');
        link.className = 'button button--small brebo-recipe-edit-link';
        link.href = `/admin/brebo/calculations/${match[1]}/workbench/recipes/${instanceId}`;
        link.textContent = 'Bewerken';
        link.setAttribute('aria-label', 'Recept bewerken');

        operationsCell.appendChild(document.createTextNode(' '));
        operationsCell.appendChild(link);
      });
    }
  };
})(Drupal, once);
