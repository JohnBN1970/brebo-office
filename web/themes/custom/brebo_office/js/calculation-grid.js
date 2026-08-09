(function (Drupal) {
  'use strict';

  Drupal.behaviors.breboCalculationGrid = {
    attach: function (context) {
      context.querySelectorAll('.brebo-calc-grid-form:not([data-live-grid-ready])').forEach(function (form) {
        form.setAttribute('data-live-grid-ready', 'true');
        var table = form.querySelector('.brebo-calc-recipe-grid');
        if (!table) return;

        var calculationInput = form.querySelector('input[name="calculation_id"]');
        var calculationId = calculationInput ? calculationInput.value : 'unknown';
        var storagePrefix = 'brebo-calc-grid-' + calculationId + '-';
        var saveButton = form.querySelector('.brebo-calc-grid__actions input[type="submit"], .brebo-calc-grid__actions button[type="submit"]');
        var saveLabel = saveButton ? (saveButton.value || saveButton.textContent) : '';

        function parseNumber(value) {
          var normalized = String(value === null || value === undefined ? '' : value)
            .trim()
            .replace(/\s/g, '')
            .replace(',', '.');
          var number = Number(normalized);
          return Number.isFinite(number) ? number : 0;
        }

        function formatNumber(value, digits) {
          return new Intl.NumberFormat('nl-NL', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
          }).format(value || 0);
        }

        function formatMoney(value) {
          return '€ ' + formatNumber(value, 2);
        }

        function field(row, key) {
          return row.querySelector('[name$="[' + key + ']"]');
        }

        function markDirty() {
          form.classList.add('is-dirty');
          if (!saveButton) return;
          if ('value' in saveButton) {
            saveButton.value = saveLabel + ' • niet opgeslagen';
          }
          else {
            saveButton.textContent = saveLabel + ' • niet opgeslagen';
          }
        }

        function setStatus(row) {
          var select = field(row, 'status');
          if (!select) return;
          var status = select.value.toLowerCase().replace(/\./g, '').replace(/\s+/g, '-');
          row.setAttribute('data-line-status', status);
        }

        function recalculateRow(row) {
          var quantityInput = field(row, 'quantity');
          var actualInput = field(row, 'actual');
          var priceInput = field(row, 'unit_price');
          var postTypeInput = field(row, 'post_type');
          var categoryInput = field(row, 'category');
          var modeInput = field(row, 'hours_mode');
          var normInput = field(row, 'norm_hours');
          var totalHoursInput = field(row, 'budget_hours');
          var laborRateInput = field(row, 'labor_rate');
          if (!quantityInput || !priceInput) return;

          var quantity = parseNumber(quantityInput.value);
          var actualRaw = actualInput ? actualInput.value.trim() : '';
          var actual = parseNumber(actualRaw);
          var mode = modeInput ? modeInput.value : 'Normuren';
          var norm = normInput ? parseNumber(normInput.value) : 0;
          var totalHours = totalHoursInput ? parseNumber(totalHoursInput.value) : 0;
          var laborRate = laborRateInput ? parseNumber(laborRateInput.value) : 0;

          if (mode === 'Totaaluren') {
            norm = quantity > 0 ? totalHours / quantity : 0;
            if (normInput && document.activeElement !== normInput) {
              normInput.value = norm ? norm.toFixed(4) : '';
            }
          }
          else {
            totalHours = quantity * norm;
            if (totalHoursInput && document.activeElement !== totalHoursInput) {
              totalHoursInput.value = totalHours ? totalHours.toFixed(4) : '0.0000';
            }
          }

          if (categoryInput && categoryInput.value === 'Arbeid' && laborRate > 0 && norm > 0) {
            var laborUnitPrice = norm * laborRate;
            if (document.activeElement !== priceInput) {
              priceInput.value = laborUnitPrice.toFixed(4);
            }
          }

          var price = parseNumber(priceInput.value);
          var forecastQuantity = postTypeInput && postTypeInput.value === 'Verrekenpost' && actualRaw !== ''
            ? actual
            : quantity;
          var contract = quantity * price;
          var forecast = forecastQuantity * price;
          var contractOutput = row.querySelector('[data-live-contract]');
          var forecastOutput = row.querySelector('[data-live-forecast]');
          if (contractOutput) {
            contractOutput.textContent = formatMoney(contract);
            contractOutput.dataset.value = String(contract);
          }
          if (forecastOutput) {
            forecastOutput.textContent = formatMoney(forecast);
            forecastOutput.dataset.value = String(forecast);
          }
          row.dataset.liveHours = String(totalHours);
          setStatus(row);
        }

        function recipeRows(recipeId) {
          return Array.from(table.querySelectorAll('tr[data-recipe-id="' + recipeId + '"]'));
        }

        function recalculateRecipe(recipeId) {
          var header = table.querySelector('tr[data-recipe-header="' + recipeId + '"]');
          if (!header) return;
          var contract = 0;
          var hours = 0;
          recipeRows(recipeId).forEach(function (row) {
            recalculateRow(row);
            contract += parseNumber((row.querySelector('[data-live-contract]') || {}).dataset && row.querySelector('[data-live-contract]').dataset.value);
            hours += parseNumber(row.dataset.liveHours);
          });
          var quantityInput = header.querySelector('[name$="[heading][quantity]"]');
          var quantity = quantityInput ? parseNumber(quantityInput.value) : 0;
          var hoursOutput = header.querySelector('[data-recipe-hours]');
          var priceOutput = header.querySelector('[data-recipe-price]');
          var totalOutput = header.querySelector('[data-recipe-total]');
          if (hoursOutput) hoursOutput.textContent = formatNumber(hours, 2);
          if (priceOutput) priceOutput.textContent = formatMoney(quantity > 0 ? contract / quantity : 0);
          if (totalOutput) totalOutput.textContent = formatMoney(contract);
        }

        function allRecipeIds() {
          return Array.from(table.querySelectorAll('tr[data-recipe-header]')).map(function (row) {
            return row.getAttribute('data-recipe-header');
          });
        }

        function boundaryAfterRecipe(recipeId) {
          var header = table.querySelector('tr[data-recipe-header="' + recipeId + '"]');
          if (!header) return null;
          var current = header.nextElementSibling;
          while (current && !current.hasAttribute('data-recipe-header')) {
            current = current.nextElementSibling;
          }
          return current;
        }

        function sortRecipe(recipeId) {
          var boundary = boundaryAfterRecipe(recipeId);
          recipeRows(recipeId)
            .sort(function (left, right) {
              return parseNumber(field(left, 'sequence') && field(left, 'sequence').value)
                - parseNumber(field(right, 'sequence') && field(right, 'sequence').value);
            })
            .forEach(function (row) {
              table.tBodies[0].insertBefore(row, boundary);
            });
        }

        function moveRow(row, recipeId) {
          row.setAttribute('data-recipe-id', recipeId);
          var header = table.querySelector('tr[data-recipe-header="' + recipeId + '"]');
          if (!header) return;
          var bandClass = Array.from(header.classList).find(function (name) {
            return name.indexOf('brebo-calc-row--nlsfb-') === 0;
          });
          Array.from(row.classList).filter(function (name) {
            return name.indexOf('brebo-calc-row--nlsfb-') === 0;
          }).forEach(function (name) {
            row.classList.remove(name);
          });
          if (bandClass) row.classList.add(bandClass);
          table.tBodies[0].insertBefore(row, boundaryAfterRecipe(recipeId));
          sortRecipe(recipeId);
          applyCollapsedState(recipeId);
        }

        function collapseKey(recipeId) {
          return storagePrefix + 'collapsed-' + recipeId;
        }

        function applyCollapsedState(recipeId) {
          var collapsed = window.localStorage.getItem(collapseKey(recipeId)) === '1';
          recipeRows(recipeId).forEach(function (row) {
            row.hidden = collapsed;
          });
          var button = table.querySelector('[data-recipe-toggle="' + recipeId + '"]');
          if (button) {
            button.textContent = collapsed ? '▸' : '▾';
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
          }
        }

        table.querySelectorAll('[data-recipe-toggle]').forEach(function (button) {
          var recipeId = button.getAttribute('data-recipe-toggle');
          button.addEventListener('click', function () {
            var collapsed = button.getAttribute('aria-expanded') === 'true';
            window.localStorage.setItem(collapseKey(recipeId), collapsed ? '1' : '0');
            applyCollapsedState(recipeId);
          });
          applyCollapsedState(recipeId);
        });

        table.querySelectorAll('.brebo-calc-ingredient-row').forEach(function (row) {
          recalculateRow(row);
          setStatus(row);
          var recipeSelect = field(row, 'recipe');
          var sequenceInput = field(row, 'sequence');
          if (recipeSelect) {
            recipeSelect.addEventListener('change', function () {
              var oldRecipe = row.getAttribute('data-recipe-id');
              moveRow(row, recipeSelect.value);
              recalculateRecipe(oldRecipe);
              recalculateRecipe(recipeSelect.value);
              markDirty();
            });
          }
          if (sequenceInput) {
            sequenceInput.addEventListener('change', function () {
              sortRecipe(row.getAttribute('data-recipe-id'));
              markDirty();
            });
          }
        });

        form.addEventListener('input', function (event) {
          var row = event.target.closest('.brebo-calc-ingredient-row');
          if (row) {
            recalculateRow(row);
            recalculateRecipe(row.getAttribute('data-recipe-id'));
          }
          var header = event.target.closest('[data-recipe-heading]');
          if (header) {
            recalculateRecipe(header.getAttribute('data-recipe-heading'));
          }
          markDirty();
        });

        form.addEventListener('change', function (event) {
          var row = event.target.closest('.brebo-calc-ingredient-row');
          if (row) {
            recalculateRow(row);
            recalculateRecipe(row.getAttribute('data-recipe-id'));
          }
          markDirty();
        });

        var widthKey = storagePrefix + 'column-widths';
        var savedWidths = {};
        try {
          savedWidths = JSON.parse(window.localStorage.getItem(widthKey) || '{}');
        }
        catch (ignore) {}

        table.querySelectorAll('thead th').forEach(function (th, index) {
          if (savedWidths[index]) {
            th.style.width = savedWidths[index] + 'px';
            th.style.minWidth = savedWidths[index] + 'px';
          }
          var handle = document.createElement('span');
          handle.className = 'brebo-column-resizer';
          handle.setAttribute('aria-hidden', 'true');
          th.appendChild(handle);
          handle.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            var startX = event.clientX;
            var startWidth = th.getBoundingClientRect().width;
            handle.setPointerCapture(event.pointerId);

            function resize(moveEvent) {
              var width = Math.max(72, startWidth + moveEvent.clientX - startX);
              th.style.width = width + 'px';
              th.style.minWidth = width + 'px';
              savedWidths[index] = Math.round(width);
            }

            function stop() {
              handle.removeEventListener('pointermove', resize);
              handle.removeEventListener('pointerup', stop);
              handle.removeEventListener('pointercancel', stop);
              window.localStorage.setItem(widthKey, JSON.stringify(savedWidths));
            }

            handle.addEventListener('pointermove', resize);
            handle.addEventListener('pointerup', stop);
            handle.addEventListener('pointercancel', stop);
          });
        });

        allRecipeIds().forEach(recalculateRecipe);
      });
    }
  };
})(Drupal);
