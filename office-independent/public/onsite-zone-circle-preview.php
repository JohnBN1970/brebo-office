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
<p class="note">Geografische testweergave met echte WGS84-coördinaten en een straal in meters. Zonder kaarttegels of opslag; de achtergrond blijft schematisch.</p>
<label for="lat">Breedtegraad (test)</label><input id="lat" type="number" min="-90" max="90" step="0.0000001" value="51.5000000">
<label for="lon">Lengtegraad (test)</label><input id="lon" type="number" min="-180" max="180" step="0.0000001" value="4.3000000">
<label for="project">Office-projectnummer (test)</label><input id="project" inputmode="numeric" placeholder="Bijvoorbeeld 17">
<label for="name">Zonenaam</label><input id="name" maxlength="160" value="Werkgebied">
<label for="radius">Straal: <output id="meters">150</output> meter</label><input id="radius" type="range" min="10" max="5000" step="10" value="150">
<p class="note">Klik op het vlak om het middelpunt geografisch te verplaatsen (weergave ± 2 km rondom de oorspronkelijke positie). Pijltjestoetsen verplaatsen circa 10 meter per stap.</p>
<div id="map" class="map" role="application" tabindex="0" aria-label="Schematische cirkelbediening"><div id="circle" class="circle"></div><div id="center" class="center"></div></div>
<p class="note" id="position">Middelpunt: 51.5000000, 4.3000000</p>
<button id="preview" type="button">Toon voorbeeldgegevens</button>
<pre class="output" id="result" aria-live="polite">Nog geen gegevens geselecteerd.</pre>
<p class="note">Opslaan is bewust uitgeschakeld totdat projectautorisatie, CSRF en de Office-kaartkoppeling gereed zijn.</p>
</main><script>
(() => {
  const map=document.getElementById('map'),circle=document.getElementById('circle'),center=document.getElementById('center');
  const radius=document.getElementById('radius'),meters=document.getElementById('meters'),position=document.getElementById('position');
  const latInput=document.getElementById('lat'),lonInput=document.getElementById('lon');
  const spanMetres=4000;
  let lat=51.5,lon=4.3,originLat=lat,originLon=lon;
  const radians=Math.PI/180;
  function metresPerDegreeLon(latitude){return 111320*Math.cos(latitude*radians)}
  function valid(latitude,longitude){return Number.isFinite(latitude)&&Number.isFinite(longitude)&&Math.abs(latitude)<=90&&Math.abs(longitude)<=180}
  function render(){
    const scale=Math.min(map.clientWidth,map.clientHeight)/spanMetres;
    const diameter=2*Number(radius.value)*scale;
    const x=50+100*(lon-originLon)*metresPerDegreeLon(originLat)/spanMetres;
    const y=50-100*(lat-originLat)*111320/spanMetres;
    circle.style.width=diameter+'px';circle.style.height=diameter+'px';
    for(const el of [circle,center]){el.style.left=x+'%';el.style.top=y+'%'}
    meters.textContent=radius.value;
    position.textContent='Middelpunt: '+lat.toFixed(7)+', '+lon.toFixed(7)+' (WGS84)';
    latInput.value=lat.toFixed(7);lonInput.value=lon.toFixed(7);
  }
  map.addEventListener('click',event=>{
    const rect=map.getBoundingClientRect();
    const east=((event.clientX-rect.left)/rect.width-.5)*spanMetres;
    const north=(.5-(event.clientY-rect.top)/rect.height)*spanMetres;
    const nextLat=originLat+north/111320;
    const nextLon=originLon+east/metresPerDegreeLon(originLat);
    if(valid(nextLat,nextLon)){lat=nextLat;lon=nextLon;render()}
  });
  map.addEventListener('keydown',event=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
    event.preventDefault();
    const nextLat=lat+(event.key==='ArrowUp'?10:event.key==='ArrowDown'?-10:0)/111320;
    const nextLon=lon+(event.key==='ArrowRight'?10:event.key==='ArrowLeft'?-10:0)/metresPerDegreeLon(lat);
    if(valid(nextLat,nextLon)){lat=nextLat;lon=nextLon;render()}
  });
  function coordinateChange(){
    const nextLat=Number(latInput.value),nextLon=Number(lonInput.value);
    if(!valid(nextLat,nextLon)||latInput.value===''||lonInput.value==='')return;
    lat=nextLat;lon=nextLon;originLat=lat;originLon=lon;render();
  }
  latInput.addEventListener('change',coordinateChange);
  lonInput.addEventListener('change',coordinateChange);
  radius.addEventListener('input',render);window.addEventListener('resize',render);
  document.getElementById('preview').addEventListener('click',()=>{
    document.getElementById('result').textContent=JSON.stringify({project_id:document.getElementById('project').value,zone_name:document.getElementById('name').value,latitude:lat,longitude:lon,radius_metres:Number(radius.value),saved:false},null,2);
  });render();
})();
</script></body></html>
