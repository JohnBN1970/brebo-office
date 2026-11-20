(() => {
  'use strict';
  const root=document.getElementById('crm-kanban-drag');
  if (!root) return;
  const csrf=root.dataset.csrf;
  const feedback=document.getElementById('crm-drag-feedback');
  const next={lead:['prospect','lost'],prospect:['opportunity','lost'],opportunity:['proposal','lost'],proposal:['negotiation','won','lost'],negotiation:['proposal','won','lost'],won:[],lost:['prospect']};
  let moving=null, busy=false;
  root.querySelectorAll('[data-crm-id]').forEach(card=>{
    if (!(next[card.dataset.crmStage]||[]).length) {card.draggable=false;return;}
    card.addEventListener('dragstart',e=>{
      if (busy) {e.preventDefault();return;}
      moving=card;
      e.dataTransfer.effectAllowed='move';
      e.dataTransfer.setData('text/plain',card.dataset.crmId);
    });
    card.addEventListener('dragend',()=>{moving=null;});
  });
  root.querySelectorAll('[data-crm-stage]:not([data-crm-id])').forEach(zone=>{
    zone.addEventListener('dragover',e=>{
      if (!moving || !(next[moving.dataset.crmStage]||[]).includes(zone.dataset.crmStage)) return;
      e.preventDefault();
      e.dataTransfer.dropEffect='move';
    });
    zone.addEventListener('drop',async e=>{
      if (!moving || busy) return;
      const card=moving, from=card.dataset.crmStage, to=zone.dataset.crmStage;
      if (!(next[from]||[]).includes(to)) return;
      e.preventDefault();
      busy=true;
      feedback.textContent='Fasewijziging wordt opgeslagen...';
      try {
        const data=new URLSearchParams({csrf,stage:to});
        const response=await fetch('/office-test/crm/verkoopkansen/fase?id='+encodeURIComponent(card.dataset.crmId),{
          method:'POST',credentials:'same-origin',body:data,redirect:'manual',
          headers:{'Content-Type':'application/x-www-form-urlencoded'}
        });
        if (response.type==='opaqueredirect' || response.status===303) {
          window.location.reload();
          return;
        }
        feedback.textContent='Fasewijziging geweigerd. Vernieuw de pagina of gebruik Fase wijzigen.';
      } catch (_) {
        feedback.textContent='Opslaan niet gelukt. Probeer opnieuw.';
      } finally {busy=false;moving=null;}
    });
  });
})();