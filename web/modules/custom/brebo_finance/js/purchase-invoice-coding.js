(function (Drupal, once) {
  'use strict';

  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const money = value => new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR'}).format(Number(value || 0));
  const parseEvidence = value => String(value || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
    const separator = line.indexOf('=');
    if (separator < 1) throw new Error('Gebruik voor bewijs: type=referentie, één item per regel.');
    return {type: line.slice(0, separator).trim(), ref: line.slice(separator + 1).trim()};
  });

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
            body: JSON.stringify(payload || {})
          });
          if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || `Actie mislukt (${response.status}).`);
          }
          return response.json();
        };

        const reloadPage = () => window.location.reload();

        const loadActions = async () => {
          const host = app.querySelector('[data-finance-actions]');
          if (!host) return;
          const response = await fetch(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/actions`, {credentials: 'same-origin'});
          if (!response.ok) {
            host.innerHTML = '<p>Financiële acties zijn pas beschikbaar nadat de factuur aan een toegankelijk project is gekoppeld.</p>';
            return;
          }
          const state = await response.json();
          const permissions = state.permissions || {};
          const lines = state.lines || [];
          const release = state.payment_release || null;
          const invoice = state.invoice || {};
          const allMatched = lines.length > 0 && lines.every(line => line.match_status === 'matched');

          host.innerHTML = `
            <h2>4. Prestatie · three-way match · betaalvrijgave</h2>
            <p>Elke actie hieronder gebruikt de bestaande Finance-controlservices. Een blokkade kan hier niet worden omzeild.</p>
            ${lines.map(line => {
              const blocker = line.blocker || {};
              const performances = blocker.performances || [];
              return `<article class="bfpic-line">
                <div><strong>Factuurregel #${esc(line.line_number)} · ${esc(line.description)}</strong><small>${esc(line.match_status || 'unmatched')} · ${esc(line.variance_code || 'geen afwijkingscode')}</small></div>
                ${permissions.manage_procurement && line.commitment_line_id ? `
                  <details>
                    <summary>Prestatie registreren tegen commitmentregel #${esc(line.commitment_line_id)}</summary>
                    <form data-performance-form data-invoice-line-id="${esc(line.id)}">
                      <label>Bedrag excl. btw <input name="amount_ex_vat" inputmode="decimal" value="${esc(line.amount_ex_vat || '')}" required></label>
                      <label>Omschrijving <input name="description" value="${esc(line.description || '')}" required></label>
                      <label>Gebouw-NID <input name="building_nid" type="number" min="1" required></label>
                      <label>Object-ID <input name="object_id" type="number" min="1" required></label>
                      <label>Bewijs, één per regel als type=referentie<textarea name="evidence" required></textarea></label>
                      <button type="submit">Prestatie registreren</button>
                    </form>
                  </details>` : ''}
                ${performances.map(performance => `
                  <div class="bfpic-action">
                    <strong>Prestatie #${esc(performance.id)} · ${esc(performance.status)}</strong>
                    <small>${money(performance.amount_ex_vat)} excl. btw${performance.verified_for_match ? ' · geverifieerd voor match' : ''}</small>
                    ${permissions.approve_finance && performance.status === 'submitted' ? `
                      <form data-performance-verify data-receipt-id="${esc(performance.id)}">
                        <label><input type="checkbox" name="building_evidence_complete" value="1"> Gebouwbewijs compleet</label>
                        <label><input type="checkbox" name="quality_accepted" value="1"> Kwaliteit geaccepteerd</label>
                        <label>Beoordelingsnotitie <textarea name="note" required></textarea></label>
                        <button type="submit">Prestatie beoordelen</button>
                      </form>` : ''}
                  </div>`).join('')}
                ${permissions.manage_finance ? `<button type="button" data-match-line="${esc(line.id)}">Three-way match uitvoeren</button>` : ''}
              </article>`;
            }).join('')}
            <section class="bfpic-action">
              <h3>Betaalvrijgave</h3>
              <p>Factuurstatus: <strong>${esc(invoice.status || '')}</strong> · match: <strong>${esc(invoice.match_status || '')}</strong>${allMatched ? ' · alle regels matched' : ''}</p>
              ${permissions.manage_finance && !release ? `
                <form data-release-request>
                  <label>Vrijgavenummer <input name="release_number" required></label>
                  <label>Gewenste betaaldatum <input name="requested_payment_date" type="date"></label>
                  <button type="submit">Betaalvrijgave aanvragen</button>
                </form>` : ''}
              ${release ? `<p>Vrijgave <strong>${esc(release.release_number || '#' + release.id)}</strong> · status <strong>${esc(release.status)}</strong> · totaal ${money(release.total_amount)}</p>` : ''}
              ${release && permissions.approve_finance && release.status === 'pending_approval' ? `
                <form data-release-decision data-release-id="${esc(release.id)}">
                  <label>Besluit <select name="decision"><option value="approved">Goedkeuren</option><option value="rejected">Afwijzen</option></select></label>
                  <label>Motivering <textarea name="note" required></textarea></label>
                  <button type="submit">Besluit vastleggen</button>
                </form>` : ''}
              ${release && permissions.manage_finance && release.status === 'approved' ? `
                <form data-release-execute data-release-id="${esc(release.id)}">
                  <label>Moneybird-/bankreferentie <input name="moneybird_payment_ref" required></label>
                  <button type="submit">Betaling als uitgevoerd registreren</button>
                </form>` : ''}
            </section>`;

          host.querySelectorAll('[data-performance-form]').forEach(form => form.onsubmit = async event => {
            event.preventDefault();
            const data = new FormData(form);
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/lines/${Number(form.dataset.invoiceLineId)}/performances`, {
                amount_ex_vat: data.get('amount_ex_vat'),
                description: data.get('description'),
                evidence: parseEvidence(data.get('evidence')),
                building_nid: Number(data.get('building_nid')),
                object_id: Number(data.get('object_id'))
              });
              reloadPage();
            }
            catch (error) { alert(error.message); }
          });

          host.querySelectorAll('[data-performance-verify]').forEach(form => form.onsubmit = async event => {
            event.preventDefault();
            const data = new FormData(form);
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/performances/${Number(form.dataset.receiptId)}/verification`, {
                building_evidence_complete: data.get('building_evidence_complete') === '1',
                quality_accepted: data.get('quality_accepted') === '1',
                note: data.get('note')
              });
              reloadPage();
            }
            catch (error) { alert(error.message); }
          });

          host.querySelectorAll('[data-match-line]').forEach(button => button.onclick = async () => {
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/lines/${Number(button.dataset.matchLine)}/match`, {});
              reloadPage();
            }
            catch (error) { alert(error.message); }
          });

          const requestForm = host.querySelector('[data-release-request]');
          if (requestForm) requestForm.onsubmit = async event => {
            event.preventDefault();
            const data = new FormData(requestForm);
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/payment-releases`, {
                release_number: data.get('release_number'),
                requested_payment_date: data.get('requested_payment_date')
              });
              reloadPage();
            }
            catch (error) { alert(error.message); }
          };

          const decisionForm = host.querySelector('[data-release-decision]');
          if (decisionForm) decisionForm.onsubmit = async event => {
            event.preventDefault();
            const data = new FormData(decisionForm);
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/payment-releases/${Number(decisionForm.dataset.releaseId)}/decision`, {
                decision: data.get('decision'),
                note: data.get('note')
              });
              reloadPage();
            }
            catch (error) { alert(error.message); }
          };

          const executeForm = host.querySelector('[data-release-execute]');
          if (executeForm) executeForm.onsubmit = async event => {
            event.preventDefault();
            const data = new FormData(executeForm);
            try {
              await post(`/brebo-office/api/finance/purchase-invoices/${invoiceId}/payment-releases/${Number(executeForm.dataset.releaseId)}/execution`, {
                moneybird_payment_ref: data.get('moneybird_payment_ref')
              });
              reloadPage();
            }
            catch (error) { alert(error.message); }
          };
        };

        const load = async () => {
          app.innerHTML = '<p>Codeerwerkbank laden…</p>';
          const response = await fetch(apiUrl, {credentials: 'same-origin'});
          if (!response.ok) throw new Error('Codeerwerkbank kon niet worden geladen.');
          const data = await response.json();
          const invoice = data.invoice || {};
          const projects = data.projects || [];
          const lines = data.lines || [];
          const commitmentLines = data.commitment_lines || [];
          const rec = data.reconciliation || {};
          const unmatched = lines.filter(line => line.match_status !== 'matched').length;
          const nextAction = !invoice.project_nid
            ? 'Koppel eerst het juiste project.'
            : lines.length === 0
              ? 'Controleer de bronfactuur en leg de factuurregels vast.'
              : !rec.balanced
                ? 'Maak de factuurregels sluitend met de factuurkop.'
                : unmatched > 0
                  ? 'Koppel de regels aan commitments en voer de controle uit.'
                  : 'De codering is gereed; ga door met prestatie, match en betaalvrijgave.';

          app.innerHTML = `
            <section class="bfpic-action bfpic-next-action">
              <h2>Wat moet ik nu doen?</h2>
              <p><strong>${esc(nextAction)}</strong></p>
            </section>
            <section class="bfpic-summary">
              <div><small>Project</small><strong>${invoice.project_nid ? esc((projects.find(project => Number(project.id) === Number(invoice.project_nid)) || {}).label || `#${invoice.project_nid}`) : 'Niet gecodeerd'}</strong></div>
              <div><small>Factuurregels</small><strong>${lines.length}</strong></div>
              <div><small>Nog unmatched</small><strong>${unmatched}</strong></div>
              <div class="${rec.balanced ? 'is-ok' : 'is-warning'}"><small>Regels vs. factuurkop</small><strong>${rec.balanced ? 'In balans' : money(rec.difference_inc_vat)}</strong></div>
            </section>
            ${canManage ? `
              <section class="bfpic-action">
                <h3>1. Project koppelen</h3>
                <form data-project-form>
                  <label>Project
                    <select name="project_nid" required>
                      <option value="">Kies project…</option>
                      ${projects.map(project => `<option value="${esc(project.id)}" ${Number(project.id) === Number(invoice.project_nid) ? 'selected' : ''}>${esc(project.label)}</option>`).join('')}
                    </select>
                  </label>
                  <button type="submit">Project koppelen</button>
                </form>
              </section>
              <section class="bfpic-action">
                <h3>2. Factuurregel controleren / vastleggen</h3>
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
                    </form>` : '<small>Koppel eerst het project om een commitmentregel te kiezen.</small>'}
                </article>`).join('') : '<p>Nog geen factuurregels vastgelegd.</p>'}
            </section>
            <section class="bfpic-action" data-finance-actions><p>Financiële acties laden…</p></section>`;

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

          await loadActions();
        };

        try { await load(); }
        catch (error) { app.innerHTML = `<div class="bfpic-error">${esc(error.message)}</div>`; }
      });
    }
  };
})(Drupal, once);