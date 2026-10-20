<?php
declare(strict_types=1);

/**
 * Static preview of the Office personnel-zone circle editor.
 * No persistence, no session cookies, no external tiles or Drupal data.
 * Not connected to a public Office route.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Personeelszone - Office ontwerp</title>
<style>
:root{font:16px system-ui,sans-serif;color:#1d2939;background:#f5f7fa}
body{max-width:960px;margin:2rem auto;padding:0 1rem}
main{background:#fff;border:1px solid #d0d5dd;border-radius:14px;padding:1.5rem}
label{display:block;margin:1rem 0 .35rem;font-weight:650}
input{font:inherit;padding:.6rem;border:1px solid #98a2b3;border-radius:7px;max-width:100%;box-sizing:border-box}
input[type=range]{width:100%}
.map{position:relative;height:390px;border:2px solid #98a2b3;border-radius:12px;overflow:hidden;background:repeating-linear-gradient(0deg,#eaf1ec 0 39px,#dae6dd 40px),repeating-linear-gradient(90deg,transparent 0 39px,#d1ddd4 40px);cursor:crosshair;touch-action:manipulation}
.map:focus-visible{outline:3px solid #175cd3}
.circle{position:absolute;left:50%;top:50%;border:2px solid #175cd3;background:#175cd326;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none}
.center{position:absolute;left:50%;top:50%;width:12px;height:12px;background:#175cd3;border:2px solid white;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none}
.note{color:#475467;font-size:.9rem}
.output{white-space:pre-wrap;background:#f2f4f7;padding:1rem;border-radius:8px}
</style></head><body><main>
<h1>Personeelszone instellen</h1>
<p class="note">Ontwerpvoorbeeld zonder echte kaarttegels of opslag. De ondergrond is schematisch, geen geografische kaart.</p>
<label for="project">Office-projectnummer (test)</label><input id="project" inputmode="numeric" placeholder="Bijvoorbeeld 17">
<label for="name">Zonenaam</label><input id="name" maxlength="160" value="Werkgebied">
<label for="radius">Straal: <output id="meters">150</output> meter</label><input id="radius" type="range" min="10" max="5000" step="10" value="150">
<p class="note">Klik op het vlak om het middelpunt te verplaatsen. Pijltjestoetsen werken wanneer het vlak focus heeft.</p>
<div id="map" class="map" role="application" tabindex="0" aria-label="Schematische cirkelbediening"><div id="circle" class="circle"></div><div id="center" class="center"></div></div>
<p class="note" id="position">Middelpunt: 50%, 50%</p>
<button id="preview" type="button">Toon voorbeeldgegevens</button>
<pre class="output" id="result" aria-live="polite">Nog geen gegevens geselecteerd.</pre>
<p class="note">Opslaan is bewust uitgeschakeld totdat projectautorisatie, CSRF en de Office-kaartkoppeling gereed zijn.</p>
</main><script>
(() => {
  const map=document.getElementById('map'),circle=document.getElementById('circle'),center=document.getElementById('center');
  const radius=document.getElementById('radius'),meters=document.getElementById('meters'),position=document.getElementById('position');
  let x=50,y=50;
  function render(){
    const diameter=Math.min(map.clientWidth,map.clientHeight)*Number(radius.value)/5000;
    circle.style.width=diameter+'px';circle.style.height=diameter+'px';
    for(const el of [circle,center]){el.style.left=x+'%';el.style.top=y+'%'}
    meters.textContent=radius.value;
    position.textContent='Middelpunt: '+x.toFixed(1)+'%, '+y.toFixed(1)+'% (schematisch)';
  }
  map.addEventListener('click',event=>{
    const rect=map.getBoundingClientRect();x=Math.max(0,Math.min(100,100*(event.clientX-rect.left)/rect.width));y=Math.max(0,Math.min(100,100*(event.clientY-rect.top)/rect.height));render();
  });
  map.addEventListener('keydown',event=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
    event.preventDefault();x=Math.max(0,Math.min(100,x+(event.key==='ArrowRight'?1:event.key==='ArrowLeft'?-1:0)));
    y=Math.max(0,Math.min(100,y+(event.key==='ArrowDown'?1:event.key==='ArrowUp'?-1:0)));render();
  });
  radius.addEventListener('input',render);window.addEventListener('resize',render);
  document.getElementById('preview').addEventListener('click',()=>{
    document.getElementById('result').textContent=JSON.stringify({project_id:document.getElementById('project').value,zone_name:document.getElementById('name').value,radius_metres:Number(radius.value),schematic_center_percent:{x,y},saved:false},null,2);
  });render();
})();
</script></body></html>
