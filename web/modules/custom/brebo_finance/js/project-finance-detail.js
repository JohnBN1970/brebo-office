(function (Drupal, once) {
  'use strict';
  const money=(v)=>new Intl.NumberFormat('nl-NL',{style:'currency',currency:'EUR',maximumFractionDigits:0}).format(Number(v||0));
  const esc=(v)=>String(v??'').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const table=(rows,columns)=>rows.length?`<div class="bfpd-table-wrap"><table class="bfpd-table"><thead><tr>${columns.map(c=>`<th>${esc(c[1])}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${columns.map(c=>`<td>${c[2]==='money'?money(r[c[0]]):esc(r[c[0]]??'—')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`:'<p class="bfpd-empty">Geen geregistreerde regels.</p>';
  Drupal.behaviors.breboFinanceProjectDetail={attach(context){once('bfpd','#brebo-finance-project-detail',context).forEach(async(app)=>{
    const root=app.querySelector('[data-bfpd-content]');
    try{
      const [cockpit,gates,decisions,ledger]=await Promise.all([
        fetch(app.dataset.apiUrl,{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('Projectcockpit kon niet worden geladen.');return r.json();}),
        fetch(app.dataset.gatesUrl,{credentials:'same-origin'}).then(r=>r.json()),
        fetch(app.dataset.decisionsUrl,{credentials:'same-origin'}).then(r=>r.ok?r.json():({items:[]})),
        fetch(app.dataset.ledgerUrl,{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('Financieel grootboek kon niet worden geladen.');return r.json();}),
      ]);
      const b=cockpit.billing_position||{},p=cockpit.procurement_pipeline||{},w=cockpit.workflow||{},c=cockpit.contract_obligations||{},f=cockpit.failure_costs||{},ch=cockpit.change_orders||{};
      root.innerHTML=`<section class="bfpd-kpis">${[['Te factureren',money(b.billable_not_invoiced_ex_vat)],['Gefactureerd',money(b.invoiced_ex_vat)],['Verplichtingen',money(p.committed_ex_vat)],['Contractrisico',money(c.open_exposure_ex_vat)],['Netto faalkosten',money(f.net_failure_cost_ex_vat)],['Open meerwerk',money(ch.open_sales_ex_vat)],['Betaalvrijgaven',w.payment_releases_pending||0],['Forecast',cockpit.forecast_is_stale?'VEROUDERD':'Actueel']].map(([l,v])=>`<div class="bfpd-kpi"><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('')}</section>
      <section class="bfpd-section"><h2>Financiële fasepoorten</h2><div class="bfpd-gates">${Object.values(gates.phase_gates||{}).map(g=>`<div class="bfpd-gate ${g.released?'is-go':'is-stop'}"><strong>${esc(g.label)}</strong><span>${g.released?'GO':'STOP'}</span><small>${g.blocking_count||0} blokkade(s)</small></div>`).join('')}</div></section>
      <section class="bfpd-section"><h2>Open beslissingen</h2>${(decisions.items||[]).length?`<div class="bfpd-decisions">${decisions.items.map(d=>`<a href="/brebo-office/finance/decision-inbox?exception_id=${d.exception_id}"><strong>${esc(d.priority?.label||'Besluit')}</strong><span>${esc(d.gate)} · ${money(d.exposure?.exposure_amount)}</span><small>${esc(d.priority?.explanation||'')}</small></a>`).join('')}</div>`:'<p>Geen open financiële beslissingen voor dit project.</p>'}</section>
      <section class="bfpd-section"><div class="bfpd-ledger-head"><div><span class="bfpd-kicker">BRONREGELS</span><h2>Financieel dossier</h2></div><small>Van projectpositie naar individuele euro en auditactie.</small></div><nav class="bfpd-tabs" data-ledger-tabs></nav><div data-ledger-panel></div></section>`;
      const tabs=[
        ['commitments','Inkoop',ledger.commitments,[['commitment_number','Nr.'],['supplier_name','Leverancier'],['status','Status'],['amount_ex_vat','Ex. btw','money'],['amount_inc_vat','Incl. btw','money']]],
        ['billing','Facturen',ledger.billing,[['invoice_number','Factuur'],['status','Status'],['amount_ex_vat','Ex. btw','money'],['amount_inc_vat','Incl. btw','money'],['due_date','Vervaldatum']]],
        ['change_orders','Meerwerk',ledger.change_orders,[['change_number','Nr.'],['change_type','Type'],['title','Omschrijving'],['status','Status'],['sales_amount_ex_vat','Verkoop','money'],['cost_amount_ex_vat','Kosten','money']]],
        ['failure_costs','Faalkosten',ledger.failure_costs,[['failure_number','Nr.'],['category','Categorie'],['title','Omschrijving'],['status','Status'],['gross_failure_cost_ex_vat','Bruto','money'],['recoverable_amount_ex_vat','Verhaalbaar','money'],['net_failure_cost_ex_vat','Netto','money']]],
        ['payment_releases','Betalingen',ledger.payment_releases,[['invoice_id','Factuur ID'],['status','Status'],['payment_amount','Betaling','money'],['g_account_amount','G-rekening','money'],['blocked_amount','Geblokkeerd','money'],['reason','Reden']]],
        ['audit','Audittrail',ledger.audit,[['created','Tijd'],['entity_type','Object'],['entity_id','ID'],['action','Actie'],['reason','Reden'],['created_by','Gebruiker']]],
      ];
      const nav=root.querySelector('[data-ledger-tabs]'),panel=root.querySelector('[data-ledger-panel]');
      const show=(key)=>{const t=tabs.find(x=>x[0]===key);nav.querySelectorAll('button').forEach(b=>b.classList.toggle('is-active',b.dataset.tab===key));panel.innerHTML=table(t[2]||[],t[3]);};
      nav.innerHTML=tabs.map(t=>`<button type="button" data-tab="${t[0]}">${t[1]} <span>${(t[2]||[]).length}</span></button>`).join('');
      nav.querySelectorAll('button').forEach(btn=>btn.addEventListener('click',()=>show(btn.dataset.tab))); show('commitments');
    }catch(e){root.innerHTML=`<div class="bfpd-error">${esc(e.message)}</div>`;}
  });}};
})(Drupal,once);
