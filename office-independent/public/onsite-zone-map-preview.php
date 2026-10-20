<?php
declare(strict_types=1);
/**
 * Standalone development-only map prototype. No project data, cookies, or persistence.
 * NOT mounted in Office navigation; external OpenStreetMap tiles are requested only
 * after the user explicitly enables them.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://unpkg.com; script-src 'unsafe-inline' https://unpkg.com; img-src data: https://*.tile.openstreetmap.org; connect-src https://*.tile.openstreetmap.org; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Office - personeelszone kaartprototype</title>
<style>
body{font:16px system-ui,sans-serif;max-width:980px;margin:2rem auto;padding:1rem;color:#243244}
label{display:block;margin:.8rem 0 .3rem;font-weight:600}input{font:inherit;padding:.4rem}
#map{height:480px;background:#e8eef1;border:1px solid #abb7c3;border-radius:10px}
#radius{width:min(100%,500px)}button{padding:.7rem 1rem;font:inherit;cursor:pointer}
small{color:#52606d}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f3f5f8;padding:1rem}
</style></head><body><h1>Personeelszone - kaartprototype</h1>
<p>Fictieve testlocatie. Schakel de kaart zelf in; daarna worden kaarttegels geladen bij OpenStreetMap. Er worden geen Office-projectgegevens verstuurd.</p>
<button id="enable" type="button">Kaart laden</button>
<label for="name">Zonenaam</label><input id="name" value="Werkgebied" maxlength="160">
<label for="radius">Straal: <output id="metres">150</output> meter</label>
<input id="radius" type="range" min="10" max="5000" step="10" value="150">
<div id="map" role="region" aria-label="Kaart van personeelszone"></div>
<p id="coordinates" aria-live="polite">Kaart nog niet geladen.</p>
<button id="preview" type="button" disabled>Toon coördinaten (niet opslaan)</button>
<pre id="result">Geen gegevens opgeslagen.</pre>
<small>Prototype: geen projectautorisatie, geen opslag en geen productie-integratie. Kaartdata © OpenStreetMap-bijdragers.</small>
<script>
(()=>{
 const enable=document.getElementById('enable'),radius=document.getElementById('radius');
 const metres=document.getElementById('metres'),coordinates=document.getElementById('coordinates');
 const preview=document.getElementById('preview'),result=document.getElementById('result');
 let map,circle,marker,center={lat:51.5,lng:4.3};
 function render(){
   if(!circle)return;
   circle.setLatLng(center);circle.setRadius(Number(radius.value));marker.setLatLng(center);
   metres.textContent=radius.value;
   coordinates.textContent='Middelpunt: '+center.lat.toFixed(7)+', '+center.lng.toFixed(7);
 }
 function init(){
   map=L.map('map',{scrollWheelZoom:false}).setView([center.lat,center.lng],16);
   L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
     maxZoom:19,attribution:'&copy; OpenStreetMap contributors'
   }).addTo(map);
   circle=L.circle([center.lat,center.lng],{radius:Number(radius.value),color:'#175cd3',fillOpacity:.15}).addTo(map);
   marker=L.marker([center.lat,center.lng],{draggable:true}).addTo(map);
   map.on('click',event=>{center=event.latlng;render()});
   marker.on('dragend',()=>{center=marker.getLatLng();render()});
   radius.addEventListener('input',render);
   preview.disabled=false;enable.disabled=true;render();
 }
 enable.addEventListener('click',()=>{
   enable.disabled=true;enable.textContent='Kaart wordt geladen...';
   const css=document.createElement('link');css.rel='stylesheet';css.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';document.head.appendChild(css);
   const script=document.createElement('script');script.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
   script.onload=init;
   script.onerror=()=>{enable.disabled=false;enable.textContent='Kaart opnieuw laden';coordinates.textContent='Kaartbibliotheek kon niet worden geladen.'};
   document.head.appendChild(script);
 });
 preview.addEventListener('click',()=>{
   result.textContent=JSON.stringify({zone_name:document.getElementById('name').value,
     latitude:center.lat,longitude:center.lng,radius_metres:Number(radius.value),saved:false},null,2);
 });
})();
</script></body></html>
