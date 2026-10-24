<?php
declare(strict_types=1);
// Office-native building-zone map; writes require the separately verified test gate.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://unpkg.com; script-src 'unsafe-inline' https://unpkg.com; img-src data: https://unpkg.com https://service.pdok.nl; connect-src https://service.pdok.nl https://api.pdok.nl https://nominatim.openstreetmap.org; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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
  require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeZoneProjectAccess.php';
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingRepository.php';
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingZoneRepository.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) {header('Location: /office-test/inloggen',true,302);exit;}
  if (!(new \Brebo\Office\Inzet\OnSiteOfficeZoneProjectAccess($db))->canManage($operator->operatorId,$id)) {
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
$updatedId=filter_var($_GET['zone_updated']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
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
        if ((is_int($createdId) && (string)$row['id']===(string)$createdId) || (is_int($updatedId) && (string)$row['id']===(string)$updatedId)) $createdZone=$row;
      }
    }
  } catch (Throwable $e) {
    http_response_code(503);
    exit('Gebouwzones tijdelijk niet beschikbaar.');
  }
}
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
$existingZone=$createdZone??($projectZones[0]??null);
$existingRadius=is_array($existingZone) ? max(10,min(1000,(int)$existingZone['radius_metres'])) : 150;
$existingName=is_array($existingZone) ? (string)$existingZone['zone_name'] : 'Werkgebied';
$latitude=is_array($existingZone) ? (float)$existingZone['latitude'] : null;
$longitude=is_array($existingZone) ? (float)$existingZone['longitude'] : null;
$startInputs='<label>Breedtegraad <input id="start-lat" type="number" min="-90" max="90" step="any" value="'.$escape($latitude).'"></label><label>Lengtegraad <input id="start-lon" type="number" min="-180" max="180" step="any" value="'.$escape($longitude).'"></label>';
if ($zoneWriteEnabled && $selectedBuilding!==null) {
  try {
    $zoneCsrf=\Brebo\Office\Inzet\OnSiteOfficeZoneFormToken::issue($operator,(string)$id,
      (string)$selectedBuilding['id'],getenv('OFFICE_TEST_ZONE_CSRF_SECRET') ?: '',time());
  } catch (Throwable $e) {http_response_code(503);exit('Zoneformulier niet beschikbaar.');}
}
$zoneList='<h2>Personeelszones van het gebouw</h2><p>Opgeslagen zones zijn hieronder zichtbaar. Selecteer een zone en schakel de kaart in om deze te bekijken of te bewerken.</p><ul>';
foreach ($projectZones as $zoneRow) {
  $zoneList.='<li>Zone '.$escape($zoneRow['id']).': '.$escape($zoneRow['zone_name']).' — '.$escape($zoneRow['radius_metres']).' meter (revisie '.$escape($zoneRow['revision']).')</li>';
}
$zoneList.='</ul>';
$zoneOptions='<option value="">Nieuwe zone</option>';
foreach ($projectZones as $zoneRow) {
  $zoneOptions.='<option value="'.$escape($zoneRow['id']).'">'.$escape($zoneRow['zone_name']).' (revisie '.$escape($zoneRow['revision']).')</option>';
}
$zoneSelector='<label for="saved-zone">Zone bekijken <select id="saved-zone">'.$zoneOptions.'</select></label><p id="zone-view-mode">Selecteer een bestaande zone en schakel de kaart in om deze te bewerken.</p>';
$zoneData=[];
foreach ($projectZones as $zoneRow) {
  $zoneData[]=['id'=>(string)$zoneRow['id'],'name'=>(string)$zoneRow['zone_name'],'lat'=>(float)$zoneRow['latitude'],'lng'=>(float)$zoneRow['longitude'],'radius'=>(float)$zoneRow['radius_metres'],'revision'=>(int)$zoneRow['revision']];
}
$zoneDataJson=json_encode($zoneData,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);

$createdNotice=$createdZone!==null ? '<p role="status">Zone '.$escape($createdZone['zone_name']).' opgeslagen en teruggelezen uit Office-testdatabase (zone '.$escape($createdZone['id']).', revisie '.$escape($createdZone['revision']).').</p>' : '';
$buildingOptions='<label for="building-select">Gebouw <select id="building-select" onchange="location.href=\'/office-test/inzet/projectkaart?id='.$id.'&building_id=\'+encodeURIComponent(this.value)"><option value="">Selecteer een gebouw</option>';
foreach ($buildings as $building) {
  $buildingOptions.='<option value="'.$escape($building['id']).'"'.($selectedBuilding!==null && (string)$selectedBuilding['id']===(string)$building['id']?' selected':'').'>'.$escape($building['building_name']).'</option>';
}
$buildingOptions.='</select></label>';
$updateForm=$zoneWriteEnabled && $selectedBuilding!==null ? '<form id="zone-update" method="post" action="/office-test/inzet/zone/bewerken"><input type="hidden" name="form_token" value="'.$escape($zoneCsrf).'"><input type="hidden" name="project_id" value="'.$id.'"><input type="hidden" name="building_id" value="'.$escape($selectedBuilding['id']).'"><input type="hidden" name="zone_id" id="update-id"><input type="hidden" name="revision" id="update-revision"><input type="hidden" name="zone_name" id="update-name"><input type="hidden" name="latitude" id="update-lat"><input type="hidden" name="longitude" id="update-lon"><input type="hidden" name="radius_metres" id="update-radius"><button id="update-zone" type="submit" disabled>Wijzigen inschakelen</button></form>' : '';
$saveForm=$zoneWriteEnabled && $selectedBuilding!==null ? '<form id="zone-save" method="post" action="/office-test/inzet/zone/aanmaken"><input type="hidden" name="form_token" value="'.$escape($zoneCsrf).'"><input type="hidden" name="project_id" value="'.$id.'"><input type="hidden" name="building_id" value="'.$escape($selectedBuilding['id']).'"><input type="hidden" name="zone_name" id="save-name"><input type="hidden" name="latitude" id="save-lat"><input type="hidden" name="longitude" id="save-lon"><input type="hidden" name="radius_metres" id="save-radius"><button id="save-zone" type="submit" disabled>Zone opslaan in testdatabase</button></form>' : '';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personeelszone · Office</title><style>body{font:16px system-ui;margin:2rem auto;max-width:900px;padding:1rem;color:#243244}section{padding:1.5rem;border:1px solid #ccd5df;border-radius:12px}#map{height:460px;background:#e8eef1;border:1px solid #ccd5df;border-radius:8px}label{display:block;margin:1rem 0}button,input{font:inherit;padding:.5rem}input[type=range]{width:min(100%,500px)}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><main><a href="/office-test/projecten/detail?id='.$id.'">← Project</a><section><h1>Personeelszone · project '.$id.'</h1><p><a href="/office-test/inzet/aanwezigheid?id='.$id.'">Aanwezigheidscontrole bekijken</a></p><p>Gebruik een bestaande gebouwzone of vul een gecontroleerd middelpunt in. Kaarttegels worden pas na inschakelen geladen. Opslag uitsluitend op het geselecteerde gebouw.</p><label for="address-search">Zoek gebouwadres <input id="address-search" type="search" maxlength="240" placeholder="Straat, huisnummer, postcode en plaats" autocomplete="street-address"></label><button id="find-address" type="button">Zoek adres (NL/BE)</button><p id="address-result" role="status" aria-live="polite">Controleer de gevonden locatie op de kaart voordat je opslaat.</p>'.$startInputs.'<button id="enable" type="button">Kaart inschakelen</button><label for="zone-name">Zonenaam <input id="zone-name" maxlength="160" value="'.$escape($existingName).'" required></label><label>Straal: <output id="metres">'.$escape($existingRadius).'</output> meter <input id="radius" type="range" min="10" max="1000" step="10" value="'.$escape($existingRadius).'"></label><div id="map" aria-label="Personeelszonekaart"></div><p id="status" aria-live="polite">Kaart uitgeschakeld.</p><button id="preview" type="button" disabled>Toon zonegegevens (niet opslaan)</button><pre id="result">Geen gegevens opgeslagen.</pre>'.$buildingOptions.$createdNotice.$zoneSelector.$zoneList.$saveForm.$updateForm.'<small>Alleen testomgeving. Adreszoekopdrachten gaan naar PDOK (NL) of OpenStreetMap Nominatim (BE). Bij inschakelen ontvangt de kaartprovider de opgevraagde kaartregio.</small></section></main><script>
(()=>{
 const zones='.$zoneDataJson.';
 const addressSearch=document.getElementById("address-search"),addressResult=document.getElementById("address-result"),findAddress=document.getElementById("find-address");
 let center=null,map,circle,marker,editing=false;
 const zoneSelect=document.getElementById("saved-zone");
 const activeZone=()=>zones.find(z=>z.id===zoneSelect.value)||null;
 const canMove=()=>!activeZone()||editing;
 const updateButton=document.getElementById("update-zone");
 const enable=document.getElementById("enable"),radius=document.getElementById("radius"),status=document.getElementById("status"),preview=document.getElementById("preview");
 const metres=document.getElementById("metres"),zoneName=document.getElementById("zone-name");
 radius.addEventListener("input",()=>{metres.textContent=radius.value;if(circle)circle.setRadius(Number(radius.value));});
 function render(){circle.setLatLng(center);marker.setLatLng(center);status.textContent="Middelpunt: "+center.lat.toFixed(7)+", "+center.lng.toFixed(7);}
 function selectZone(){
  const z=activeZone(),save=document.getElementById("save-zone");
  editing=false;
  if(z){
   center={lat:z.lat,lng:z.lng};
   document.getElementById("start-lat").value=z.lat;
   document.getElementById("start-lon").value=z.lng;
   zoneName.value=z.name;
   radius.value=Math.max(10,Math.min(1000,Math.round(z.radius/10)*10));
   metres.textContent=radius.value;
   if(map){map.setView([z.lat,z.lng],16);circle.setLatLng(center);circle.setRadius(z.radius);marker.setLatLng(center);render();}
  }
  document.getElementById("zone-view-mode").textContent=z?"Bestaande zone geselecteerd. Klik op Wijzigen inschakelen om de zone te bewerken.":"Nieuwe zone aanmaken.";
  radius.disabled=!!z;
  zoneName.readOnly=!!z;
  if(save)save.disabled=!map||!!z;
  if(updateButton){updateButton.disabled=!map||!z;updateButton.textContent="Wijzigen inschakelen";}
 }
 zoneSelect.addEventListener("change",selectZone);
 findAddress.addEventListener("click",async()=>{
  const query=addressSearch.value.trim();
  if(query.length<5){addressResult.textContent="Vul straat, huisnummer en plaats of postcode in.";return;}

  if(activeZone()&&!editing){addressResult.textContent="Schakel eerst Wijzigen in om een bestaande zone te verplaatsen.";return;}
  findAddress.disabled=true;addressResult.textContent="Adres zoeken...";
  try{
   const belgian=/\b(?:belgi[eë]|belgium|kalmthout)\b/i.test(query)||/\b[1-9][0-9]{3}\s+[a-zà-ÿ][a-zà-ÿ -]+\s*(?:belgi[eë])?\b/i.test(query);
   if(belgian){
    const postal=(query.match(/\b[1-9][0-9]{3}\b/)||[])[0];
    const number=(query.match(/\b([0-9]{1,4})\b/)||[])[1];
    if(!postal||!number){addressResult.textContent="Vul voor België ook postcode en huisnummer in. De zone is niet verplaatst.";return;}
    const url="https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=be&q="+encodeURIComponent(query+", België");
    const response=await fetch(url,{method:"GET",mode:"cors",credentials:"omit",cache:"no-store"});
    if(!response.ok)throw new Error("Belgische adreszoekdienst niet beschikbaar");
    const results=await response.json();
    const matches=Array.isArray(results)?results.filter(x=>String(x.address?.postcode||"").trim()===postal&&String(x.address?.house_number||"").trim()===number&&["house","building","residential"].includes(String(x.type||"").toLowerCase())||String(x.address?.postcode||"").trim()===postal&&String(x.address?.house_number||"").trim()===number&&x.addresstype==="building"):[];
    if(matches.length!==1){addressResult.textContent="Geen eenduidig Belgisch huisadres gevonden. Zone niet verplaatst; controleer het adres.";return;}
    const match=matches[0],lat=Number(match.lat),lng=Number(match.lon);
    if(!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180)throw new Error("Ongeldige locatie");
    center={lat,lng};document.getElementById("start-lat").value=lat.toFixed(7);document.getElementById("start-lon").value=lng.toFixed(7);
    addressResult.textContent="Gevonden (OpenStreetMap): "+String(match.display_name||query)+". Controleer de locatie op de kaart voordat je opslaat.";
    if(map){map.setView([lat,lng],17);render();}else{status.textContent="Adres gevonden. Klik op Kaart inschakelen om de zone te controleren.";}
    return;
   }
   const url="https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?q="+encodeURIComponent(query)+"&rows=5";
   const response=await fetch(url,{method:"GET",mode:"cors",credentials:"omit",cache:"no-store"});
   if(!response.ok)throw new Error("PDOK niet beschikbaar");
   const data=await response.json();const docs=data?.response?.docs;
   if(!Array.isArray(docs)||docs.length===0){addressResult.textContent="Geen adres gevonden. Controleer de invoer.";return;}
   const postcode=(query.match(/\b[1-9][0-9]{3}\s?[A-Za-z]{2}\b/)||[])[0];
   const requestedNumber=(query.match(/\b([0-9]{1,5})\b/)||[])[1];
   const match=docs.find(d=>d.type==="adres"&&(!postcode||String(d.postcode||"").replace(/\s/g,"").toUpperCase()===postcode.replace(/\s/g,"").toUpperCase())&&(!requestedNumber||String(d.huisnummer||"")===requestedNumber));
   if(!match){addressResult.textContent="Geen exact bevestigd adres gevonden. De zone is niet verplaatst. Controleer postcode en huisnummer.";return;}
   const point=/^POINT\\((-?[0-9.]+) (-?[0-9.]+)\\)$/.exec(String(match.centroide_ll||""));
   if(!point)throw new Error("Geen geldige coördinaten");
   const lng=Number(point[1]),lat=Number(point[2]);
   if(!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180)throw new Error("Ongeldige locatie");
   center={lat,lng};document.getElementById("start-lat").value=lat.toFixed(7);document.getElementById("start-lon").value=lng.toFixed(7);
   addressResult.textContent="Gevonden: "+String(match.weergavenaam||query)+". Controleer de cirkel op de kaart en sla pas daarna op.";
   if(map){map.setView([lat,lng],17);render();}else{status.textContent="Adres gevonden. Klik op Kaart inschakelen om de zone te controleren.";}
  }catch(e){addressResult.textContent="Adres zoeken mislukt. Probeer opnieuw of gebruik handmatige coördinaten.";}
  finally{findAddress.disabled=false;}
 });
 function init(){
  map=L.map("map",{scrollWheelZoom:false}).setView([center.lat,center.lng],16);
  L.tileLayer.wms("https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0",{layers:"Actueel_orthoHR",styles:"",format:"image/jpeg",transparent:false,version:"1.3.0",maxZoom:19,attribution:"Luchtfoto: PDOK"}).addTo(map);
  circle=L.circle([center.lat,center.lng],{radius:Number(radius.value),color:"#175cd3",fillOpacity:.15}).addTo(map);
  marker=L.marker([center.lat,center.lng],{draggable:true}).addTo(map);
  map.on("click",event=>{if(!canMove())return;center=event.latlng;render();});
  marker.on("dragend",()=>{if(!canMove()){marker.setLatLng(center);return;}center=marker.getLatLng();render();});
  enable.disabled=true;preview.disabled=false;const save=document.getElementById("save-zone");if(save)save.disabled=false;render();selectZone();
 }
 enable.addEventListener("click",()=>{
  const latInput=document.getElementById("start-lat"),lonInput=document.getElementById("start-lon");
  const chosen=activeZone();
  const lat=chosen?chosen.lat:Number(latInput.value),lng=chosen?chosen.lng:Number(lonInput.value);
  if((!chosen&&(!latInput.value.trim()||!lonInput.value.trim()))||!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180){status.textContent="Vul eerst een geldig, gecontroleerd middelpunt in.";return;}
  center={lat,lng};enable.disabled=true;status.textContent="Kaart wordt geladen...";
  const css=document.createElement("link");css.rel="stylesheet";css.href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";document.head.appendChild(css);
  const script=document.createElement("script");script.src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
  script.onload=init;script.onerror=()=>{enable.disabled=false;status.textContent="Kaartbibliotheek niet beschikbaar.";};
  document.head.appendChild(script);
 });
 const updateForm=document.getElementById("zone-update");
 if(updateForm)updateForm.addEventListener("submit",event=>{
  const z=activeZone(),name=zoneName.value.trim();
  if(!z||!center||!editing||!name||new TextEncoder().encode(name).length>160||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180||Number(radius.value)<10||Number(radius.value)>1000){event.preventDefault();status.textContent="Selecteer eerst een zone en schakel bewerken in.";return;}
  document.getElementById("update-id").value=z.id;
  document.getElementById("update-revision").value=z.revision;
  document.getElementById("update-name").value=name;
  document.getElementById("update-lat").value=center.lat.toFixed(7);
  document.getElementById("update-lon").value=center.lng.toFixed(7);
  document.getElementById("update-radius").value=radius.value;
 });
 if(updateButton)updateButton.addEventListener("click",event=>{
  if(!editing){event.preventDefault();if(!activeZone()||!map)return;editing=true;radius.disabled=false;zoneName.readOnly=false;updateButton.textContent="Wijzigingen opslaan";document.getElementById("zone-view-mode").textContent="Bewerken actief: verplaats de marker of wijzig de straal, sla daarna op.";}
 });
 const saveForm=document.getElementById("zone-save");if(saveForm)saveForm.addEventListener("submit",event=>{if(activeZone()){event.preventDefault();status.textContent="Bestaande zone kan hier niet opnieuw worden opgeslagen.";return;}const name=zoneName.value.trim();if(!center||!name||new TextEncoder().encode(name).length>160||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180){event.preventDefault();status.textContent="Ongeldige zonegegevens.";return;}document.getElementById("save-name").value=name;document.getElementById("save-lat").value=center.lat.toFixed(7);document.getElementById("save-lon").value=center.lng.toFixed(7);document.getElementById("save-radius").value=radius.value;});
 preview.addEventListener("click",()=>{const name=zoneName.value.trim();if(!name||new TextEncoder().encode(name).length>160){status.textContent="Ongeldige zonenaam (maximaal 160 bytes).";return;}document.getElementById("result").textContent=JSON.stringify({building_id:'.($selectedBuilding!==null ? json_encode((string)$selectedBuilding['id'],JSON_THROW_ON_ERROR) : 'null').',zone_name:name,latitude:center.lat,longitude:center.lng,radius_metres:Number(radius.value),saved:false},null,2);});
})();
</script></body></html>';
