"use strict";
const iosButton=document.getElementById("ios-guide-button");
const iosGuide=document.getElementById("ios-guide");
iosButton.addEventListener("click",()=>{const open=iosButton.getAttribute("aria-expanded")==="true";iosButton.setAttribute("aria-expanded",String(!open));iosGuide.hidden=open;if(!open)iosGuide.scrollIntoView({block:"nearest",behavior:matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"});});
const fragment=new URLSearchParams(location.hash.slice(1));
const query=new URLSearchParams(location.search);
const token=fragment.get("activation")||fragment.get("token")||query.get("activation")||"";
if(token&&/^[A-Za-z0-9_-]{20,256}$/.test(token)){document.getElementById("activation-panel").hidden=false;document.getElementById("activate-link").href="brebo-onsite://activate?token="+encodeURIComponent(token);document.getElementById("invitation-note").textContent="Je persoonlijke uitnodiging is ingelezen. Installeer eerst de app en activeer daarna dit toestel.";}
if(query.has("activation")||fragment.has("activation")||fragment.has("token")){try{history.replaceState(null,"",location.pathname+(token&&/^[A-Za-z0-9_-]{20,256}$/.test(token)?"#activation="+encodeURIComponent(token):""));}catch(_){}}
