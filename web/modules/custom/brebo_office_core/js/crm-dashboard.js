(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboCrmAnalyticsPeriod = {
    attach(context) {
      once('brebo-crm-analytics-period', '.brebo-crm-period select[name="analytics_period"]', context).forEach((select) => {
        select.addEventListener('change', () => {
          if (select.form) {
            select.form.submit();
          }
        });
      });
    },
  };
})(Drupal, once);
