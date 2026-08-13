(function (Drupal, once) {
  'use strict';

  function money(value) {
    return new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR'}).format(Number(value || 0));
  }

  Drupal.behaviors.breboArticlePicker = {
    attach: function (context) {
      once('brebo-article-picker', '[data-brebo-article-picker]', context).forEach(function (button) {
        button.addEventListener('click', function () {
          var row = button.closest('.brebo-calc-ingredient-row');
          if (!row) return;

          var dialog = document.createElement('dialog');
          dialog.className = 'brebo-article-picker';
          dialog.innerHTML =
            '<form method="dialog" class="brebo-article-picker__shell">' +
              '<header><div><small>BREBO Artikelbeheer</small><h2>Artikel of kostendrager zoeken</h2></div><button value="cancel" aria-label="Sluiten">×</button></header>' +
              '<div class="brebo-article-picker__filters">' +
                '<input type="search" name="q" placeholder="Omschrijving, artikelnummer, GTIN, productgroep of NLSfB" autofocus>' +
                '<select name="category"><option value="">Alle kostensoorten</option><option>Materiaal</option><option>Arbeid</option><option>Materieel</option><option>Onderaanneming</option><option>Overig</option></select>' +
                '<input type="search" name="supplier" placeholder="Leverancier">' +
              '</div>' +
              '<div class="brebo-article-picker__status">Begin met typen om de centrale artikelstam te doorzoeken.</div>' +
              '<div class="brebo-article-picker__results"></div>' +
            '</form>';
          document.body.appendChild(dialog);

          var q = dialog.querySelector('[name="q"]');
          var category = dialog.querySelector('[name="category"]');
          var supplier = dialog.querySelector('[name="supplier"]');
          var status = dialog.querySelector('.brebo-article-picker__status');
          var results = dialog.querySelector('.brebo-article-picker__results');
          var timer;

          function choose(item) {
            var set = function (key, value) {
              var input = row.querySelector('[name$="[' + key + ']"]');
              if (input) {
                input.value = value === null || value === undefined ? '' : value;
                input.dispatchEvent(new Event('input', {bubbles: true}));
                input.dispatchEvent(new Event('change', {bubbles: true}));
              }
            };
            set('description', item.description);
            set('category', item.cost_category || 'Materiaal');
            set('unit', item.unit);
            set('unit_price', item.net_price);
            set('article_id', item.article_id);
            set('supplier_article_id', item.supplier_article_id);
            set('price_id', item.price_id);
            set('catalog_import_id', item.catalog_import_id);
            set('article_code', item.code);
            set('supplier_name', item.supplier);
            set('supplier_article_no', item.supplier_article_no);
            set('price_date', item.price_date);
            button.textContent = item.code + ' · ' + item.supplier;
            button.classList.add('has-article');
            dialog.close();

            // Trigger the existing live calculation once more after every
            // article field has been populated, so row and recipe totals use
            // one complete and consistent set of values.
            window.requestAnimationFrame(function () {
              var price = row.querySelector('[name$="[unit_price]"]');
              if (price) {
                price.dispatchEvent(new Event('input', {bubbles: true}));
                price.dispatchEvent(new Event('change', {bubbles: true}));
              }
            });
          }

          function search() {
            var term = q.value.trim();
            if (!term && !category.value && !supplier.value.trim()) {
              status.textContent = 'Begin met typen om de centrale artikelstam te doorzoeken.';
              results.innerHTML = '';
              return;
            }
            status.textContent = 'Zoeken…';
            var url = new URL('/api/artikelen', window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('category', category.value);
            url.searchParams.set('supplier', supplier.value.trim());
            fetch(url, {headers: {'Accept': 'application/json'}})
              .then(function (response) {
                if (!response.ok) throw new Error('Zoeken mislukt');
                return response.json();
              })
              .then(function (data) {
                status.textContent = data.count + ' resultaten';
                results.innerHTML = '';
                data.items.forEach(function (item) {
                  var article = document.createElement('article');
                  article.innerHTML =
                    '<div><small>' + (item.code || '—') + ' · ' + (item.product_group || 'Geen productgroep') + '</small>' +
                    '<strong>' + item.description + '</strong>' +
                    '<span>' + item.supplier + ' · art. ' + item.supplier_article_no + '</span></div>' +
                    '<div><small>Eenheid</small><strong>' + item.unit + '</strong></div>' +
                    '<div><small>Netto</small><strong>' + money(item.net_price) + '</strong><span>' + item.price_date + '</span></div>' +
                    '<button type="button">Kiezen</button>';
                  article.querySelector('button').addEventListener('click', function () { choose(item); });
                  results.appendChild(article);
                });
              })
              .catch(function () {
                status.textContent = 'De artikelstam kon niet worden doorzocht.';
              });
          }

          [q, category, supplier].forEach(function (input) {
            input.addEventListener('input', function () {
              window.clearTimeout(timer);
              timer = window.setTimeout(search, 220);
            });
            input.addEventListener('change', search);
          });
          dialog.addEventListener('close', function () { dialog.remove(); });
          dialog.showModal();
        });
      });
    }
  };
})(Drupal, once);
