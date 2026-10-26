"use strict";
// A different invitation or a restored browser page must be checked again.
addEventListener("hashchange",()=>location.reload());
addEventListener("pageshow",event=>{if(event.persisted)location.reload();});
(async()=>{
  const fragment=new URLSearchParams(location.hash.slice(1));
  const token=fragment.get("activation")||fragment.get("token")||"";
  const expected=document.querySelector('meta[name="onsite-invitation"]')?.content||"";
  const root=document.documentElement;
  const message=document.getElementById("gate-message");
  function fail(text){
    if(expected){location.replace("./?action=locked#invalid");return;}
    if(message)message.textContent=text;
  }
  if(!/^[A-Za-z0-9_-]{32,128}$/.test(token)){
    fail("Deze pagina is alleen toegankelijk via je persoonlijke OnSite-uitnodiging. Vraag bij een ontbrekende of verlopen link een nieuwe uitnodiging aan BREBO.");return;
  }
  if(expected){
    const digest=await crypto.subtle.digest("SHA-256",new TextEncoder().encode(token));
    const hash=Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,"0")).join("");
    if(hash===expected){
      root.classList.remove("invitation-pending");
      document.getElementById("activation-panel").hidden=false;
      document.getElementById("activate-link").href="brebo-onsite://activate?token="+encodeURIComponent(token)+"&start=1";
      return;
    }
  }
  if(message)message.textContent="Je persoonlijke uitnodiging wordt gecontroleerd…";
  try{
    const response=await fetch("./?action=unlock",{method:"POST",credentials:"same-origin",cache:"no-store",headers:{"Content-Type":"application/json"},body:JSON.stringify({activation_token:token})});
    const data=await response.json();
    if(response.ok&&data.ok){location.reload();return;}
    fail("Deze uitnodiging is ongeldig, verlopen of al gebruikt. Vraag BREBO om een nieuwe persoonlijke uitnodiging.");
  }catch(_){fail("Je uitnodiging kan nu niet worden gecontroleerd. Controleer je verbinding en open je link opnieuw.");}
})();
