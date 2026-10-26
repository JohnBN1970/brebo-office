"use strict";
const b=document.getElementById("ios-guide-button"),g=document.getElementById("ios-guide");
b.addEventListener("click",()=>{const open=b.getAttribute("aria-expanded")==="true";b.setAttribute("aria-expanded",String(!open));g.hidden=open;});
// Keep the personal invitation fragment while navigating within this page.
document.querySelectorAll('a[href^="#"]').forEach(a=>a.addEventListener("click",event=>{
 const target=document.getElementById(a.getAttribute("href").slice(1));
 if(target){event.preventDefault();target.scrollIntoView({behavior:matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"});}
}));
