(function (Drupal, once) {
  'use strict';

  const money = (v) => new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR', maximumFractionDigits: 0}).format(Number(v || 0));
  const number = (v) => new Intl.NumberFormat('nl-NL', {maximumFractionDigits: 2}).format(Number(v || 0));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
  const light = (tone, label, value) => `<div class="bfcc-control bfcc-control--${tone}"><span class="bfcc-traffic" aria-hidden="true"></span><div><small>${esc(label)}</small><strong>${esc(value)}</strong></div></div>`;
  const state = (tone, label) => `<span class="bfcc-domain-state bfcc-domain-state--${esc(tone || 'neutral')}"><span class="bfcc-traffic" aria-hidden="true"></span>${esc(label)}</span>`;

  Drupal.behaviors.breboFinanceCommandCenter = {
    attach(context) {
      once('bfcc', '#brebo-finance-command-center', context).forEach(async (app) => {
        const root = app.querySelector('[data-bfcc-content]');
        try {
          const data = await fetch(app.dataset.apiUrl, {credentials: 'same-origin'}).then((r) => {
            if (!r.ok) throw new Error('Command Center kon niet worden geladen.');
            return r.json();
          });
          const p = data.portfolio;
          const d = data.decisions;
          const h = data.business_health || {};
          const pl = h.profit_loss || {};
          const liq = h.liquidity || {};
          const fixed = h.fixed_costs || {};
          const breakEven = h.break_even || {};
          const overdueTone = Number(p.overdue_invoice_count || 0) > 0 ? 'red' : 'green';
          const releaseTone = Number(p.pending_payment_releases || 0) > 0 ? 'orange' : 'green';
          const staleTone = Number(p.forecast_stale_count || 0) > 0 ? 'orange' : 'green';
          const decisionTone = Number(d.now || 0) > 0 ? 'red' : (Number(d.count || 0) > 0 ? 'orange' : 'green');
          const decisionUrl = app.dataset.decisionUrl || '';
          const decisionHeadLink = decisionUrl ? `<a href="${esc(decisionUrl)}">Open beslisinbox →</a>` : '';
          const decisionRows = decisionUrl && d.top.length
            ? `<div class="bfcc-decisions">${d.top.map((x) => `<a href="${esc(decisionUrl)}?exception_id=${esc(x.exception_id)}" class="bfcc-decision"><span class="bfcc-priority">${esc(x.priority?.label || 'Deze week')}</span><strong>Project #${esc(x.project_nid)} · ${esc(x.gate)}</strong><span>${esc(money(x.exposure?.exposure_amount))}</span><small>${esc(x.priority?.explanation || '')}</small></a>`).join('')}</div>`
            : '<p>Geen financiële besluiten die op jou wachten.</p>';

          const healthAvailable = h.status === 'ok';
          const businessTone = healthAvailable ? (h.tone || 'neutral') : 'neutral';
          const businessLabel = !healthAvailable ? 'Niet beschikbaar' : (businessTone === 'green' ? 'Gezond' : (businessTone === 'red' ? 'Direct sturen' : 'Aandacht'));
          const bankAccounts = Array.isArray(liq.accounts) ? liq.accounts : [];
          const bankRows = bankAccounts.length
            ? `<div class="bfcc-health-list">${bankAccounts.map((x) => `<div><span>${esc(x.name || 'Bankrekening')}${x.identifier ? ` · ${esc(x.identifier)}` : ''}</span><strong>${esc(money(x.closing_balance))}</strong></div>`).join('')}</div>`
            : '<p>Geen afzonderlijke bankrekeningen beschikbaar in de Moneybird-rapportage.</p>';
          const categories = Array.isArray(fixed.categories) ? fixed.categories : [];
          const fixedRows = categories.length
            ? `<div class="bfcc-health-list">${categories.map((x) => `<div><span>${esc(x.label)}</span><span>Werkelijk YTD <strong>${esc(money(x.actual_ytd))}</strong> · budget p/m ${esc(money(x.monthly_budget))}</span></div>`).join('')}</div>`
            : '';
          const unclassified = Array.isArray(fixed.unclassified_expense_accounts) ? fixed.unclassified_expense_accounts : [];
          const unclassifiedRows = unclassified.length
            ? `<details class="bfcc-health-details"><summary>Nog te rubriceren bedrijfskosten (${esc(unclassified.length)})</summary><div class="bfcc-health-list">${unclassified.map((x) => `<div><span>${esc(x.ledger_account_name)}</span><strong>${esc(money(x.value))}</strong></div>`).join('')}</div></details>`
            : '';
          const steering = Array.isArray(h.steering) ? h.steering : [];
          const steeringRows = steering.length
            ? `<ul class="bfcc-steering">${steering.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>`
            : '<p>Op basis van de beschikbare gegevens zijn er geen directe bedrijfseconomische stuurwaarschuwingen.</p>';

          const businessHealth = healthAvailable ? `
            <section class="bfcc-section" id="bfcc-business-health">
              <div class="bfcc-section-head"><div><span class="bfcc-kicker">BEDRIJFSGEZONDHEID · MONEYBIRD YTD</span><h2>Resultaat, liquiditeit en break-even</h2></div>${state(businessTone, businessLabel)}</div>
              <div class="bfcc-domain-grid">
                <article class="bfcc-domain">
                  <div class="bfcc-section-head"><div><span class="bfcc-kicker">WINST & VERLIES</span><h3>Ondernemingsresultaat</h3></div>${state(businessTone, businessLabel)}</div>
                  <div class="bfcc-domain-metrics">
                    <div><small>Omzet YTD</small><strong>${esc(money(pl.revenue))}</strong></div>
                    <div><small>Brutowinst</small><strong>${esc(money(pl.gross_profit))}</strong><small>${pl.gross_margin_pct == null ? '' : `${esc(number(pl.gross_margin_pct))}% marge`}</small></div>
                    <div><small>Bedrijfsresultaat</small><strong>${esc(money(pl.operating_profit))}</strong><small>${pl.operating_margin_pct == null ? '' : `${esc(number(pl.operating_margin_pct))}% marge`}</small></div>
                    <div><small>Nettowinst</small><strong>${esc(money(pl.net_profit))}</strong></div>
                  </div>
                </article>

                <article class="bfcc-domain">
                  <div class="bfcc-section-head"><div><span class="bfcc-kicker">BANK & LIQUIDITEIT</span><h3>Beschikbare geldpositie</h3></div>${state(liq.tone || 'neutral', liq.tone === 'green' ? 'Onder controle' : (liq.tone === 'red' ? 'Direct aandacht' : 'Aandacht'))}</div>
                  <div class="bfcc-domain-metrics">
                    <div><small>Banksaldo</small><strong>${esc(money(liq.closing_balance))}</strong></div>
                    <div><small>Liquiditeitsbuffer</small><strong>${liq.liquidity_months == null ? '—' : `${esc(number(liq.liquidity_months))} mnd`}</strong></div>
                  </div>
                  ${bankRows}
                </article>
              </div>

              <article class="bfcc-domain bfcc-domain--wide">
                <div class="bfcc-section-head"><div><span class="bfcc-kicker">VASTE KOSTEN & BREAK-EVEN</span><h3>Wat moet BREBO minimaal verdienen?</h3></div>${fixed.requires_configuration ? state('orange', 'Rubricering afronden') : state('green', 'Rubricering actief')}</div>
                <div class="bfcc-domain-metrics">
                  <div><small>Indirecte kosten YTD</small><strong>${esc(money(breakEven.indirect_cost_ytd))}</strong></div>
                  <div><small>Indirecte run-rate p/m</small><strong>${esc(money(breakEven.indirect_monthly_run_rate))}</strong></div>
                  <div><small>Vaste-kostenbudget p/m</small><strong>${esc(money(fixed.monthly_budget))}</strong></div>
                  <div><small>Vaste kosten werkelijk YTD</small><strong>${esc(money(fixed.actual_ytd))}</strong></div>
                  <div><small>Break-even omzet p/m</small><strong>${breakEven.monthly_revenue_required == null ? '—' : esc(money(breakEven.monthly_revenue_required))}</strong></div>
                </div>
                ${fixed.requires_configuration ? '<p><strong>Actie:</strong> koppel de Moneybird-grootboekrekeningen aan de BREBO vaste-kostenrubrieken en vul de maandbudgetten in. Tot die tijd wordt het algemene break-evenpunt gebaseerd op de feitelijke indirecte kostenrun-rate en niet gepresenteerd als een volledig vaste-kostenbudget.</p>' : fixedRows}
                ${unclassifiedRows}
              </article>

              <article class="bfcc-domain bfcc-domain--wide">
                <div class="bfcc-section-head"><div><span class="bfcc-kicker">STURING</span><h3>Waar moeten we op sturen?</h3></div>${state(businessTone, businessLabel)}</div>
                ${steeringRows}
              </article>
            </section>` : `
            <section class="bfcc-section" id="bfcc-business-health">
              <div class="bfcc-section-head"><div><span class="bfcc-kicker">BEDRIJFSGEZONDHEID</span><h2>Resultaat, liquiditeit en break-even</h2></div>${state('neutral', 'Niet beschikbaar')}</div>
              <p>${esc(h.operator_message || 'Bedrijfsgezondheid kon niet uit Moneybird worden geladen.')}</p>
            </section>`;

          root.innerHTML = `
            <section class="bfcc-kpis">
              ${light(decisionTone, 'Besluiten', d.count || 0)}
              ${light(staleTone, 'Forecasts verouderd', p.forecast_stale_count || 0)}
              ${light(overdueTone, 'Vervallen verkoopfacturen', p.overdue_invoice_count || 0)}
              ${light(releaseTone, 'Betaalvrijgaven open', p.pending_payment_releases || 0)}
              ${healthAvailable ? light(businessTone, 'Bedrijfsresultaat YTD', money(pl.operating_profit)) : light('neutral', 'Bedrijfsgezondheid', 'Niet beschikbaar')}
              ${healthAvailable ? light(liq.tone || 'neutral', 'Banksaldo', money(liq.closing_balance)) : ''}
            </section>

            <section class="bfcc-domain-grid">
              <article class="bfcc-domain" id="bfcc-purchase">
                <div class="bfcc-section-head"><div><span class="bfcc-kicker">INKOOP & CREDITEUREN</span><h2>Uitgaande geldstroom</h2></div><a href="${esc(app.dataset.payablesUrl)}">Open werkvoorraad →</a></div>
                <div class="bfcc-domain-metrics">
                  <div><small>Verplicht</small><strong>${esc(money(p.committed_ex_vat))}</strong></div>
                  <div><small>Betaalvrijgaven</small><strong>${esc(p.pending_payment_releases || 0)}</strong></div>
                  <div><small>Faalkosten netto</small><strong>${esc(money(p.net_failure_cost_ex_vat))}</strong></div>
                </div>
                <p><a href="${esc(app.dataset.purchaseInvoicesUrl)}">Alle inkoopfacturen openen →</a></p>
              </article>

              <article class="bfcc-domain" id="bfcc-sales">
                <div class="bfcc-section-head"><div><span class="bfcc-kicker">VERKOOP & DEBITEUREN</span><h2>Inkomende geldstroom</h2></div>${state(overdueTone, Number(p.overdue_invoice_count || 0) > 0 ? 'Aandacht nodig' : 'Onder controle')}</div>
                <div class="bfcc-domain-metrics">
                  <div><small>Te factureren</small><strong>${esc(money(p.billable_not_invoiced_ex_vat))}</strong></div>
                  <div><small>Gefactureerd</small><strong>${esc(money(p.invoiced_ex_vat))}</strong></div>
                  <div><small>Vervallen facturen</small><strong>${esc(p.overdue_invoice_count || 0)}</strong></div>
                </div>
                <p>Verkoopfacturen worden per project aangemaakt en via Moneybird bewaakt; deze cockpit toont de organisatiebrede opvolging.</p>
              </article>
            </section>

            ${businessHealth}

            <section class="bfcc-section"><div class="bfcc-section-head"><div><span class="bfcc-kicker">ACTIE</span><h2>Financiële beslissingen</h2></div>${decisionHeadLink}</div>${decisionRows}</section>

            <section class="bfcc-section"><div class="bfcc-section-head"><div><span class="bfcc-kicker">PORTFOLIO</span><h2>Financiële portefeuille</h2></div><span>${esc(p.project_count)} projecten</span></div><div class="bfcc-domain-metrics"><div><small>Contractrisico open</small><strong>${esc(money(p.open_contract_exposure_ex_vat))}</strong></div><div><small>Open meerwerk</small><strong>${esc(money(p.open_change_order_sales_ex_vat))}</strong></div><div><small>Forecasts verouderd</small><strong>${esc(p.forecast_stale_count || 0)}</strong></div></div></section>`;
        }
        catch (e) {
          root.innerHTML = `<div class="bfcc-error">${esc(e.message)}</div>`;
        }
      });
    },
  };
})(Drupal, once);
