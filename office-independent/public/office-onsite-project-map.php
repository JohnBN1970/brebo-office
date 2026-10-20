<?php
declare(strict_types=1);
// Read-only Office-native map entry; no writes, no Drupal and no third-party tiles.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://unpkg.com; script-src 'unsafe-inline' https://unpkg.com; img-src data: https://*.tile.openstreetmap.org; connect-src https://*.tile.openstreetmap.org; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') {http_response_code(405);header('Allow: GET');exit;}
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_NATIVE_PROJECTS_ENABLED')!=='enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')!=='enabled'
  || getenv('OFFICE_TEST_PROJECT_MAP_COORDINATES_VERIFIED')!=='enabled') {http_response_code(503);exit('Projectkaart niet beschikbaar.');}
$host=$_SERVER['HTTP_HOST']??'';
if (!is_string($host) || $host==='' || getenv('OFFICE_PUBLIC_HOST')!==$host
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {http_response_code(403);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!is_int($id)) {http_response_code(400);exit('Ongeldig projectnummer.');}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeProjectMapSeed.php';
  require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeProjectMapRepository.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) {header('Location: /office-test/inloggen',true,302);exit;}
  $seed=(new \Brebo\Office\Inzet\OnSiteOfficeProjectMapRepository($db))->forManager($operator->operatorId,$id);
} catch (InvalidArgumentException | RuntimeException $e) {
  http_response_code(404);exit('Projectlocatie niet beschikbaar.');
} catch (Throwable $e) {
  http_response_code(503);exit('Projectkaart tijdelijk niet beschikbaar.');
}
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
$latitude=json_encode($seed['latitude'],JSON_THROW_ON_ERROR);
$longitude=json_encode($seed['longitude'],JSON_THROW_ON_ERROR);
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personeelszone · Office</title><style>body{font:16px system-ui;margin:2rem auto;max-width:900px;padding:1rem;color:#243244}section{padding:1.5rem;border:1px solid #ccd5df;border-radius:12px}#map{height:460px;background:#e8eef1;border:1px solid #ccd5df;border-radius:8px}label{display:block;margin:1rem 0}button,input{font:inherit;padding:.5rem}input[type=range]{width:min(100%,500px)}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><main><a href="/office-test/projecten/detail?id='.$id.'">← Project</a><section><h1>Personeelszone · project '.$id.'</h1><p>Startpunt uit geautoriseerde Office-projectlocatie. Kaarttegels worden pas na toestemming geladen. Geen opslag.</p><button id="enable" type="button">Kaart inschakelen</button><label for="zone-name">Zonenaam <input id="zone-name" maxlength="160" value="Werkgebied" required></label><label>Straal: <output id="metres">150</output> meter <input id="radius" type="range" min="10" max="5000" step="10" value="150"></label><div id="map" aria-label="Personeelszonekaart"></div><p id="status" aria-live="polite">Kaart uitgeschakeld.</p><button id="preview" type="button" disabled>Toon zonegegevens (niet opslaan)</button><pre id="result">Geen gegevens opgeslagen.</pre><small>Alleen testomgeving. Projectlocatie blijft op de Office-server; bij inschakelen ontvangt de kaartprovider wel de opgevraagde kaartregio.</small></section></main><script>
(()=>{
 const initial={lat:'.$latitude.',lng:'.$longitude.'};
 let center={...initial},map,circle,marker;
 const enable=document.getElementById("enable"),radius=document.getElementById("radius"),status=document.getElementById("status"),preview=document.getElementById("preview");
 const metres=document.getElementById("metres"),zoneName=document.getElementById("zone-name");
 radius.addEventListener("input",()=>{metres.textContent=radius.value;if(circle)circle.setRadius(Number(radius.value));});
 function render(){circle.setLatLng(center);marker.setLatLng(center);status.textContent="Middelpunt: "+center.lat.toFixed(7)+", "+center.lng.toFixed(7);}
 function init(){
  map=L.map("map",{scrollWheelZoom:false}).setView([center.lat,center.lng],16);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"&copy; OpenStreetMap contributors"}).addTo(map);
  circle=L.circle([center.lat,center.lng],{radius:Number(radius.value),color:"#175cd3",fillOpacity:.15}).addTo(map);
  marker=L.marker([center.lat,center.lng],{draggable:true}).addTo(map);
  map.on("click",event=>{center=event.latlng;render();});
  marker.on("dragend",()=>{center=marker.getLatLng();render();});
  enable.disabled=true;preview.disabled=false;render();
 }
 enable.addEventListener("click",()=>{
  enable.disabled=true;status.textContent="Kaart wordt geladen...";
  const css=document.createElement("link");css.rel="stylesheet";css.href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";document.head.appendChild(css);
  const script=document.createElement("script");script.src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
  script.onload=init;script.onerror=()=>{enable.disabled=false;status.textContent="Kaartbibliotheek niet beschikbaar.";};
  document.head.appendChild(script);
 });
 preview.addEventListener("click",()=>{const name=zoneName.value.trim();if(!name||new TextEncoder().encode(name).length>160){status.textContent="Ongeldige zonenaam (maximaal 160 bytes).";return;}document.getElementById("result").textContent=JSON.stringify({project_id:'.$id.',zone_name:name,latitude:center.lat,longitude:center.lng,radius_metres:Number(radius.value),saved:false},null,2);});
})();
</script></body></html>';
