<?php
declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$paths=['/office-test/kwaliteit'=>'quality','/office-test/risicos'=>'risk','/office-test/inkoop'=>'procurement'];
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
$module=is_string($path) ? ($paths[$path]??null) : null;
if ($module===null || getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_GOVERNANCE_ENABLED')!=='enabled'
    || getenv('OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED')!=='enabled') {
    http_response_code(404);exit('Onderdeel niet beschikbaar.');
}
if (getenv('OFFICE_CRM_SESSION_MODE')!=='enabled') {http_response_code(503);exit('Sessievoorziening niet beschikbaar.');}
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) {header('Allow: GET, POST');http_response_code(405);exit;}
$host=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host)||$host===''||!hash_equals($host,(string)($_SERVER['HTTP_HOST']??''))
    ||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') {http_response_code(403);exit;}
if ($method==='POST' && (!hash_equals('https://'.$host,(string)($_SERVER['HTTP_ORIGIN']??''))
    || (int)($_SERVER['CONTENT_LENGTH']??0)>65536)) {http_response_code(403);exit;}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $f) require_once dirname(__DIR__).'/src/Intake/'.$f;
    require_once dirname(__DIR__).'/src/Governance/bootstrap.php';
    require_once dirname(__DIR__).'/src/Governance/OfficeGovernanceCsrf.php';
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
    if ($operator===null) {
        if ($method==='POST') {http_response_code(401);exit('Sessie verlopen.');}
        header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;
    }
    $store=new \Brebo\Office\Governance\Infrastructure\PdoOfficeGovernanceStore($db);
    $service=new \Brebo\Office\Governance\OfficeGovernanceService($store);
    $contexts=$store->contexts($operator->operatorId,$module);
    if ($contexts===[]) {http_response_code(403);exit('Geen toegang tot dit onderdeel.');}
    $input=$method==='POST'?$_POST:$_GET;
    $project=filter_var($input['project_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $building=filter_var($input['building_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ((isset($input['project_id'])&&!is_int($project)) || (isset($input['building_id'])&&!is_int($building))) throw new InvalidArgumentException('Selecteer een project en gebouw.');
    if (is_int($project)) {
        $contexts=array_values(array_filter($contexts,static fn(array $c):bool=>(int)$c['project_id']===$project));
        if ($contexts===[]) throw new OutOfBoundsException('Project niet gevonden.');
    }
    if (is_int($building)&&!is_int($project)) throw new InvalidArgumentException('Project ontbreekt.');
    $records=[];$record=null;$csrf='';$canWrite=false;$choices=[];
    if (is_int($project)&&is_int($building)) {
        $records=$service->read($operator->operatorId,$module,$project,$building);
        $choices=$store->choices($operator->operatorId,$module,$project,$building);
        $canWrite=$store->allowed($operator->operatorId,$module,'write',$project,$building);
        $secret=getenv('OFFICE_TEST_GOVERNANCE_CSRF_SECRET');
        if ($canWrite) {
            if (!is_string($secret)||strlen($secret)<32) throw new RuntimeException('Security configuration missing');
            $csrf=\Brebo\Office\Governance\OfficeGovernanceCsrf::token($operator->sessionId,$module,$project,$building,$secret,time());
        }
        if ($method==='POST') {
            if (!$canWrite || !\Brebo\Office\Governance\OfficeGovernanceCsrf::verify($_POST['csrf']??null,$operator->sessionId,$module,$project,$building,$secret,time())) {http_response_code(403);exit('Ongeldige formulierbeveiliging.');}
            if ($module==='procurement') {
                $price=$_POST['unit_price_euros']??null;
                if (!is_string($price)||!preg_match('/^([0-9]{1,8})(?:[.,]([0-9]{1,2}))?$/D',trim($price),$money)) throw new InvalidArgumentException('Ongeldige prijs.');
                $_POST['unit_price_cents']=(int)$money[1]*100+(int)str_pad($money[2]??'',2,'0');
            }
            $saved=$service->save($operator->operatorId,$module,$_POST);
            header('Location: '.$path.'?'.http_build_query(['project_id'=>$project,'building_id'=>$building,'id'=>$saved['id']]),true,303);exit;
        }
        if (isset($_GET['id'])) {
            $id=filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!is_int($id)) throw new InvalidArgumentException('Ongeldig dossier.');
            foreach ($records as $r) if ((int)$r['id']===$id) $record=$r;
            if ($record===null) throw new OutOfBoundsException('Dossier niet gevonden.');
        }
    } elseif ($method==='POST') throw new InvalidArgumentException('Context ontbreekt.');
} catch (OutOfBoundsException $e) {http_response_code(404);exit('Dossier niet gevonden.');}
catch (DomainException $e) {http_response_code(403);exit('Wijziging niet toegestaan. Controleer status, rechten en revisie.');}
catch (InvalidArgumentException $e) {http_response_code(422);exit('Ongeldige invoer. Controleer verplichte velden en datum.');}
catch (Throwable $e) {http_response_code(503);exit('Onderdeel tijdelijk niet beschikbaar.');}
$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$titles=['quality'=>'Kwaliteit','risk'=>'Risico’s','procurement'=>'Inkoop'];
$statusLabels=['open'=>'Open','deviation'=>'Afwijking','recheck'=>'Hercontrole','accepted'=>'Akkoord','mitigating'=>'In beheersing','closed'=>'Gesloten','draft'=>'Concept','submitted'=>'Ter goedkeuring','approved'=>'Goedgekeurd','ordered'=>'Opdracht verstrekt','received'=>'Ontvangen','cancelled'=>'Geannuleerd'];
$fields=match($module) {
    'quality'=>['requirement'=>'Eis / toetsingskader','observation'=>'Constatering','remedy'=>'Herstelmaatregel','evidence'=>'Bewijsreferentie','inspection_finding_id'=>'Inspectiebevinding (optioneel)'],
    'risk'=>['cause'=>'Oorzaak','event'=>'Gebeurtenis','consequence'=>'Gevolg','probability'=>'Kans (1–5)','impact'=>'Impact (1–5)','measure'=>'Beheersmaatregel','residual_risk'=>'Restrisico','evidence'=>'Beheersbewijs'],
    'procurement'=>['supplier_relation_id'=>'Leverancier','description'=>'Omschrijving opdracht','quantity'=>'Aantal eenheden','unit_price_cents'=>'Prijs per eenheid excl. btw (€)','selection_reason'=>'Onderbouwing leverancierskeuze','evidence'=>'Opdracht- / prestatiebewijs'],
};
$statuses=match($module) {'quality'=>['open','deviation','recheck','accepted'],'risk'=>['open','mitigating','closed'],'procurement'=>['draft','submitted','approved','ordered','received','cancelled']};
// Read-only operational KPIs for the selected project/building, derived from authorized rows.
$counts=[];$late=0;$today=gmdate('Y-m-d');$urgent=0;$amount=0;
foreach($records as $item) {
    $state=(string)($item['status']??'');
    $counts[$state]=($counts[$state]??0)+1;
    $payload=$item['payload']??[];
    $done=in_array($state,['accepted','closed','received','cancelled'],true);
    if(!$done && is_string($payload['due_date']??null) && $payload['due_date']<$today) $late++;
    if($module==='quality' && !$done && !empty($payload['blocks_release'])) $urgent++;
    if($module==='risk' && !$done && (int)($payload['score']??((int)($payload['probability']??0)*(int)($payload['impact']??0)))>=15) $urgent++;
    if($module==='procurement' && in_array($state,['approved','ordered','received'],true)) {
        $amount+=(int)($payload['quantity']??0)*(int)($payload['unit_price_cents']??0);
    }
}
$activeCount=count($records)-array_sum(array_intersect_key($counts,array_flip(['accepted','closed','received','cancelled'])));
$filterStatus=is_string($_GET['status']??null)?(string)$_GET['status']:'all';
if(!in_array($filterStatus,['all','open','overdue','closed'],true)) $filterStatus='all';
$visibleRecords=array_values(array_filter($records,static function(array $r)use($filterStatus,$today):bool {
    $status=(string)($r['status']??'');
    $closed=in_array($status,['accepted','closed','received','cancelled'],true);
    return match($filterStatus) {
        'open'=>!$closed,
        'overdue'=>!$closed && is_string($r['payload']['due_date']??null) && $r['payload']['due_date']<$today,
        'closed'=>$closed,
        default=>true,
    };
}));
usort($visibleRecords,static function(array $a,array $b):int {
    $ad=(string)($a['payload']['due_date']??'9999-12-31');
    $bd=(string)($b['payload']['due_date']??'9999-12-31');
    return strcmp($ad,$bd) ?: ((int)$b['id']<=>(int)$a['id']);
});

$moduleLinks=['quality'=>'kwaliteit','risk'=>'risicos','procurement'=>'inkoop'];
$projectOverview=[];
if(is_int($project)&&is_int($building)) {
    // Cross-module overview is read-only and respects each module's live object ACL.
    foreach($moduleLinks as $key=>$route) {
        if(!$store->allowed($operator->operatorId,$key,'read',$project,$building)) continue;
        $moduleRows=$key===$module?$records:$service->read($operator->operatorId,$key,$project,$building);
        $open=0;$overdue=0;$blocking=0;$high=0;$commitments=0;
        foreach($moduleRows as $item) {
            $state=(string)($item['status']??'');
            $payload=$item['payload']??[];
            $finished=in_array($state,['accepted','closed','received','cancelled'],true);
            if(!$finished) {
                $open++;
                if(is_string($payload['due_date']??null)&&$payload['due_date']<$today) $overdue++;
            }
            if($key==='quality'&&!$finished&&!empty($payload['blocks_release'])) $blocking++;
            if($key==='risk'&&!$finished&&(int)($payload['probability']??0)*(int)($payload['impact']??0)>=15) $high++;
            if($key==='procurement'&&in_array($state,['approved','ordered','received'],true)) {
                $commitments+=(int)($payload['amount_cents']??0);
            }
        }
        $projectOverview[$key]=['route'=>$route,'open'=>$open,'overdue'=>$overdue,
            'blocking'=>$blocking,'high'=>$high,'commitments'=>$commitments];
    }
}

$values=$record['payload']??[];
$labels=[];
foreach($choices as $key=>$options) foreach($options as $option) $labels[$key][(string)$option['id']]=$option['label'];
$display=static function(string $key,mixed $value)use($labels):string {
    if($key==='unit_price_cents')return '€ '.number_format((int)$value/100,2,',','.');
    return isset($labels[$key][(string)$value]) ? (string)$labels[$key][(string)$value] : (string)$value;
};
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($titles[$module]) ?> · BREBO Office</title>
<style>body{margin:0;font:16px system-ui;background:#f4f6fa;color:#203247}main{max-width:1100px;margin:auto;padding:28px}a{color:#175a91}section{background:white;padding:22px;margin:18px 0;border:1px solid #dce3eb;border-radius:10px}label{display:block;margin:14px 0 5px}input,textarea,select{box-sizing:border-box;width:100%;padding:10px;border:1px solid #bdcbd7;border-radius:6px;font:inherit}textarea{min-height:85px}button{margin-top:18px;padding:12px 22px;background:#175a91;color:white;border:0;border-radius:6px;font:inherit}.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.kpi{border:1px solid #dce3eb;border-radius:10px;padding:16px;background:#fff}.kpi strong{font-size:26px;display:block}.kpi small{color:#536779}.kpi.warn{border-left:5px solid #d18c21}.kpi.danger{border-left:5px solid #b33d37}.tabs{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}.tabs a{display:inline-block;background:#e7eef5;border-radius:7px;padding:10px 14px;text-decoration:none}.tabs a.current{background:#175a91;color:white}table{border-collapse:collapse;width:100%}td,th{text-align:left;border-bottom:1px solid #dce3eb;padding:12px}.fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 20px}</style></head><body><main>
<a href="/office-test/">BREBO Office</a><h1><?= $e($titles[$module]) ?></h1>
<section><h2>Project en gebouw</h2><?php foreach($contexts as $c): ?><p><a href="<?= $e($path.'?'.http_build_query(['project_id'=>$c['project_id'],'building_id'=>$c['building_id']])) ?>"><?= $e($c['title']) ?> · <?= $e($c['building_name']) ?></a></p><?php endforeach; ?></section>
<?php if (is_int($project)&&is_int($building)): ?>
<section><h2>Projectbeheersing · totaaloverzicht</h2><div class="kpis"><?php foreach($projectOverview as $key=>$stats): ?><div class="kpi <?= ($stats['blocking']||$stats['high'])?'danger':($stats['overdue']?'warn':'') ?>"><small><?= $e($titles[$key]) ?></small><strong><?= $stats['open'] ?> open</strong><p><?= $stats['overdue'] ?> te laat<?php if($key==='quality'): ?> · <?= $stats['blocking'] ?> blokkades<?php elseif($key==='risk'): ?> · <?= $stats['high'] ?> hoog<?php else: ?> · € <?= $e(number_format($stats['commitments']/100,2,',','.')) ?> verplicht<?php endif; ?></p><a href="<?= $e('/office-test/'.$stats['route'].'?'.http_build_query(['project_id'=>$project,'building_id'=>$building])) ?>">Bekijk dossiers</a></div><?php endforeach; ?></div><p><a href="<?= $e('/office-test/projecten/detail?id='.$project) ?>">Terug naar projectdashboard</a></p></section>
<nav class="tabs" aria-label="Projectbeheersing"><?php foreach($moduleLinks as $key=>$route): ?><a class="<?= $key===$module?'current':'' ?>" href="<?= $e('/office-test/'.$route.'?'.http_build_query(['project_id'=>$project,'building_id'=>$building])) ?>"><?= $e($titles[$key]) ?></a><?php endforeach; ?></nav>
<div class="kpis"><div class="kpi"><small>Registraties</small><strong><?= count($records) ?></strong></div><div class="kpi"><small>Openstaand</small><strong><?= $activeCount ?></strong></div><div class="kpi <?= $late?'warn':'' ?>"><small>Termijn verstreken</small><strong><?= $late ?></strong></div><?php if($module!=='procurement'): ?><div class="kpi <?= $urgent?'danger':'' ?>"><small><?= $module==='quality'?'Vrijgaveblokkades':'Hoge risico’s (15+)' ?></small><strong><?= $urgent ?></strong></div><?php else: ?><div class="kpi"><small>Goedgekeurd / besteld / ontvangen excl. btw</small><strong>€ <?= $e(number_format($amount/100,2,',','.')) ?></strong></div><?php endif; ?></div>
<p><a href="/office-test/projecten/detail?id=<?= $e($project) ?>">Terug naar project</a> · Gebouw <?= $e($building) ?></p>
<section><h2>Dossieroverzicht</h2><nav class="tabs" aria-label="Dossierfilter"><?php foreach(['all'=>'Alles','open'=>'Openstaand','overdue'=>'Te laat','closed'=>'Afgerond'] as $key=>$label): ?><a class="<?= $filterStatus===$key?'current':'' ?>" href="<?= $e($path.'?'.http_build_query(['project_id'=>$project,'building_id'=>$building,'status'=>$key])) ?>"><?= $e($label) ?> (<?= $key==='all'?count($records):($key==='open'?$activeCount:($key==='overdue'?$late:count($records)-$activeCount)) ?>)</a><?php endforeach; ?></nav><table><thead><tr><th>Onderwerp</th><th>Status</th><th>Eigenaar</th><th>Termijn</th></tr></thead><tbody>
<?php foreach(array_slice($visibleRecords,0,200) as $r): ?><tr><td><a href="<?= $e($path.'?'.http_build_query(['project_id'=>$project,'building_id'=>$building,'id'=>$r['id']])) ?>"><?= $e($r['title']) ?></a></td><td><?= $e($statusLabels[$r['status']]??$r['status']) ?></td><td><?= $e($display('owner',$r['payload']['owner'])) ?></td><td><?= $e($r['payload']['due_date']) ?></td></tr><?php endforeach; ?>
</tbody></table><?php if($visibleRecords===[]): ?><p>Geen registraties voor dit filter.</p><?php endif; ?><p>Gesorteerd op termijn (eerstvolgende bovenaan). Maximaal 200 resultaten per filter.</p></section>
<?php if($record!==null): ?><section><h2><?= $e($record['title']) ?></h2><?php foreach($fields as $key=>$label): ?><p><strong><?= $e($label) ?>:</strong> <?= nl2br($e($display($key,$values[$key]??''))) ?></p><?php endforeach; ?></section><?php endif; ?>
<?php if($canWrite): ?><section><h2><?= $record===null?'Nieuwe registratie':'Registratie bewerken' ?></h2><form method="post" action="<?= $e($path) ?>">
<?php foreach(['csrf'=>$csrf,'project_id'=>$project,'building_id'=>$building,'id'=>$record['id']??0,'revision'=>$record['revision']??0,'command'=>bin2hex(random_bytes(16))] as $k=>$v): ?><input type="hidden" name="<?= $e($k) ?>" value="<?= $e($v) ?>"><?php endforeach; ?>
<div class="fields"><div><label for="title">Onderwerp</label><input id="title" name="title" required maxlength="255" value="<?= $e($record['title']??'') ?>"></div>
<div><label for="status">Status</label><select id="status" name="status"><?php foreach($statuses as $s): ?><option value="<?= $e($s) ?>"<?= ($record['status']??$statuses[0])===$s?' selected':'' ?>><?= $e($statusLabels[$s]) ?></option><?php endforeach; ?></select></div>
<div><label for="owner">Eigenaar</label><select id="owner" name="owner" required><?php foreach($choices['owner'] as $choice): ?><option value="<?= $e($choice['id']) ?>"<?= (string)($values['owner']??$operator->operatorId)===(string)$choice['id']?' selected':'' ?>><?= $e($choice['label']) ?></option><?php endforeach; ?></select></div>
<div><label for="due_date">Termijn</label><input id="due_date" type="date" name="due_date" required value="<?= $e($values['due_date']??'') ?>"></div>
<?php foreach($fields as $key=>$label): ?><div><label for="<?= $e($key) ?>"><?= $e($label) ?></label><?php if(in_array($key,['inspection_finding_id','supplier_relation_id'],true)): ?><select id="<?= $e($key) ?>" name="<?= $e($key) ?>"><option value="<?= $key==='inspection_finding_id'?'0':'' ?>">Selecteren</option><?php foreach($choices[$key] as $choice): ?><option value="<?= $e($choice['id']) ?>"<?= (string)($values[$key]??'')===(string)$choice['id']?' selected':'' ?>><?= $e($choice['label']) ?></option><?php endforeach; ?></select><?php elseif($key==='unit_price_cents'): ?><input id="unit_price_cents" name="unit_price_euros" inputmode="decimal" required value="<?= isset($values[$key])?$e(number_format($values[$key]/100,2,',','')):'' ?>"><?php elseif(in_array($key,['probability','impact','quantity'],true)): ?><input type="number" id="<?= $e($key) ?>" name="<?= $e($key) ?>" min="<?= $key==='inspection_finding_id'?'0':'1' ?>" step="1" value="<?= $e($values[$key]??($key==='inspection_finding_id'?0:'')) ?>"><?php else: ?><textarea id="<?= $e($key) ?>" name="<?= $e($key) ?>" maxlength="<?= in_array($key,['description','observation'],true)?8000:(in_array($key,['evidence'],true)?2000:4000) ?>"><?= $e($values[$key]??'') ?></textarea><?php endif; ?></div><?php endforeach; ?>
<?php if($module==='quality'): ?><div><label for="blocks_release">Blokkeert vrijgave</label><select id="blocks_release" name="blocks_release"><option value="0">Nee</option><option value="1"<?= !empty($values['blocks_release'])?' selected':'' ?>>Ja</option></select></div><?php endif; ?></div><button>Opslaan</button>
<?php if($module==='procurement'): ?><p>Prijs en opdrachtwaarde zijn exclusief btw. Een andere bevoegde medewerker beoordeelt de opdracht. Registratie van verstrekking verzendt geen opdracht aan de leverancier.</p><?php endif; ?>
</form><?php if($record!==null): ?><p><a href="<?= $e($path.'?'.http_build_query(['project_id'=>$project,'building_id'=>$building])) ?>">Nieuwe registratie</a></p><?php endif; ?></section><?php endif; ?>
<?php endif; ?></main></body></html>
