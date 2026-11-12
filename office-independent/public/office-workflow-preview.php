<?php
declare(strict_types=1);
/** Read-only workflow preview. No publication or execution endpoint. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$scriptNonce = base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-" . $scriptNonce . "'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_WORKFLOW_PREVIEW_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only') {
    http_response_code(404); exit('Pagina niet beschikbaar.');
}
$host = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host === '' || !hash_equals($host, (string)($_SERVER['HTTP_HOST'] ?? ''))
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: /office-test/inloggen', true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id = ? AND active = 1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn() === false) { http_response_code(403); exit('Geen toegang.'); }
} catch (\Throwable $e) {
    http_response_code(503); exit('Workflowoverzicht tijdelijk niet beschikbaar.');
}
$nodes = ['Intake','CRM','Calculatie (optioneel)','Offertestudio (optioneel)','Acceptatie','Projecten','Uitvoering','FinanceCenter'];
$edges = [
 ['Registreren','Intake → CRM','crm.update_status','always'],
 ['Berekenen','CRM → Calculatie','crm.create_task','always'],
 ['Offerteconcept','Calculatie → Offertestudio','quotation.create_draft','calculation.exists'],
 ['Directe opdracht','CRM → Acceptatie','crm.update_status','acceptance.verified'],
 ['Opdracht starten','Acceptatie → Projecten','project.create','acceptance.verified'],
 ['Werkbegroting','Acceptatie → Projecten','budget.transfer_accepted_calculation','acceptance.verified'],
 ['Uitvoering','Projecten → Uitvoering','notification.create','always'],
 ['Financiële opvolging','Uitvoering → FinanceCenter','notification.create','always'],
];
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Office · Workflows</title><style>
body{font:15px system-ui,sans-serif;background:#f5f7fb;color:#182b48;margin:0;padding:30px}
main{max-width:1080px;margin:auto}h1{margin-bottom:5px}p{color:#52617b}
.flow{display:flex;gap:8px;flex-wrap:wrap;margin:24px 0}
.node{background:#fff;border:1px solid #cbd6e8;border-radius:10px;padding:14px 16px;font-weight:600}
.arrow{align-self:center;color:#426da4}.panel{background:white;border:1px solid #dce4ef;border-radius:12px;padding:20px;overflow:auto}
button,select{font:inherit;padding:9px;border:1px solid #b8c8df;border-radius:7px;background:#fff;color:#182b48}button{cursor:pointer}button:focus-visible{outline:3px solid #3379ca}fieldset{border:1px solid #dce4ef;border-radius:10px;margin-top:16px;padding:16px}label{display:block;margin:10px 0 5px}table{border-collapse:collapse;width:100%}th,td{padding:12px;text-align:left;border-bottom:1px solid #e7ecf4}
th{color:#4a5970}small{color:#64748b}
</style></head><body><main><h1>Installatie / Workflows</h1>
<p>BREBO commerciële blauwdruk · alleen-lezen voorbeeld · geen actieve procesuitvoering</p>
<div class="flow"><?php foreach ($nodes as $i=>$node): ?>
<?php if ($i>0): ?><span class="arrow" aria-hidden="true">→</span><?php endif; ?>
<div class="node"><?=h($node)?></div><?php endforeach; ?></div>
<section class="panel"><h2>Acties op verbindingen</h2>
<table><thead><tr><th>Verbinding</th><th>Route</th><th>Actie</th><th>Voorwaarde</th></tr></thead><tbody>
<?php foreach ($edges as [$label,$route,$action,$condition]): ?>
<tr><td><button type="button" class="edge" data-edge="<?=h((string)array_search([$label,$route,$action,$condition],$edges,true))?>"><?=h($label)?></button></td><td><?=h($route)?></td><td><code><?=h($action)?></code></td><td><?=h($condition)?></td></tr>
<?php endforeach; ?></tbody></table>
<fieldset><legend>Verbinding configureren (lokale simulatie)</legend><p id="selection">Selecteer een verbinding in de tabel.</p><label for="condition">Voorwaarde</label><select id="condition" disabled><option value="always">Altijd</option><option value="acceptance.verified">Acceptatie gecontroleerd</option><option value="calculation.exists">Calculatie aanwezig</option><option value="quotation.accepted">Offerte geaccepteerd</option><option value="document.verified">Document gecontroleerd</option></select><label for="failure">Bij fout</label><select id="failure" disabled><option value="block">Blokkeren</option><option value="retry">Opnieuw proberen</option><option value="assign_task">Taak aanmaken</option></select><p><button id="add" type="button" disabled>Voeg actie toe</button> <button id="reset" type="button">Herstel voorbeeld</button></p><ol id="actions"></ol><p id="message" role="status">Wijzigingen bestaan uitsluitend in deze browsersessie; er wordt niets opgeslagen of uitgevoerd.</p></fieldset><p><small>Demonstratie op basis van voorbeeldgegevens, niet de gepubliceerde databaseconfiguratie. Publiceren en opslaan zijn uitgeschakeld.</small></p>
<section class="panel" id="draft-editor"><h2>Opgeslagen concept bewerken</h2><p>Open deze pagina met <code>?blueprint_id=1&amp;version_id=1</code> (gebruik de werkelijke database-ID's). Deze editor wijzigt uitsluitend conceptversies. Het voorbeeld hierboven blijft een losse simulatie.</p><label for="definition">Blauwdrukdefinitie (JSON)</label><textarea id="definition" rows="14" style="width:100%;box-sizing:border-box;font:13px monospace" disabled></textarea><p><button type="button" id="load-draft">Concept laden</button> <button type="button" id="save-draft" disabled>Concept opslaan</button> <button type="button" id="publish-draft" disabled>Publiceren</button></p><p id="draft-status" role="status">Nog geen concept geladen.</p></section></main><script nonce="<?=h($scriptNonce)?>">
(() => {
'use strict';
const original = <?=json_encode($edges, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)?>;
const allowed = ['crm.update_status','crm.create_task','quotation.create_draft','project.create','budget.transfer_accepted_calculation','notification.create','document.create_draft'];
let data = original.map(row => ({label:row[0],route:row[1],actions:[row[2]],condition:row[3],failure:'block'}));
let selected = -1;
const condition = document.getElementById('condition'), failure = document.getElementById('failure');
const list = document.getElementById('actions'), message = document.getElementById('message');
const add = document.getElementById('add');
function render() {
  list.replaceChildren();
  condition.disabled = failure.disabled = add.disabled = selected < 0;
  if (selected < 0) { document.getElementById('selection').textContent='Selecteer een verbinding in de tabel.'; return; }
  const row = data[selected];
  document.getElementById('selection').textContent=row.label+' — '+row.route;
  condition.value=row.condition; failure.value=row.failure;
  row.actions.forEach((action,i) => {
    const li=document.createElement('li');
    const name=document.createElement('span');name.textContent=action+' ';
    const remove=document.createElement('button');remove.type='button';remove.textContent='Verwijder';
    remove.addEventListener('click',()=>{row.actions.splice(i,1);render();});
    li.append(name,remove);list.append(li);
  });
}
document.querySelectorAll('button.edge').forEach(button=>button.addEventListener('click',()=>{
 selected=Number(button.dataset.edge);render();
}));
condition.addEventListener('change',()=>{
 if(selected<0)return;
 const row=data[selected];
 if(row.actions.some(a=>a==='project.create'||a==='budget.transfer_accepted_calculation')&&condition.value!=='acceptance.verified'){
  message.textContent='Projectaanmaak en werkbegroting vereisen gecontroleerde acceptatie.';render();return;
 }
 row.condition=condition.value;message.textContent='Alleen lokale voorbeeldwijziging.';render();
});
failure.addEventListener('change',()=>{if(selected>=0){data[selected].failure=failure.value;render();}});
add.addEventListener('click',()=>{
 if(selected<0)return;
 const row=data[selected];if(row.actions.length>=20){message.textContent='Maximaal 20 acties.';return;}
 const choices=allowed.filter(a=>row.condition==='acceptance.verified'||!['project.create','budget.transfer_accepted_calculation'].includes(a));
 const action=window.prompt('Actie toevoegen (kies exact uit):\n'+choices.join('\n'));
 if(action===null)return;
 if(!choices.includes(action)){message.textContent='Actie niet toegestaan.';return;}
 row.actions.push(action);message.textContent='Actie alleen in lokale simulatie toegevoegd.';render();
});
document.getElementById('reset').addEventListener('click',()=>{
 data=original.map(row=>({label:row[0],route:row[1],actions:[row[2]],condition:row[3],failure:'block'}));
 selected=-1;message.textContent='Voorbeeld hersteld; niets opgeslagen.';render();
});
render();
})();
(() => {
'use strict';
const params=new URLSearchParams(location.search);
const blueprintId=Number(params.get('blueprint_id')),versionId=Number(params.get('version_id'));
const editor=document.getElementById('definition'),status=document.getElementById('draft-status');
const load=document.getElementById('load-draft'),save=document.getElementById('save-draft'),publish=document.getElementById('publish-draft');
const endpoint='/office-test/installatie/workflows/api';
let csrf='',sha='';
const enabled=Number.isSafeInteger(blueprintId)&&blueprintId>0&&Number.isSafeInteger(versionId)&&versionId>0;
load.disabled=!enabled;
async function request(method,payload){
 const response=await fetch(method==='GET'?endpoint+'?blueprint_id='+blueprintId+'&version_id='+versionId:endpoint,{
 method,credentials:'same-origin',headers:method==='POST'?{'Content-Type':'application/json'}:{},
 ...(method==='POST'?{body:JSON.stringify(payload)}:{})
 });
 const result=await response.json();
 if(!response.ok)throw new Error(result.error||'HTTP '+response.status);
 return result;
}
load.addEventListener('click',async()=>{
 try{
 const result=await request('GET');
 editor.value=JSON.stringify(result.draft.definition,null,2);
 sha=result.draft.definition_sha256;csrf=result.csrf;
 editor.disabled=false;save.disabled=false;publish.disabled=false;
 status.textContent='Conceptversie '+result.draft.version_number+' geladen. Wijzigingen nog niet opgeslagen.';
 }catch(e){status.textContent='Laden mislukt: '+e.message;}
});
save.addEventListener('click',async()=>{
 try{
 const definition=JSON.parse(editor.value);
 const result=await request('POST',{operation:'save',blueprint_id:blueprintId,version_id:versionId,csrf,expected_sha256:sha,definition});
 sha=result.definition_sha256;status.textContent='Concept opgeslagen. Niet gepubliceerd.';
 }catch(e){status.textContent='Opslaan mislukt: '+e.message;}
});
publish.addEventListener('click',async()=>{
 if(!confirm('Publiceer deze workflowversie? Lopende dossiers blijven op hun bestaande versie.'))return;
 try{
 const result=await request('POST',{operation:'publish',blueprint_id:blueprintId,version_id:versionId,csrf});
 status.textContent='Versie '+result.published.version_id+' gepubliceerd. Nieuwe dossiers kunnen deze versie gebruiken.';
 editor.disabled=save.disabled=publish.disabled=true;
 }catch(e){status.textContent='Publiceren mislukt: '+e.message;}
});
})();
</script></body></html>
