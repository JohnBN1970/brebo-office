(function (Drupal, once) {
  'use strict';
  const money=(v)=>new Intl.NumberFormat('nl-NL',{style:'currency',currency:'EUR',maximumFractionDigits:0}).format(Number(v||0));
  const esc=(v)=>String(v??'').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  Drupal.behaviors.breboFinanceProjectDetail={attach(context){once('bfpd','#brebo-finance-project-detail',context).forEach(async(app)=>{
    const root=app.querySelector('[data-bfpd-content]');
    try{
      const [cockpit,gates,decisions]=await Promise.all([
        fetch(app.dataset.apiUrl,{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('Projectcockpit kon niet worden geladen.');return r.json();}),
        fetch(app.dataset.gatesUrl,{credentials:'same-origin'}).then(r=>r.json()),
        fetch(app.dataset.decisionsUrl,{credentials:'same-origin'}).then(r=>r.ok?r.json():({items:[]})),
      ]);
      const b=cockpit.billing_position||{},p=cockpit.procurement_pipeline||{},w=cockpit.workflow||{},c=cockpit.contract_obligations||{},f=cockpit.failure_costs||{},ch=cockpit.change_orders||{};
      root.innerHTML=`<section class="bfpd-kpis">${[
        ['Te factureren',money(b.billable_not_invoiced_ex_vat)],['Gefactureerd',money(b.invoiced_ex_vat)],['Verplichtingen',money(p.committed_ex_vat)],['Contractrisico',money(c.open_exposure_ex_vat)],['Netto faalkosten',money(f.net_failure_cost_ex_vat)],['Open meerwerk',money(ch.open_sales_ex_vat)],['Betaalvrijgaven',w.payment_releases_pending||0],['Forecast',cockpit.forecast_is_stale?'VEROUDERD':'Actueel']
      ].map(([l,v])=>`<div class="bfpd-kpi"><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('')}</section>
      <section class="bfpd-section"><h2>Financiële fasepoorten</h2><div class="bfpd-gates">${Object.values(gates.phase_gates||{}).map(g=>`<div class="bfpd-gate ${g.released?'is-go':'is-stop'}"><strong>${esc(g.label)}</strong><span>${g.released?'GO':'STOP'}</span><small>${g.blocking_count||0} blokkade(s)</small></div>`).join('')}</div></section>
      <section class="bfpd-section"><h2>Open beslissingen</h2>${(decisions.items||[]).length?`<div class="bfpd-decisions">${decisions.items.map(d=>`<a href="/brebo-office/finance/decision-inbox?exception_id=${d.exception_id}"><strong>${esc(d.priority?.label||'Besluit')}</strong><span>${esc(d.gate)} · ${money(d.exposure?.exposure_amount)}</span><small>${esc(d.priority?.explanation||'')}</small></a>`).join('')}</div>`:'<p>Geen open financiële beslissingen voor dit project.</p>'}</section>
      <section class="bfpd-section"><h2>Risico & geldstroom</h2><div class="bfpd-grid"><div><small>Achterstallige facturen</small><strong>${esc(b.overdue_count||0)}</strong></div><div><small>Betwiste facturen</small><strong>${esc(b.disputed_count||0)}</strong></div><div><small>Open contractverplichtingen</small><strong>${esc(c.open_count||0)}</strong></div><div><small>Open faalkosten</small><strong>${esc(f.open_count||0)}</strong></div><div><small>Meerwerk uitvoeringsrisico</small><strong>${esc(ch.execution_at_risk||0)}</strong></div><div><small>Meerwerk niet gefactureerd</small><strong>${esc(ch.executed_not_invoiced||0)}</strong></div></div></section>`;
    }catch(e){root.innerHTML=`<div class="bfpd-error">${esc(e.message)}</div>`;}
  });}};
})(Drupal,once);
