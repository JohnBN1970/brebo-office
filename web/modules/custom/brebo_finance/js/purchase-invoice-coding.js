(function (Drupal, once) {
  'use strict';

  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const money = value => new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR'}).format(Number(value || 0));

  Drupal.behaviors.breboPurchaseInvoiceCoding = {
    attach(context) {
      once('brebo-purchase-invoice-coding', '[data-brebo-invoice-coding]', context).forEach(async app => {
        const invoiceId = Number(app.dataset.invoiceId);
        const apiUrl = app.dataset.apiUrl;
        const canManage = app.dataset.canManage === '1';
        const csrf = () => fetch('/session/token', {credentials: 'same-origin'}).then(r => r.text());
        const post = async (url, payload) => {
          const token = await csrf();
          const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': token},
            body: JSON.stringify(payload)
          });
          if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || `Actie mislukt (${response.status}).`);
          }
          return response.json();
        };

        const load = async () => {
          app.innerHTML = '<p>Codeerwerkbank laden…</p>';
          const response = await fetch(apiUrl, {credentials: 'same-origin'});
          if (!response.ok) throw new Error('Codeerwerkbank kon niet worden geladen.');
          const data = await response.json();
          const invoice = data.invoice || {};
          const lines = data.lines || [];
          const commitmentLines = data.commitment_lines || [];
          const rec = data.reconciliation || {};
          const unmatched = lines.filter(line => line.match_status !== 'matched').length;

          app.innerHTML = `
            <section class="bfpic-summary">
              <div><small>Project</small><strong>${invoice.project_nid ? `#${esc(invoice.project_nid)}` : 'Niet gecodeerd'}</strong></div>
              <div><small>Factuurregels</small><strong>${lines.length}</strong></div>
              <div><small>Nog unmatched</small><strong>${unmatched}</strong></div>
              <div class="${rec.balanced ? 'is-ok' : 'is-warning'}"><small>Regels vs. factuurkop</small><strong>${rec.balanced ? 'In balans' : money(rec.difference_inc_vat)}</strong></div>
            </section>
            ${canManage ? `
              <section class="bfpic-action">
                <h3>1. Project coderen</h3>
                <form data-project-form>
                  <label>Project-NID <input name="project_nid" type="number" min="1" value="${invoice.project_nid || ''}" required></label>
                  <button type="submit">Project koppelen</button>
                </form>
              </section>
              <section class="bfpic-action">
                <h3>2. Factuurregel vastleggen</h3>
                <form data-line-form>
                  <label>Regelnummer <input name="line_number" type="number" min="1" required></label>
                  <label>Omschrijving <input name="description" required></label>
                  <label>Aantal <input name="quantity" inputmode="decimal" required></label>
                  <label>Eenheid <input name="unit"></label>
                  <label>Prijs excl. btw <input name="unit_price_ex_vat" inputmode="decimal" required></label>
                  <label>Bedrag excl. btw <input name="amount_ex_vat" inputmode="decimal" required></label>
                  <label>Btw-code <input name="vat_code" value="NL_21" required></label>
                  <label>Btw % <input name="vat_rate" inputmode="decimal" value="21" required></label>
                  <label>Btw-bedrag <input name="vat_amount" inputmode="decimal" required></label>
                  <label>Bedrag incl. btw <input name="amount_inc_vat" inputmode="decimal" required></label>
                  <label>Notitie <textarea name="review_note"></textarea></label>
                  <button type="submit">Regel opslaan</button>
                </form>
              </section>` : ''}
            <section class="bfpic-lines">
              <h3>3. Codering per factuurregel</h3>
              ${lines.length ? lines.map(line => `
                <article class="bfpic-line">
                  <div><strong>#${esc(line.line_number)} · ${esc(line.description)}</strong><small>${money(line.amount_ex_vat)} excl. · ${money(line.amount_inc_vat)} incl. · ${esc(line.match_status)}</small></div>
                  ${canManage && invoice.project_nid ? `
                    <form data-link-form data-line-id="${line.id}">
                      <select name="commitment_line_id" required>
                        <option value="">Kies bestaande commitmentregel…</option>
                        ${commitmentLines.map(order => `<option value="${order.id}" ${Number(line.commitment_line_id) === Number(order.id) ? 'selected' : ''}>${esc(order.commitment_number)} · regel ${esc(order.line_number)} · ${esc(order.supplier_name)} · ${esc(order.description)} · ${money(order.amount_ex_vat)}</option>`).join('')}
                      </select>
                      <button type="submit">Koppelen</button>
                    </form>` : '<small>Codeer eerst het project om een commitmentregel te koppelen.</small>'}
                </article>`).join('') : '<p>Nog geen factuurregels vastgelegd.</p>'}
            </section>`;

          const projectForm = app.querySelector('[data-project-form]');
          if (projectForm) projectForm.onsubmit = async event => {
            event.preventDefault();
            const projectNid = Number(new FormData(projectForm).get('project_nid'));
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/project`, {project_nid: projectNid});
              await load();
            }
            catch (error) { alert(error.message); }
          };

          const lineForm = app.querySelector('[data-line-form]');
          if (lineForm) lineForm.onsubmit = async event => {
            event.preventDefault();
            const form = new FormData(lineForm);
            const lineNumber = Number(form.get('line_number'));
            const payload = Object.fromEntries(form.entries());
            delete payload.line_number;
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/lines/${lineNumber}`, payload);
              await load();
            }
            catch (error) { alert(error.message); }
          };

          app.querySelectorAll('[data-link-form]').forEach(form => form.onsubmit = async event => {
            event.preventDefault();
            const lineId = Number(form.dataset.lineId);
            const commitmentLineId = Number(new FormData(form).get('commitment_line_id'));
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/lines/${lineId}/commitment`, {commitment_line_id: commitmentLineId});
              await load();
            }
            catch (error) { alert(error.message); }
          });
        };

        try { await load(); }
        catch (error) { app.innerHTML = `<div class="bfpic-error">${esc(error.message)}</div>`; }
      });
    }
  };
})(Drupal, once);
