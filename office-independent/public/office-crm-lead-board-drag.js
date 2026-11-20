(()=>{
 const board=document.getElementById('lead-board'); if(!board)return;
 const allowed={new:['contacted'],contacted:['new','qualified'],qualified:['contacted','accepted'],accepted:['qualified']};
 let moving=null;
 board.querySelectorAll('[draggable="true"]').forEach(card=>{
  card.addEventListener('dragstart',e=>{moving=card; e.dataTransfer.effectAllowed='move';
   board.querySelectorAll('[data-stage]').forEach(zone=>{if(zone.tagName==='SECTION' && allowed[card.dataset.stage]?.includes(zone.dataset.stage)){zone.style.outline='3px solid #16a34a';}});
  });
  card.addEventListener('dragend',()=>{moving=null;board.querySelectorAll('section').forEach(z=>z.style.outline='');});
 });
 board.querySelectorAll('section[data-stage]').forEach(zone=>{
  zone.addEventListener('dragover',e=>{if(moving && allowed[moving.dataset.stage]?.includes(zone.dataset.stage)){e.preventDefault();e.dataTransfer.dropEffect='move';}});
  zone.addEventListener('drop',async e=>{
   e.preventDefault();if(!moving || !allowed[moving.dataset.stage]?.includes(zone.dataset.stage))return;
   const data=new URLSearchParams({csrf:board.dataset.csrf,lead_id:moving.dataset.leadId,stage:zone.dataset.stage});
   try {const response=await fetch('/office-test/crm/aanvragen/fase',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data});
    if(!response.ok)throw new Error('Fasewijziging geweigerd ('+response.status+')');location.reload();
   }catch(err){alert(err.message);}
  });
 });
})();
