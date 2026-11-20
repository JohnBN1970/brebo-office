(() => {
  'use strict';
  const root=document.getElementById('crm-kanban-drag');
  if (!root) return;
  const feedback=document.getElementById('crm-drag-feedback');
  const csrf=root.dataset.csrf;
  const next={lead:['prospect','lost'],prospect:['opportunity','lost'],opportunity:['proposal','lost'],proposal:['negotiation','won','lost'],negotiation:['proposal','won','lost'],won:[],lost:['prospect']};
  const zones=Array.from(root.querySelectorAll('[data-crm-stage]:not([data-crm-id])'));
  let moving=null, busy=false;
  const notify=message=>{if(feedback) feedback.textContent=message;};
  const clear=()=>zones.forEach(zone=>{
    zone.style.outline='';
    zone.style.backgroundColor='';
    zone.removeAttribute('aria-label');
  });
  const allowed=zone=>moving && (next[moving.dataset.crmStage]||[]).includes(zone.dataset.crmStage);
  root.querySelectorAll('[data-crm-id]').forEach(card=>{
    const targets=next[card.dataset.crmStage]||[];
    card.draggable=targets.length>0;
    if (!targets.length) {
      card.style.cursor='default';
      card.title='Deze fase heeft geen directe vervolgactie. Gebruik Fase wijzigen voor een correctie.';
      return;
    }
    card.title='Sleep naar een toegestane fase';
    card.addEventListener('dragstart',event=>{
      if (busy) {event.preventDefault();return;}
      moving=card;
      event.dataTransfer.effectAllowed='move';
      event.dataTransfer.setData('text/plain',card.dataset.crmId);
      zones.forEach(zone=>{
        if (!allowed(zone)) return;
        zone.style.outline='3px dashed #178057';
        zone.style.backgroundColor='#e9f8f0';
        zone.setAttribute('aria-label','Toegestane doelkolom: '+zone.querySelector('h2')?.textContent);
      });
      notify('Sleep de kaart naar een groen gemarkeerde kolom.');
    });
    card.addEventListener('dragend',()=>{moving=null;clear();});
  });
  zones.forEach(zone=>{
    zone.addEventListener('dragover',event=>{
      if (!allowed(zone) || busy) return;
      event.preventDefault();
      event.dataTransfer.dropEffect='move';
    });
    zone.addEventListener('drop',async event=>{
      if (!allowed(zone) || busy) return;
      event.preventDefault();
      const card=moving, target=zone.dataset.crmStage;
      busy=true;
      notify('Fasewijziging wordt opgeslagen...');
      try {
        const response=await fetch('/office-test/crm/verkoopkansen/fase?id='+encodeURIComponent(card.dataset.crmId),{
          method:'POST',credentials:'same-origin',
          body:new URLSearchParams({csrf,stage:target}),
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          redirect:'follow'
        });
        if (response.ok && response.redirected && new URL(response.url).pathname==='/office-test/crm/verkoopkansen') {
          window.location.reload(); return;
        }
        if (response.status===403) notify('Geen wijzigingsrechten of sessie verlopen. Vernieuw de pagina en probeer opnieuw.');
        else if (response.status===409) notify('De kaart is intussen gewijzigd. Vernieuw de pagina.');
        else notify('Fasewijziging niet opgeslagen (HTTP '+response.status+'). Gebruik Fase wijzigen.');
      } catch (_) {
        notify('Verbindingsfout. Fase is niet bevestigd; vernieuw de pagina.');
      } finally {
        busy=false; moving=null; clear();
      }
    });
  });
})();