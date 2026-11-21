(() => {
'use strict';
const configs={
 lead:{root:'lead-board',card:'[data-lead-id]',zone:'section[data-stage]',id:'leadId',endpoint:'/office-test/crm/aanvragen/fase',next:{new:['contacted'],contacted:['new','qualified'],qualified:['contacted','accepted'],accepted:['qualified']}},
 deal:{root:'crm-kanban-drag',card:'[data-crm-id]',zone:'[data-crm-stage]:not([data-crm-id])',id:'dealId',endpoint:'/office-test/crm/verkoopkansen/fase',next:{lead:['prospect','lost'],prospect:['opportunity','lost'],opportunity:['proposal','lost'],proposal:['negotiation','won','lost'],negotiation:['proposal','won','lost'],won:[],lost:['prospect']}}
};
Object.entries(configs).forEach(([type,cfg])=>{
 const root=document.getElementById(cfg.root);if(!root)return;
 const zones=[...root.querySelectorAll(cfg.zone)],cards=[...root.querySelectorAll(cfg.card)];
 const feedback=document.getElementById(type==='deal'?'crm-drag-feedback':'crm-board-feedback');
 const stage=el=>el.dataset[type==='lead'?'stage':'crmStage'];
 const id=el=>el.dataset[type==='lead'?'leadId':'crmId'];
 let moving=null,busy=false;
 const notify=message=>{if(feedback)feedback.textContent=message;};
 const clear=()=>zones.forEach(z=>{z.classList.remove('crm-board-drop-target');z.removeAttribute('data-drop-active');});
 const allowed=(card,target)=>!!card&&(cfg.next[stage(card)]||[]).includes(target);
 async function save(card,target){
  if(busy||!allowed(card,target))return;
  busy=true;notify('Fasewijziging opslaan...');
  const params=new URLSearchParams({csrf:root.dataset.csrf,stage:target});
  let url=cfg.endpoint;
  if(type==='lead')params.set('lead_id',id(card));
  else url+='?id='+encodeURIComponent(id(card));
  try{
   const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params,redirect:'follow'});
   if(type==='lead'){
    const data=await response.json().catch(()=>({}));
    if(!response.ok||data.ok!==true)throw Error(data.error||'Opslaan geweigerd (HTTP '+response.status+')');
   }else{
    if(!response.ok||!response.redirected||new URL(response.url).pathname!=='/office-test/crm/verkoopkansen')
     throw Error('Opslaan niet bevestigd (HTTP '+response.status+')');
   }
   window.location.reload();
  }catch(error){notify(error.message||'Opslaan mislukt. De fase is niet gewijzigd.');}
  finally{busy=false;moving=null;clear();}
 }
 cards.forEach(card=>{
  const targets=cfg.next[stage(card)]||[];
  card.draggable=targets.length>0;
  card.querySelectorAll('a').forEach(a=>a.draggable=false);
  card.addEventListener('dragstart',e=>{
   if(busy||!targets.length){e.preventDefault();return;}
   moving=card;e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',id(card));
   zones.forEach(z=>{if(allowed(card,stage(z)))z.classList.add('crm-board-drop-target');});
  });
  card.addEventListener('dragend',()=>{moving=null;clear();});
  const button=card.querySelector('.lead-move-button');
  button?.addEventListener('click',()=>save(card,card.querySelector('.lead-move-select')?.value));
 });
 zones.forEach(zone=>{
  zone.addEventListener('dragover',e=>{if(!busy&&allowed(moving,stage(zone))){e.preventDefault();e.dataTransfer.dropEffect='move';}});
  zone.addEventListener('drop',e=>{if(!busy&&allowed(moving,stage(zone))){e.preventDefault();const card=moving;moving=null;clear();save(card,stage(zone));}});
 });
});
})();