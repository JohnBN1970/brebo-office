(function (Drupal, once) {
  'use strict';

  const money = (v) => new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR', maximumFractionDigits: 0}).format(Number(v || 0));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
  const light = (tone, label, value) => `<div class="bfcc-control bfcc-control--${tone}"><span class="bfcc-traffic" aria-hidden="true"></span><div><small>${esc(label)}</small><strong>${esc(value)}</strong></div></div>`;

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
          const overdueTone = Number(p.overdue_invoice_count || 0) > 0 ? 'red' : 'green';
          const releaseTone = Number(p.pending_payment_releases || 0) > 0 ? 'orange' : 'green';
          const staleTone = Number(p.forecast_stale_count || 0) > 0 ? 'orange' : 'green';
          const decisionTone = Number(d.now || 0) > 0 ? 'red' : (Number(d.today || 0) > 0 ? 'orange' : 'green');

          root.innerHTML = `
            <section class="bfcc-kpis">
              ${light(decisionTone, 'Besluiten', d.count || 0)}
              ${light(staleTone, 'Forecasts verouderd', p.forecast_stale_count || 0)}
              ${light(overdueTone, 'Vervallen verkoopfacturen', p.overdue_invoice_count || 0)}
              ${light(releaseTone, 'Betaalvrijgaven open', p.pending_payment_releases || 0)}
              <div class="bfcc-kpi"><small>Beslis-exposure</small><strong>${esc(money(d.exposure_amount))}</strong></div>
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
                <div class="bfcc-section-head"><div><span class="bfcc-kicker">VERKOOP & DEBITEUREN</span><h2>Inkomende geldstroom</h2></div><span class="bfcc-domain-state bfcc-domain-state--${overdueTone}"><span class="bfcc-traffic"></span>${Number(p.overdue_invoice_count || 0) > 0 ? 'Aandacht nodig' : 'Onder controle'}</span></div>
                <div class="bfcc-domain-metrics">
                  <div><small>Te factureren</small><strong>${esc(money(p.billable_not_invoiced_ex_vat))}</strong></div>
                  <div><small>Gefactureerd</small><strong>${esc(money(p.invoiced_ex_vat))}</strong></div>
                  <div><small>Vervallen facturen</small><strong>${esc(p.overdue_invoice_count || 0)}</strong></div>
                </div>
                <p>Verkoopfacturen worden per project aangemaakt en via Moneybird bewaakt; deze cockpit toont de organisatiebrede opvolging.</p>
              </article>
            </section>

            <section class="bfcc-section"><div class="bfcc-section-head"><div><span class="bfcc-kicker">ACTIE</span><h2>Financiële beslissingen</h2></div><a href="${esc(app.dataset.decisionUrl)}">Open beslisinbox →</a></div>${d.top.length ? `<div class="bfcc-decisions">${d.top.map((x) => `<a href="${esc(app.dataset.decisionUrl)}?exception_id=${esc(x.exception_id)}" class="bfcc-decision"><span class="bfcc-priority">${esc(x.priority?.label || 'Deze week')}</span><strong>Project #${esc(x.project_nid)} · ${esc(x.gate)}</strong><span>${esc(money(x.exposure?.exposure_amount))}</span><small>${esc(x.priority?.explanation || '')}</small></a>`).join('')}</div>` : '<p>Geen financiële besluiten die op jou wachten.</p>'}</section>

            <section class="bfcc-section"><div class="bfcc-section-head"><div><span class="bfcc-kicker">PORTFOLIO</span><h2>Projecten onder controle</h2></div><span>${esc(p.project_count)} projecten · ${esc(p.forecast_stale_count)} forecast(s) verouderd</span></div><div class="bfcc-projects">${data.projects.map((x) => `<article class="bfcc-project"><div><h3><a href="/brebo-office/finance/projects/${esc(x.project_nid)}">${esc(x.title)}</a></h3><small>Project #${esc(x.project_nid)}${x.forecast_is_stale ? ' · forecast verouderd' : ''}</small></div><div><small>Te factureren</small><strong>${esc(money(x.billing_position?.billable_not_invoiced_ex_vat))}</strong></div><div><small>Verplichtingen</small><strong>${esc(money(x.procurement_pipeline?.committed_ex_vat))}</strong></div><div><small>Contractrisico</small><strong>${esc(money(x.contract_obligations?.open_exposure_ex_vat))}</strong></div><div><small>Faalkosten</small><strong>${esc(money(x.failure_costs?.net_failure_cost_ex_vat))}</strong></div><div><small>Betaalvrijgaven</small><strong>${esc(x.workflow?.payment_releases_pending || 0)}</strong></div></article>`).join('')}</div></section>`;
        }
        catch (e) {
          root.innerHTML = `<div class="bfcc-error">${esc(e.message)}</div>`;
        }
      });
    },
  };
})(Drupal, once);
