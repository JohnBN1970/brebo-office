<?php
declare(strict_types=1);
// Independent CRM-first Calc workbench. Office-test only, not Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$deny=static function(int $status,string $reason='UNKNOWN'): never {
    if (!preg_match('/^[A-Z][A-Z0-9_]{1,30}$/D',$reason)) $reason='UNKNOWN';
    header('X-Brebo-Calc-Diagnostic: '.$reason);
    http_response_code($status);
    exit('Calculatie niet beschikbaar (code '.$reason.').');
};
$collaborationEnabled=getenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED')==='enabled'&&getenv('OFFICE_CALC_DRAFT_COLLABORATION_SCHEMA_VERIFIED')==='enabled';
$workbenchUrl=$collaborationEnabled?'/office-test/calculatie/samenwerken':'/office-test/calculatie';
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || (!$collaborationEnabled&&(getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')!=='enabled'||getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED')!=='enabled'))) $deny(404);
$conflict='';$actionError='';$materialSource=null;$catalog=null;$materialRows=[];$materialQuery='';$materialWarning='';
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) $deny(405);
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!is_string($host)
    ||!hash_equals($allowed,$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') $deny(403);
if ($method==='POST') {
    $expectedOrigin='https://'.$host;
    $origin=$_SERVER['HTTP_ORIGIN']??null;
    $referer=$_SERVER['HTTP_REFERER']??null;
    $fetchSite=$_SERVER['HTTP_SEC_FETCH_SITE']??null;
    if (is_string($origin) && $origin!=='' && $origin!=='null') {
        if (!hash_equals($expectedOrigin,$origin)) $deny(403,'ORIGIN_MISMATCH');
    } elseif (is_string($fetchSite) && in_array($fetchSite,['cross-site','same-site'],true)) {
        $deny(403,'ORIGIN_CROSS_SITE');
    } elseif ($fetchSite==='same-origin'
        || (is_string($referer) && str_starts_with($referer,$expectedOrigin.'/'))) {
        // An explicit same-origin browser signal.
    } elseif (!is_string($_POST['csrf']??null)) {
        // Privacy browsers can omit all origin signals; CSRF still verified below.
        $deny(403,'ORIGIN_NO_CSRF');
    }
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) $deny(413,'BODY_SIZE');
}
$draft=filter_var(($method==='POST'?($_POST['draft_id']??null):($_GET['draft_id']??null)),FILTER_VALIDATE_INT);
$showList=$collaborationEnabled&&$method==='GET'&&!array_key_exists('draft_id',$_GET);
if (!$showList&&(!is_int($draft)||$draft<1)) $deny(400);
try {
    require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftLineRepository.php';
    $session=$_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]??null;
    if (!is_string($session)){
        if($collaborationEnabled&&$method==='GET'){header('X-Brebo-Calc-Diagnostic: SESSION_COOKIE');header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;}
        $deny(403,'SESSION_COOKIE');
    }
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))->findActive($session,time());
    if($operator===null&&$collaborationEnabled&&$method==='GET'){header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;}
    if ($operator===null||!$operator->canEditCrm||!ctype_digit($operator->operatorId)) $deny(403,'SESSION_RIGHTS');
    $actor=(int)$operator->operatorId;
    if($showList){
        $q=$db->prepare("SELECT d.id,d.opportunity_id,d.created_at,a.username FROM office_calc_crm_drafts d JOIN office_crm_opportunities o ON o.id=d.opportunity_id JOIN office_operator_accounts a ON a.id=d.created_by WHERE d.project_id IS NULL AND d.crm_revision=o.revision AND o.status='open' AND o.stage IN ('prospect','opportunity','proposal','negotiation') AND (d.created_by=? OR EXISTS(SELECT 1 FROM office_calc_crm_draft_collaborators c WHERE c.draft_id=d.id AND c.operator_id=?)) ORDER BY d.id DESC LIMIT 200");$q->execute([$actor,$actor]);$drafts=$q->fetchAll(PDO::FETCH_ASSOC);
        $escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        header('Content-Type: text/html; charset=utf-8');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
        echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calculatieconcept #'.$draft.' | BREBO Office</title><style>
:root{color-scheme:light}*{box-sizing:border-box}body{font:13px system-ui,sans-serif;background:#edf1f7;color:#24374a;margin:0}
main{max-width:none;margin:0;padding:8px}a{color:#205a98}header,.panel{background:#fff;border:1px solid #cbd4df;border-radius:3px;padding:10px;margin-bottom:7px}
header{display:flex;justify-content:space-between;gap:12px;align-items:center}header small{color:#53677f;letter-spacing:.03em}
h1{font-size:18px;margin:3px 0}h2{font-size:14px;margin:0 0 8px}.muted{color:#62748b;margin:3px 0}
.metrics{display:flex;flex-wrap:wrap;gap:12px;margin:5px 0 8px;padding:5px 10px;background:#f8fafc;border:1px solid #d8e0ea}.metric{display:flex;align-items:center;gap:8px}
.metric span{color:#64758b}.metric strong{font-size:14px}
.gridlayout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:8px;align-items:start}
table{width:100%;border-collapse:collapse;white-space:nowrap}td,th{padding:5px 7px;border:1px solid #e1e5ec;text-align:left}th{font-size:11px;color:#334b66;background:#e8eef6;position:sticky;top:0;z-index:1}
tbody tr:nth-child(even){background:#f5f9fc}tbody tr:hover{background:#d6f1ec}td:first-child{font-variant-numeric:tabular-nums;color:#62748b;background:#f0f3f8}
td.numeric,th.numeric{text-align:right;font-variant-numeric:tabular-nums}.table-scroll{overflow:auto;max-height:calc(100vh - 195px);min-height:420px}.amount{font-size:15px;font-weight:700;text-align:right;margin:8px}
form{background:#f4f6f9;padding:10px;border-radius:3px}.calc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-bottom:10px}
.calc-fields label{display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:12px}.calc-fields input,.calc-fields select{width:100%;font:inherit;padding:7px;border:1px solid #b6c3d4;border-radius:3px;background:#fff}
button{background:#304a95;border:0;border-radius:3px;color:#fff;padding:8px 11px;font:inherit;font-weight:700;cursor:pointer}.empty{padding:20px;text-align:center;color:#718198}
dl{display:grid;grid-template-columns:1fr auto;gap:8px;margin:0}dd{margin:0;font-variant-numeric:tabular-nums;font-weight:650}
.workbench{display:grid;grid-template-columns:225px minmax(0,1fr);gap:8px}.element-tree{background:#fff;border:1px solid #cbd4df;padding:8px;min-height:500px;max-height:calc(100vh - 115px);overflow:auto}.element-tree h2{border-bottom:1px solid #d8e0ea;padding-bottom:7px}.element-tree ul{list-style:none;margin:0;padding:0}.element-tree li{padding:5px 3px;border-bottom:1px solid #edf0f4;font-size:12px}.element-tree li:before{content:"▸ ";color:#8c9aad}.sheet-label{background:#d8e5f2;border:1px solid #bfcfe1;padding:6px 9px;font-weight:700}.sheet-tabs{padding:6px 8px;background:#f4f6fa;border-top:1px solid #cbd4df}.sheet-tabs span{padding:4px 12px;border:1px solid #cbd4df;background:white}.sheet-tabs span:first-child{border-bottom:2px solid #3665b3;color:#244c89}
@media(max-width:1050px){.workbench{grid-template-columns:165px minmax(0,1fr)}.gridlayout{grid-template-columns:minmax(0,1fr)}}
@media(max-width:680px){.workbench{display:block}.element-tree{min-height:0;max-height:180px;margin-bottom:8px}header{display:block}.calc-fields{grid-template-columns:1fr}.table-scroll{max-height:65vh}}
</style></head><body><main><header><div><small>BREBO OFFICE / CRM / CALCULATIE</small><h1>Calculatieconcept #'.$draft.'</h1><p class="muted">Concept · Zonder project · Bedragen exclusief btw</p></div><a href="/office-test/crm/verkoopkansen">Terug naar CRM</a></header>';
echo '<div class="workbench"><aside class="element-tree" aria-label="Elementenboom"><h2>Elementen · Bouwkosten</h2><ul>';
foreach(['00 Bouwdeel algemeen','10 Sloopwerkzaamheden','16 Funderingen','21 Buitenwanden','22 Binnenwanden','23 Vloeren','24 Trappen en hellingen','27 Daken','31 Buitenwandopeningen','32 Binnenwandopeningen','33 Vloeropeningen','34 Balustrades en leuningen','37 Dakopeningen','41 Buitenwandafwerkingen','42 Binnenwandafwerkingen','43 Vloerafwerkingen','45 Plafondafwerkingen','47 Dakafwerkingen','50 Energieopwekking','51 Warmteopwekking','52 Afvoeren','53 Water','54 Gassen','56 Warmtedistributie','57 Luchtbehandeling','61 Elektrotechnische voorzieningen','62 Krachtstroom','63 Verlichting','65 Beveiliging','73 Vaste keukenvoorzieningen','74 Vaste sanitaire voorzieningen','90 Terrein','98 Stelposten'] as $element){echo '<li>'.$h($element).'</li>';}
echo '</ul><p class="muted">Classificatie als referentie; koppeling aan calculatieparagrafen volgt.</p></aside><div class="workspace"><div class="metrics">';
echo '<div class="metric"><span>Directe kosten</span><strong>€ '.number_format((float)$canonicalTotal,2,',','.').'</strong></div>';
echo '<div class="metric"><span>Regels</span><strong>'.count($lines).'</strong></div>';
echo '<div class="metric"><span>Status</span><strong>Concept</strong></div>';
echo '</div><div class="sheet-label">Data · Calculatieregels</div><div class="gridlayout"><section class="panel"><div class="table-scroll"><table><thead><tr><th>Nr.</th><th>Omschrijving</th><th>Type</th><th class="numeric">Hoeveelheid</th><th>Eenheid</th><th class="numeric">Norm</th><th class="numeric">Uren</th><th class="numeric">Tarief</th><th class="numeric">Loon</th><th class="numeric">Materiaal</th><th class="numeric">Materieel</th><th class="numeric">Stelpost</th><th class="numeric">Ond.aann.</th><th class="numeric">Kosten/eh</th><th class="numeric">Subtotaal</th><th>Prijsbron</th><th>Actie</th></tr></thead><tbody>';

$total=0;
$rowNumber=0;
foreach($lines as $line) {
    ++$rowNumber;
    $subtotal=$lineAmount($line);if(!$collaborationEnabled)$total+=$subtotal;
    $sourceLabel=($line['price_source_type']??'manual')==='article'?($line['source_supplier'].' · '.$line['source_reference']):'Handmatig';
    $unitCost=number_format($collaborationEnabled?(float)$line['unit_cost_decimal']:(int)$line['unit_cost_cents']/100,$collaborationEnabled?4:2,',','.');
    $rowTotal=number_format($collaborationEnabled?(float)$subtotal:$subtotal/100,$collaborationEnabled?4:2,',','.');
    $costCells=['labor','material','equipment','other','subcontract'];
    echo '<tr><td>'.$rowNumber.'</td><td>'.$h((string)$line['description']).'</td><td>'.$h($typeLabels[(string)$line['cost_type']]??(string)$line['cost_type']).'</td><td class="numeric">'.$h((string)($line['quantity_decimal']??$line['quantity'])).'</td><td>'.$h((string)($line['unit']??'')).'</td><td colspan="3" title="Norm, uren en tarief zijn nog niet beschikbaar in dit native regelmodel">—</td>';
    foreach($costCells as $kind)echo '<td class="numeric">'.((string)$line['cost_type']===$kind?'€ '.$unitCost:'—').'</td>';
    echo '<td class="numeric">€ '.$unitCost.'</td><td class="numeric">€ '.$rowTotal.'</td><td>'.$h($sourceLabel).'</td><td><a href="'.$workbenchUrl.'?draft_id='.$draft.'&amp;edit_line_id='.rawurlencode((string)$line['line_id']).'">Wijzigen</a></td></tr>';
}
$editIdValue=$edit===null?'':$h((string)$edit['line_id']);
$editDescription=$edit===null?'':$h((string)$edit['description']);
$editQuantity=$edit===null?'':$h((string)($edit['quantity_decimal']??$edit['quantity']));
$editPrice=$edit===null?'':($collaborationEnabled?$h(str_replace('.',',',(string)$edit['unit_cost_decimal'])):number_format((int)$edit['unit_cost_cents']/100,2,',',''));
$editUnit=$edit===null?'st':$h((string)(($edit['unit']??'')?:'st'));
$selectedCost=$edit===null?'labor':(string)$edit['cost_type'];
if($lines===[]) echo '<tr><td colspan="17" class="empty">Nog geen calculatieregels. Voeg rechts de eerste post toe.</td></tr>';
echo '</tbody></table></div><p class="amount">Totaal: € '.number_format($collaborationEnabled?(float)$canonicalTotal:$total/100,2,',','.').'</p></section>';
echo '<section class="panel"><h2>'.($edit===null?'Calculatieregel toevoegen':'Calculatieregel wijzigen').'</h2>';
if($conflict!=='')echo '<p role="alert" style="padding:16px;background:#fff0bf">'.$h($conflict).'</p>';
echo '<form method="post" action="'.$workbenchUrl.'">';
echo '<input type="hidden" name="draft_id" value="'.$draft.'">';
echo '<input type="hidden" name="csrf" value="'.$h($csrf).'">';
if($collaborationEnabled)echo '<input type="hidden" name="expected_revision" value="'.$h($expectedRevision).'"><label>Wijzigingsreden <input name="reason" maxlength="1000" required value="'.($conflict!==''?$h((string)($_POST['reason']??'Calculatieregel gewijzigd')):'Calculatieregel gewijzigd').'"></label>';
echo '<input type="hidden" name="line_id" value="'.$editIdValue.'">';
echo '<div class="calc-fields">';
echo '<label>Omschrijving <input name="description" maxlength="500" value="'.$editDescription.'" required></label>';
echo '<label>Type <select name="cost_type">';
foreach(['labor'=>'Arbeid','material'=>'Materiaal','equipment'=>'Materieel','subcontract'=>'Onderaanneming','other'=>'Overig'] as $key=>$label) {
    echo '<option value="'.$key.'"'.($selectedCost===$key?' selected':'').'>'.$label.'</option>';
}
echo '</select></label>';
echo '<label>Aantal <input type="number" min="'.($collaborationEnabled?'0.0001':'1').'" step="'.($collaborationEnabled?'0.0001':'1').'" name="quantity" value="'.$editQuantity.'" required></label>';
if($collaborationEnabled)echo '<label>Eenheid <input name="unit" maxlength="32" value="'.$editUnit.'" required></label>';
echo '<label>Prijs per eenheid (€) <input type="text" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,'.($collaborationEnabled?'4':'2').'})?" name="unit_price_eur" value="'.$editPrice.'" placeholder="45,00" required></label>';
echo '</div><button type="submit">'.($edit===null?'Regel toevoegen':'Wijzigingen opslaan').'</button>';
if($edit!==null) echo ' <a href="'.$workbenchUrl.'?draft_id='.$draft.'">Annuleren</a>';
echo '</form></section></div><section class="panel"><h2>Kostenverdeling</h2><dl>';
foreach($typeLabels as $key=>$label) echo '<dt>'.$h($label).'</dt><dd>€ '.number_format($collaborationEnabled?(float)$categoryTotals[$key]:$categoryTotals[$key]/100,2,',','.').'</dd>';
echo '</dl><p class="muted">Opslagen, verkoopprijs, hoofdstukken en versiebeheer volgen in de volgende fase. De huidige bedragen zijn directe kosten.</p></section>';
if($materialWarning!=='')echo '<section class="panel"><p role="status">'.$h($materialWarning).'</p></section>';
require __DIR__.'/office-calc-crm-material-panel.php';
require __DIR__.'/office-calc-crm-collaboration-panels.php';
echo '<div class="sheet-tabs"><span>Data</span><span>Uren (nog niet gekoppeld)</span><span>Staart (nog niet gekoppeld)</span></div></div></div></main></body></html>';
