(function (Drupal, once) {
  'use strict';
  const money = (v) => new Intl.NumberFormat('nl-NL',{style:'currency',currency:'EUR',maximumFractionDigits:0}).format(Number(v || 0));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  Drupal.behaviors.breboFinanceCommandCenter = { attach(context) {
    once('bfcc','#brebo-finance-command-center',context).forEach(async (app) => {
      const root = app.querySelector('[data-bfcc-content]');
      try {
        const data = await fetch(app.dataset.apiUrl,{credentials:'same-origin'}).then(r=>{if(!r.ok) throw new Error('Command Center kon niet worden geladen.'); return r.json();});
        const p=data.portfolio,d=data.decisions;
        const kpis=[['Besluiten nu',d.now],['Besluiten vandaag',d.today],['Beslis-exposure',money(d.exposure_amount)],['Te factureren',money(p.billable_not_invoiced_ex_vat)],['Verplicht',money(p.committed_ex_vat)],['Contractrisico',money(p.open_contract_exposure_ex_vat)],['Faalkosten netto',money(p.net_failure_cost_ex_vat)],['Open meerwerk',money(p.open_change_order_sales_ex_vat)],['Achterstallig',p.overdue_invoice_count],['Betaalvrijgaven',p.pending_payment_releases]];
        root.innerHTML=`<section class="bfcc-kpis">${kpis.map(([l,v],i)=>`<div class="bfcc-kpi ${i<3?'bfcc-kpi-focus':''}"><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('')}</section>
          <section class="bfcc-section"><div class="bfcc-section-head"><div><span class="bfcc-kicker">ACTIE</span><h2>Financiële beslissingen</h2></div><a href="${app.dataset.decisionUrl}">Open beslisinbox →</a></div>${d.top.length?`<div class="bfcc-decisions">${d.top.map(x=>`<a href="${app.dataset.decisionUrl}?exception_id=${x.exception_id}" class="bfcc-decision"><span class="bfcc-priority">${esc(x.priority?.label||'Deze week')}</span><strong>Project #${x.project_nid} · ${esc(x.gate)}</strong><span>${money(x.exposure?.exposure_amount)}</span><small>${esc(x.priority?.explanation||'')}</small></a>`).join('')}</div>`:'<p>Geen financiële besluiten die op jou wachten.</p>'}</section>
          <section class="bfcc-section"><div class="bfcc-section-head"><div><span class="bfcc-kicker">PORTFOLIO</span><h2>Projecten onder controle</h2></div><span>${p.project_count} projecten · ${p.forecast_stale_count} forecast(s) verouderd</span></div><div class="bfcc-projects">${data.projects.map(x=>`<article class="bfcc-project"><div><h3>${esc(x.title)}</h3><small>Project #${x.project_nid}${x.forecast_is_stale?' · forecast verouderd':''}</small></div><div><small>Te factureren</small><strong>${money(x.billing_position?.billable_not_invoiced_ex_vat)}</strong></div><div><small>Verplichtingen</small><strong>${money(x.procurement_pipeline?.committed_ex_vat)}</strong></div><div><small>Contractrisico</small><strong>${money(x.contract_obligations?.open_exposure_ex_vat)}</strong></div><div><small>Faalkosten</small><strong>${money(x.failure_costs?.net_failure_cost_ex_vat)}</strong></div><div><small>Betaalvrijgaven</small><strong>${esc(x.workflow?.payment_releases_pending||0)}</strong></div></article>`).join('')}</div></section>`;
      } catch(e) { root.innerHTML=`<div class="bfcc-error">${esc(e.message)}</div>`; }
    });
  }};
})(Drupal, once);
