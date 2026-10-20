<?php
declare(strict_types=1);
// Office-native building-zone map; writes require the separately verified test gate.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://unpkg.com; script-src 'unsafe-inline' https://unpkg.com; img-src data: https://unpkg.com https://*.tile.openstreetmap.org; connect-src https://*.tile.openstreetmap.org; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') {http_response_code(405);header('Allow: GET');exit;}
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_NATIVE_PROJECTS_ENABLED')!=='enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')!=='enabled') {http_response_code(503);exit('Projectkaart niet beschikbaar.');}
$host=$_SERVER['HTTP_HOST']??'';
if (!is_string($host) || $host==='' || getenv('OFFICE_PUBLIC_HOST')!==$host
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {http_response_code(403);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!is_int($id)) {http_response_code(400);exit('Ongeldig projectnummer.');}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Project/OfficeNativeProjectAccess.php';
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingRepository.php';
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingZoneRepository.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) {header('Location: /office-test/inloggen',true,302);exit;}
  if (!(new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canManage($operator->operatorId,$id)) {
    throw new RuntimeException('Unauthorized project');
  }
} catch (InvalidArgumentException | RuntimeException $e) {
  http_response_code(404);exit('Projectlocatie niet beschikbaar.');
} catch (Throwable $e) {
  http_response_code(503);exit('Projectkaart tijdelijk niet beschikbaar.');
}
$createdZone=null;
$projectZones=[];
$buildings=[];
$selectedBuilding=null;
$createdId=filter_var($_GET['zone_created']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$buildingId=filter_var($_GET['building_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$zoneWriteEnabled=getenv('OFFICE_TEST_ZONE_WRITE_ENABLED')==='enabled'
  && getenv('OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED')==='enabled'
  && getenv('OFFICE_CRM_SESSION_MODE')==='enabled';
if ($zoneWriteEnabled) {
  try {
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingZoneReadiness.php';
    require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeZoneFormToken.php';
    \Brebo\Office\Buildings\OfficeBuildingZoneReadiness::requireReady($db);
    $buildingRepo=new \Brebo\Office\Buildings\OfficeBuildingRepository($db);
    $buildings=$buildingRepo->forProject((string)$id);
    if (count($buildings)===1 && !is_int($buildingId)) $buildingId=(int)$buildings[0]['id'];
    foreach ($buildings as $building) {
      if (is_int($buildingId) && (string)$building['id']===(string)$buildingId) $selectedBuilding=$building;
    }
    if ($selectedBuilding!==null) {
      $authorizedBuilding=static fn(string $candidate):bool => $candidate===(string)$buildingId
        && $buildingRepo->belongsToProject((string)$id,$candidate);
      $projectZones=(new \Brebo\Office\Buildings\OfficeBuildingZoneRepository($db))->forBuilding((string)$buildingId,$authorizedBuilding);
      foreach ($projectZones as $row) {
        if (is_int($createdId) && (string)$row['id']===(string)$createdId) $createdZone=$row;
      }
    }
  } catch (Throwable $e) {
    http_response_code(503);
    exit('Gebouwzones tijdelijk niet beschikbaar.');
  }
}
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
$existingZone=$projectZones[0]??null;
$latitude=is_array($existingZone) ? (float)$existingZone['latitude'] : null;
$longitude=is_array($existingZone) ? (float)$existingZone['longitude'] : null;
$startInputs='<label>Breedtegraad <input id="start-lat" type="number" min="-90" max="90" step="any" value="'.$escape($latitude).'"></label><label>Lengtegraad <input id="start-lon" type="number" min="-180" max="180" step="any" value="'.$escape($longitude).'"></label>';
if ($zoneWriteEnabled && $selectedBuilding!==null) {
  try {
    $zoneCsrf=\Brebo\Office\Inzet\OnSiteOfficeZoneFormToken::issue($operator,(string)$id,
      (string)$selectedBuilding['id'],getenv('OFFICE_TEST_ZONE_CSRF_SECRET') ?: '',time());
  } catch (Throwable $e) {http_response_code(503);exit('Zoneformulier niet beschikbaar.');}
}
$zoneList='<h2>Personeelszones van het gebouw</h2><ul>';
foreach ($projectZones as $zoneRow) {
  $zoneList.='<li>Zone '.$escape($zoneRow['id']).': '.$escape($zoneRow['zone_name']).' — '.$escape($zoneRow['radius_metres']).' meter (revisie '.$escape($zoneRow['revision']).')</li>';
}
$zoneList.='</ul>';
$createdNotice=$createdZone!==null ? '<p role="status">Zone '.$escape($createdZone['zone_name']).' opgeslagen en teruggelezen uit Office-testdatabase (zone '.$escape($createdZone['id']).', revisie '.$escape($createdZone['revision']).').</p>' : '';
$buildingOptions='<label for="building-select">Gebouw <select id="building-select" onchange="location.href=\'/office-test/inzet/projectkaart?id='.$id.'&building_id=\'+encodeURIComponent(this.value)"><option value="">Selecteer een gebouw</option>';
foreach ($buildings as $building) {
  $buildingOptions.='<option value="'.$escape($building['id']).'"'.($selectedBuilding!==null && (string)$selectedBuilding['id']===(string)$building['id']?' selected':'').'>'.$escape($building['building_name']).'</option>';
}
$buildingOptions.='</select></label>';
$saveForm=$zoneWriteEnabled && $selectedBuilding!==null ? '<form id="zone-save" method="post" action="/office-test/inzet/zone/aanmaken"><input type="hidden" name="form_token" value="'.$escape($zoneCsrf).'"><input type="hidden" name="project_id" value="'.$id.'"><input type="hidden" name="building_id" value="'.$escape($selectedBuilding['id']).'"><input type="hidden" name="zone_name" id="save-name"><input type="hidden" name="latitude" id="save-lat"><input type="hidden" name="longitude" id="save-lon"><input type="hidden" name="radius_metres" id="save-radius"><button id="save-zone" type="submit" disabled>Zone opslaan in testdatabase</button></form>' : '';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personeelszone · Office</title><style>body{font:16px system-ui;margin:2rem auto;max-width:900px;padding:1rem;color:#243244}section{padding:1.5rem;border:1px solid #ccd5df;border-radius:12px}#map{height:460px;background:#e8eef1;border:1px solid #ccd5df;border-radius:8px}label{display:block;margin:1rem 0}button,input{font:inherit;padding:.5rem}input[type=range]{width:min(100%,500px)}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><main><a href="/office-test/projecten/detail?id='.$id.'">← Project</a><section><h1>Personeelszone · project '.$id.'</h1><p>Gebruik een bestaande gebouwzone of vul een gecontroleerd middelpunt in. Kaarttegels worden pas na inschakelen geladen. Opslag uitsluitend op het geselecteerde gebouw.</p>'.$startInputs.'<button id="enable" type="button">Kaart inschakelen</button><label for="zone-name">Zonenaam <input id="zone-name" maxlength="160" value="Werkgebied" required></label><label>Straal: <output id="metres">150</output> meter <input id="radius" type="range" min="10" max="5000" step="10" value="150"></label><div id="map" aria-label="Personeelszonekaart"></div><p id="status" aria-live="polite">Kaart uitgeschakeld.</p><button id="preview" type="button" disabled>Toon zonegegevens (niet opslaan)</button><pre id="result">Geen gegevens opgeslagen.</pre>'.$buildingOptions.$createdNotice.$zoneList.$saveForm.'<small>Alleen testomgeving. Bij inschakelen ontvangt de kaartprovider de opgevraagde kaartregio.</small></section></main><script>
(()=>{
 const initial=null;
 let center=null,map,circle,marker;
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
  enable.disabled=true;preview.disabled=false;const save=document.getElementById("save-zone");if(save)save.disabled=false;render();
 }
 enable.addEventListener("click",()=>{
  const latInput=document.getElementById("start-lat"),lonInput=document.getElementById("start-lon");
  const lat=Number(latInput.value),lng=Number(lonInput.value);
  if(!latInput.value.trim()||!lonInput.value.trim()||!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180){status.textContent="Vul eerst een geldig, gecontroleerd middelpunt in.";return;}
  center={lat,lng};enable.disabled=true;status.textContent="Kaart wordt geladen...";
  const css=document.createElement("link");css.rel="stylesheet";css.href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";document.head.appendChild(css);
  const script=document.createElement("script");script.src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
  script.onload=init;script.onerror=()=>{enable.disabled=false;status.textContent="Kaartbibliotheek niet beschikbaar.";};
  document.head.appendChild(script);
 });
 const saveForm=document.getElementById("zone-save");if(saveForm)saveForm.addEventListener("submit",event=>{const name=zoneName.value.trim();if(!center||!name||new TextEncoder().encode(name).length>160||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180){event.preventDefault();status.textContent="Ongeldige zonegegevens.";return;}document.getElementById("save-name").value=name;document.getElementById("save-lat").value=center.lat.toFixed(7);document.getElementById("save-lon").value=center.lng.toFixed(7);document.getElementById("save-radius").value=radius.value;});
 preview.addEventListener("click",()=>{const name=zoneName.value.trim();if(!name||new TextEncoder().encode(name).length>160){status.textContent="Ongeldige zonenaam (maximaal 160 bytes).";return;}document.getElementById("result").textContent=JSON.stringify({building_id:'.($selectedBuilding!==null ? json_encode((string)$selectedBuilding['id'],JSON_THROW_ON_ERROR) : 'null').',zone_name:name,latitude:center.lat,longitude:center.lng,radius_metres:Number(radius.value),saved:false},null,2);});
})();
</script></body></html>';
