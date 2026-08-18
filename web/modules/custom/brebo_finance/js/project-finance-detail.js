(function (Drupal, once) {
  'use strict';
  const money=(v)=>new Intl.NumberFormat('nl-NL',{style:'currency',currency:'EUR',maximumFractionDigits:0}).format(Number(v||0));
  const esc=(v)=>String(v??'').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const table=(rows,columns,traceType)=>rows.length?`<div class="bfpd-table-wrap"><table class="bfpd-table"><thead><tr>${columns.map(c=>`<th>${esc(c[1])}</th>`).join('')}<th></th></tr></thead><tbody>${rows.map(r=>`<tr>${columns.map(c=>`<td>${c[2]==='money'?money(r[c[0]]):esc(r[c[0]]??'—')}</td>`).join('')}<td>${traceType&&r.id?`<button type="button" class="bfpd-trace-btn" data-trace-type="${traceType}" data-trace-id="${r.id}">Volg euro →</button>`:''}</td></tr>`).join('')}</tbody></table></div>`:'<p class="bfpd-empty">Geen geregistreerde regels.</p>';
  const step=(label,rows,detail='')=>`<div class="bfpd-trace-step ${rows.length?'is-present':'is-missing'}"><span>${rows.length?'✓':'!'}</span><div><strong>${esc(label)}</strong><small>${rows.length?`${rows.length} bronregel(s)${detail?` · ${detail}`:''}`:'Koppeling ontbreekt'}</small></div></div>`;
  const evidenceCount=(raw)=>{try{const v=typeof raw==='string'?JSON.parse(raw):raw;return Array.isArray(v)?v.length:0;}catch(e){return 0;}};
  Drupal.behaviors.breboFinanceProjectDetail={attach(context){once('bfpd','#brebo-finance-project-detail',context).forEach(async(app)=>{
    const root=app.querySelector('[data-bfpd-content]');
    const csrf=()=>fetch('/session/token',{credentials:'same-origin'}).then(r=>r.text());
    const canVerify=app.dataset.canVerifyPerformance==='1';
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
      <section class="bfpd-section"><div class="bfpd-ledger-head"><div><span class="bfpd-kicker">BRONREGELS</span><h2>Financieel dossier</h2></div><small>Van projectpositie naar individuele euro en auditactie.</small></div><nav class="bfpd-tabs" data-ledger-tabs></nav><div data-ledger-panel></div></section>
      <section class="bfpd-section" data-trace-section hidden><div class="bfpd-ledger-head"><div><span class="bfpd-kicker">EURO TRACE</span><h2>Herkomst en bestemming van deze euro</h2></div><button type="button" data-trace-close>Sluiten</button></div><div data-trace-panel></div></section>`;

      const performanceHtml=(rows)=>rows.length?`<div class="bfpd-performance-list">${rows.map(r=>`<article class="bfpd-performance-card" data-receipt-id="${r.id}"><div class="bfpd-performance-main"><div><span class="bfpd-status bfpd-status-${esc(r.status)}">${esc(r.status)}</span><h3>${esc(r.description)}</h3><small>Commitmentregel #${esc(r.commitment_line_id)} · ingediend door gebruiker #${esc(r.created_by)}</small></div><strong>${money(r.amount_ex_vat)}</strong></div><div class="bfpd-performance-meta"><span>Bewijs: ${evidenceCount(r.evidence)} item(s)</span><span>Gebouwbewijs: ${Number(r.building_evidence_complete)?'compleet':'niet compleet'}</span><span>Kwaliteit: ${Number(r.quality_accepted)?'geaccepteerd':'niet geaccepteerd'}</span>${r.verified_by?`<span>Verificateur #${esc(r.verified_by)}</span>`:''}</div>${r.verification_note?`<p class="bfpd-verification-note"><strong>Verificatie:</strong> ${esc(r.verification_note)}</p>`:''}${canVerify&&r.status==='submitted'?`<div class="bfpd-performance-actions"><button type="button" data-performance-decision="reject">Afkeuren</button><button type="button" data-performance-decision="accept">Accepteren</button></div>`:''}</article>`).join('')}</div>`:'<p class="bfpd-empty">Nog geen prestaties geregistreerd.</p>';

      const tabs=[
        ['commitments','Inkoop',ledger.commitments,[['commitment_number','Nr.'],['supplier_name','Leverancier'],['status','Status'],['amount_ex_vat','Ex. btw','money'],['amount_inc_vat','Incl. btw','money']],'commitment'],
        ['performance_receipts','Prestaties',ledger.performance_receipts||[],null,null],
        ['billing','Facturen',ledger.billing,[['invoice_number','Factuur'],['status','Status'],['amount_ex_vat','Ex. btw','money'],['amount_inc_vat','Incl. btw','money'],['due_date','Vervaldatum']],null],
        ['change_orders','Meerwerk',ledger.change_orders,[['change_number','Nr.'],['change_type','Type'],['title','Omschrijving'],['status','Status'],['sales_amount_ex_vat','Verkoop','money'],['cost_amount_ex_vat','Kosten','money']],null],
        ['failure_costs','Faalkosten',ledger.failure_costs,[['failure_number','Nr.'],['category','Categorie'],['title','Omschrijving'],['status','Status'],['gross_failure_cost_ex_vat','Bruto','money'],['recoverable_amount_ex_vat','Verhaalbaar','money'],['net_failure_cost_ex_vat','Netto','money']],null],
        ['payment_releases','Betalingen',ledger.payment_releases,[['invoice_id','Factuur ID'],['status','Status'],['payment_amount','Betaling','money'],['g_account_amount','G-rekening','money'],['blocked_amount','Geblokkeerd','money'],['reason','Reden']],'payment_release'],
        ['audit','Audittrail',ledger.audit,[['created','Tijd'],['entity_type','Object'],['entity_id','ID'],['action','Actie'],['reason','Reden'],['created_by','Gebruiker']],null],
      ];
      const nav=root.querySelector('[data-ledger-tabs]'),panel=root.querySelector('[data-ledger-panel]'),traceSection=root.querySelector('[data-trace-section]'),tracePanel=root.querySelector('[data-trace-panel]');
      const bindPerformance=()=>panel.querySelectorAll('[data-performance-decision]').forEach(btn=>btn.addEventListener('click',async()=>{
        const card=btn.closest('[data-receipt-id]');
        const accepted=btn.dataset.performanceDecision==='accept';
        const note=window.prompt(accepted?'Motivatie voor acceptatie:':'Reden van afkeuring:');
        if(note===null||note.trim()==='') return;
        btn.disabled=true;
        try{
          const token=await csrf();
          const response=await fetch(`${app.dataset.performanceUrl}/${card.dataset.receiptId}/verification`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify({building_evidence_complete:accepted,quality_accepted:accepted,note:note.trim()})});
          if(!response.ok){const error=await response.json().catch(()=>({}));throw new Error(error.message||'Prestatie kon niet worden verwerkt.');}
          window.location.reload();
        }catch(e){window.alert(e.message);btn.disabled=false;}
      }));
      const bindTrace=()=>panel.querySelectorAll('[data-trace-type]').forEach(btn=>btn.addEventListener('click',async()=>{
        traceSection.hidden=false; tracePanel.innerHTML='<div class="bfpd-loading">Eurotrace laden…</div>'; traceSection.scrollIntoView({behavior:'smooth',block:'start'});
        try{const t=await fetch(`/brebo-office/api/finance/trace/${btn.dataset.traceType}/${btn.dataset.traceId}`,{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('Eurotrace kon niet worden geladen.');return r.json();});
          const invoices=t.invoices||[],releases=t.payment_releases||[],audit=t.audit||[];
          tracePanel.innerHTML=`<div class="bfpd-trace-status ${t.trace_complete?'is-complete':'is-incomplete'}"><strong>${t.trace_complete?'Keten compleet':'Keten niet compleet'}</strong><span>${t.trace_complete?'Alle hoofdschakels zijn aantoonbaar gekoppeld.':`Ontbreekt: ${(t.missing_links||[]).map(esc).join(', ')}`}</span></div><div class="bfpd-trace-line">${step('Werkbegroting',t.budget_lines||[])}${step('Inkoop',t.commitment_lines||[])}${step('Prestatie',t.performance_receipts||[])}${step('Factuur / 3-way match',t.invoice_lines||[],invoices.map(i=>i.match_status).filter(Boolean).join(', '))}${step('Betaalvrijgave',releases,releases.map(r=>r.status).filter(Boolean).join(', '))}${step('Bank / G-rekening',releases.filter(r=>r.status==='executed'),releases.map(r=>r.moneybird_payment_ref||r.g_account_amount).filter(Boolean).join(', '))}${step('Audit',audit)}</div><details class="bfpd-trace-audit"><summary>Audittrail (${audit.length})</summary>${table(audit,[['created','Tijd'],['entity_type','Object'],['entity_id','ID'],['action','Actie'],['reason','Reden'],['created_by','Gebruiker']],null)}</details>`;
        }catch(e){tracePanel.innerHTML=`<div class="bfpd-error">${esc(e.message)}</div>`;}
      }));
      const show=(key)=>{const t=tabs.find(x=>x[0]===key);nav.querySelectorAll('button').forEach(b=>b.classList.toggle('is-active',b.dataset.tab===key));panel.innerHTML=key==='performance_receipts'?performanceHtml(t[2]||[]):table(t[2]||[],t[3],t[4]);bindTrace();bindPerformance();};
      nav.innerHTML=tabs.map(t=>`<button type="button" data-tab="${t[0]}">${t[1]} <span>${(t[2]||[]).length}</span></button>`).join('');
      nav.querySelectorAll('button').forEach(btn=>btn.addEventListener('click',()=>show(btn.dataset.tab))); root.querySelector('[data-trace-close]').addEventListener('click',()=>{traceSection.hidden=true;}); show('commitments');
    }catch(e){root.innerHTML=`<div class="bfpd-error">${esc(e.message)}</div>`;}
  });}};
})(Drupal,once);
