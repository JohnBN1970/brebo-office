<?php
declare(strict_types=1);
use Brebo\Office\Dashboard\OfficeDashboardCatalog as Catalog;
use Brebo\Office\Dashboard\OfficeDashboardKpis;
use Brebo\Office\Dashboard\OfficeDashboardPreferences;
use Brebo\Office\Intake\OfficeMount;

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$script=(string)file_get_contents(__DIR__.'/../resources/office-dashboard.js');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'sha256-".base64_encode(hash('sha256',$script,true))."'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_PERSONAL_DASHBOARD_ENABLED')!=='enabled'||getenv('OFFICE_DASHBOARD_PREFERENCES_SCHEMA_VERIFIED')!=='enabled'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'){http_response_code(503);exit('Dashboard niet beschikbaar.');}
$host=getenv('OFFICE_PUBLIC_HOST');
if(!is_string($host)||$host===''||!hash_equals($host,(string)($_SERVER['HTTP_HOST']??''))||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit;}
if($method==='POST'&&(!hash_equals('https://'.$host,(string)($_SERVER['HTTP_ORIGIN']??''))||(int)($_SERVER['CONTENT_LENGTH']??0)>16384)){http_response_code(403);exit;}
$view=$_GET['view']??'projects';
if(!is_string($view)||!in_array($view,['projects','finance','quality','builder','wallboard'],true)){http_response_code(400);exit('Onbekend dashboard.');}
$monitor=$view==='wallboard';$json=($_GET['format']??'')==='json';
if($json&&!$monitor){http_response_code(400);exit;}
try {
  foreach(['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)require_once __DIR__.'/../src/Intake/'.$file;
  foreach(['OfficePersonalDashboard.php','OfficeDashboardPreferences.php','OfficeDashboardKpis.php','OfficeDashboardCatalog.php'] as $file)require_once __DIR__.'/../src/Dashboard/'.$file;
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
  if($operator===null){if($json||$method==='POST'){http_response_code(401);exit;}header('Location: '.OfficeMount::route('inloggen'),true,302);exit;}
  $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active = 1');$active->execute([$operator->operatorId]);
  if($active->fetchColumn()===false){http_response_code(403);exit;}
  $suite=(new OfficeDashboardKpis($db))->suite($operator->operatorId);
  if($view==='finance'&&!$suite['finance']){http_response_code(403);exit('Geen Finance-toegang.');}
  $widgets=Catalog::visible($suite['widgets'],$monitor);
  $preferences=new OfficeDashboardPreferences($db);
  $state=$preferences->boards($operator->operatorId,array_keys($suite['widgets']));
  $boardId=$_GET['board']??'main';
  if(!is_string($boardId)||!preg_match('/^[a-z0-9_-]{1,32}$/D',$boardId))throw new InvalidArgumentException();
  $board=$state['boards'][$boardId]??['name'=>'Nieuw dashboard','columns'=>3,'widgets'=>array_slice(array_keys($widgets),0,6)];
  $secret=getenv('OFFICE_DASHBOARD_CSRF_SECRET');
  $csrf=is_string($secret)&&strlen($secret)>=32?hash_hmac('sha256','office-dashboard-preferences:v1:'.$operator->sessionId,$secret):null;
  if($method==='POST'){
    $expected=$csrf;$token=$_POST['csrf']??null;
    if($view!=='builder'||$expected===null||!is_string($token)||!hash_equals($expected, $token)){http_response_code(403);exit('Ongeldige sessiebeveiliging.');}
    $requested=$_POST['widgets']??[];
    if(!is_array($requested)||!array_is_list($requested)||count($requested)>6||array_filter($requested,static fn($v)=>!is_string($v)))throw new InvalidArgumentException();
    if(!is_string($_POST['revision']??null)||!is_string($_POST['name']??null))throw new InvalidArgumentException();
    $preferences->saveBoard($operator->operatorId,$boardId,['name'=>$_POST['name'],'columns'=>$_POST['columns']??3,'widgets'=>$requested],array_keys($suite['widgets']),$_POST['revision']);
    header('Location: /office-test/mijn-dashboard?view=builder&board='.rawurlencode($boardId).'&saved=1',true,303);exit;
  }
} catch(DomainException){http_response_code(409);exit('Dashboard is intussen gewijzigd. Laad opnieuw.');}
catch(InvalidArgumentException){http_response_code(400);exit('Ongeldige dashboardinstellingen.');}
catch(Throwable){http_response_code(503);exit('Dashboard tijdelijk niet beschikbaar.');}
$catalog=Catalog::all();
$selected=in_array($view,['builder','wallboard'],true)?array_values(array_intersect($board['widgets'],array_keys($widgets))):array_keys(array_filter($widgets,static fn($id)=>$catalog[$id][1]===$view,ARRAY_FILTER_USE_KEY));
$updated='Bijgewerkt '.(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('d-m-Y H:i:s');
$display=[];
foreach($selected as $id){$w=$widgets[$id];$display[]=['id'=>$id,'label'=>$catalog[$id][0],'value'=>Catalog::format($id,$w['value']),'note'=>$w['note'],'tone'=>$w['tone']];}
$projectVisual=null;
$totalProjects=$widgets['projects']['value']??null;
$criticalProjects=$widgets['critical']['value']??null;
$waitingProjects=$widgets['waiting']['value']??null;
if(is_int($totalProjects)&&$totalProjects>0&&is_int($criticalProjects)&&is_int($waitingProjects)&&$criticalProjects>=0&&$waitingProjects>=0&&$criticalProjects+$waitingProjects<=$totalProjects){
  $projectVisual=['kind'=>'project-status','parts'=>[
    ['label'=>'Overig','count'=>$totalProjects-$criticalProjects,'tone'=>'ontrack'],
    ['label'=>'In wacht','count'=>$waitingProjects,'tone'=>'waiting'],
    ['label'=>'Kritiek','count'=>$criticalProjects,'tone'=>'critical']
  ]];
}
foreach($display as &$entry){if($entry['id']==='projects'&&$projectVisual!==null)$entry['visual']=$projectVisual;}
unset($entry);
if($json){header('Content-Type: application/json; charset=utf-8');echo json_encode(['widgets'=>$display,'columns'=>$board['columns'],'updated'=>$updated],JSON_THROW_ON_ERROR);exit;}
$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$titles=['projects'=>'Projectcockpit','finance'=>'Financecockpit','quality'=>'Kennis & Kwaliteit','builder'=>'Dashboardbouwer','wallboard'=>'KPI Wallboard'];
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$titles[$view].' · BREBO Office</title><style>'.file_get_contents(__DIR__.'/../resources/office-dashboard.css').'</style></head><body><main><div class="office-dash-heading"><div><h1>'.$titles[$view].'</h1><p>Actuele Office-gegevens · jouw toegankelijke dossiers</p></div></div><nav class="office-dash-tabs" aria-label="Dashboards">';
foreach($titles as $key=>$title){if($key==='finance'&&!$suite['finance'])continue;echo '<a href="/office-test/mijn-dashboard?view='.$key.'"'.($view===$key?' aria-current="page"':'').'>'.$e($title).'</a>';}
echo '</nav><div class="office-dash-legend"><span>Groen: gecontroleerd / in de pas</span><span>Geel: aandacht / wacht</span><span>Rood: afwijking / kritiek</span></div>';
if($monitor){
  echo '<section class="office-dash-monitor" data-wallboard data-boards="'.$e(implode(',',array_keys($state['boards']))).'"><h2>Kantooroverzicht</h2><p>Alleen vrijgegeven soorten tellingen. Geen namen, bedragen of individuele uren.</p><div class="office-dash-toolbar"><button type="button" data-fullscreen>Volledig scherm</button><button type="button" data-present aria-pressed="false">Presentatiemodus</button><span>Verversing elke minuut</span></div>';
}
echo '<div class="office-dash-grid" data-columns="'.(in_array($view,['builder','wallboard'],true)?(int)$board['columns']:3).'">';
foreach($display as $w){echo '<article class="office-dash-card" data-tone="'.$e($w['tone']).'"><span>'.$e($w['label']).'</span><strong>'.$e($w['value']).'</strong><small>'.$e($w['note']).'</small>';if(isset($w['visual'])&&$w['visual']['kind']==='project-status'){
  $parts=$w['visual']['parts'];$sum=array_sum(array_column($parts,'count'));
  echo '<div class="office-dash-mini-chart" role="img" aria-label="'.$e(implode(', ',array_map(static fn($p)=>$p['label'].': '.$p['count'],$parts))).'">';
  foreach($parts as $part){if($part['count']>0)echo '<span data-status="'.$e($part['tone']).'" style="width:'.number_format($part['count']/$sum*100,4,'.','').'%"></span>';}
  echo '</div>';
}
if(!$monitor)echo '<a href="/office-test'.$e($catalog[$w['id']][4]).'">Bekijk details ↗</a>';echo '</article>';}
echo '</div><p class="office-dash-status" role="status">'.$e($updated).'</p>';
if(!$display)echo '<p class="office-dash-empty">Geen toegankelijke widgets voor deze weergave. Kies geschikte widgets in de dashboardbouwer.</p>';
if($monitor)echo '</section>';
if($view==='builder'){
  echo '<section class="office-dash-panel office-dash-builder"><h2>Indeling aanpassen</h2><nav class="office-dash-tabs" aria-label="Opgeslagen dashboards">';
  foreach($state['boards'] as $id=>$saved)echo '<a href="?view=builder&amp;board='.$e($id).'">'.$e($saved['name']).'</a>';
  if(count($state['boards'])<10)echo '<a href="?view=builder&amp;board=new-'.bin2hex(random_bytes(4)).'">＋ Nieuw dashboard</a>';
  echo '</nav>';
  if(($_GET['saved']??'')==='1')echo '<p role="status">Dashboard opgeslagen.</p>';
  if($csrf!==null){
    echo '<form method="post" action="?view=builder&amp;board='.$e($boardId).'"><input type="hidden" name="csrf" value="'.$e($csrf).'"><input type="hidden" name="revision" value="'.$e($state['revision']).'"><label>Naam <input name="name" value="'.$e($board['name']).'" maxlength="60" required></label><label>Kolommen <select name="columns">';
    foreach([1,2,3] as $n)echo '<option'.($board['columns']===$n?' selected':'').'>'.$n.'</option>';
    echo '</select></label><p>Kies maximaal zes widgets. Sleep om te sorteren of gebruik de pijlen.</p><ol data-widget-order>';
    foreach(array_unique(array_merge($board['widgets'],array_keys($widgets))) as $id){if(!isset($widgets[$id]))continue;echo '<li draggable="true"><span aria-hidden="true">⠿</span><label><input type="checkbox" name="widgets[]" value="'.$e($id).'"'.(in_array($id,$board['widgets'],true)?' checked':'').'> '.$e($catalog[$id][0]).($catalog[$id][3]?' · monitor geschikt':' · privé').'</label><button type="button" data-move="up" aria-label="'.$e($catalog[$id][0]).' omhoog">↑</button><button type="button" data-move="down" aria-label="'.$e($catalog[$id][0]).' omlaag">↓</button></li>';}
    echo '</ol><button>Dashboard opslaan</button></form><p><a href="?view=wallboard&amp;board='.$e($boardId).'">Open dit wallboard ↗</a></p>';
  }else echo '<p>Opslaan niet beschikbaar: sessiebeveiliging ontbreekt.</p>';
  echo '</section>';
}
if($view==='projects'){
  // A real, scope-aware project-status visualization. Never infer status from an
  // eight-hour reference or substitute sample values for missing Office data.
  $total=$widgets['projects']['value']??null;
  $critical=$widgets['critical']['value']??null;
  $waiting=$widgets['waiting']['value']??null;
  if(is_int($total)&&is_int($critical)&&is_int($waiting)&&$total>0&&$critical>=0&&$waiting>=0&&$critical+$waiting<=$total){
    $onTrack=$total-$critical-$waiting;
    echo '<section class="office-dash-panel office-dash-visual"><div class="office-dash-visual-heading"><div><h2>Projectstatus</h2><p>Verdeling van toegankelijke projecten op basis van de actuele Office-statussen.</p></div><a href="/office-test/projecten?view=gantt">Bekijk projectplanning ↗</a></div>';
    echo '<div class="office-dash-status-track" role="img" aria-label="'.$e($onTrack).' overige projecten, '.$e($waiting).' in wacht en '.$e($critical).' kritiek">';
    foreach([['ontrack',$onTrack],['waiting',$waiting],['critical',$critical]] as [$kind,$count]){
      if($count===0)continue;
      echo '<span class="office-dash-status-segment" data-status="'.$kind.'" style="width:'.number_format($count/$total*100,4,'.','').'%"></span>';
    }
    echo '</div><div class="office-dash-status-keys">';
    foreach([['ontrack','Overige projecten',$onTrack],['waiting','In wacht',$waiting],['critical','Kritiek',$critical]] as [$kind,$label,$count]){
      echo '<div class="office-dash-status-key"><span class="office-dash-status-dot" data-status="'.$kind.'"></span><span>'.$e($label).'</span><strong>'.$e($count).'</strong></div>';
    }
    echo '</div></section>';
  }else{
    echo '<section class="office-dash-panel"><h2>Projectstatus</h2><p>Verdeling niet beschikbaar: de actuele projectstatussen zijn niet volledig beschikbaar.</p></section>';
  }
  if($suite['finance']){
    echo '<section class="office-dash-panel"><h2>Werkbegroting versus realisatie</h2><table><thead><tr><th>Project</th><th>Vergrendelde begroting</th><th>Gereconcilieerde realisatie</th></tr></thead><tbody>';
    foreach($suite['budget_rows'] as $row)echo '<tr><td><a href="/office-test/projecten/detail?id='.(int)$row['id'].'&amp;tab=werkbegroting">'.$e($row['title']).'</a></td><td>'.$e(Catalog::format('budget',$row['data']['kpis']['baseline_cents']??null)).'</td><td>'.$e(Catalog::format('budget',$row['data']['kpis']['actual_cents']??null)).'</td></tr>';
    echo '</tbody></table><p>Realisatie blijft niet beschikbaar zolang de bestaande werkbegrotingsbron niet is gereconcilieerd. Aanwezigheid geldt niet als geboekte kosten.</p></section>';
  }
  require_once __DIR__.'/../src/Project/OfficeProjectPortfolioGantt.php';
  echo '<section class="office-dash-panel"><h2>Planning & projectrisico’s</h2>'.\Brebo\Office\Project\OfficeProjectPortfolioGantt::render($suite['projects'],'/office-test').'</section><p><a href="/office-test/projecten">Open projectbewaking en werkbegrotingen ↗</a></p>';
}
if($view==='finance'){
  // The financial composition is meaningful only when both amounts are sourced
  // from the authorized Finance reader; never synthesize a trend or cash balance.
  $debtors=$widgets['debtors']['value']??null;
  $overdue=$widgets['overdue']['value']??null;
  if(is_int($debtors)&&is_int($overdue)&&$debtors>0&&$overdue>=0&&$overdue<=$debtors){
    $current=$debtors-$overdue;
    echo '<section class="office-dash-panel office-dash-visual"><div class="office-dash-visual-heading"><div><h2>Openstaande verkoopfacturen</h2><p>Verdeling van het openstaande bedrag in niet-vervallen en vervallen posten.</p></div><a href="/office-test/finance/verkoop">Bekijk facturen ↗</a></div>';
    echo '<div class="office-dash-status-track" role="img" aria-label="'.$e(Catalog::format('debtors',$current)).' niet vervallen en '.$e(Catalog::format('overdue',$overdue)).' vervallen">';
    foreach([['ontrack',$current],['critical',$overdue]] as [$kind,$amount]){
      if($amount===0)continue;
      echo '<span class="office-dash-status-segment" data-status="'.$kind.'" style="width:'.number_format($amount/$debtors*100,4,'.','').'%"></span>';
    }
    echo '</div><div class="office-dash-status-keys">';
    foreach([['ontrack','Niet vervallen',$current],['critical','Vervallen',$overdue]] as [$kind,$label,$amount]){
      echo '<div class="office-dash-status-key"><span class="office-dash-status-dot" data-status="'.$kind.'"></span><span>'.$e($label).'</span><strong>'.$e(Catalog::format('debtors',$amount)).'</strong></div>';
    }
    echo '</div></section>';
  }else{
    echo '<section class="office-dash-panel"><h2>Openstaande verkoopfacturen</h2><p>Verdeling niet beschikbaar: openstaande en vervallen bedragen zijn niet volledig vergelijkbaar of er zijn geen openstaande posten.</p></section>';
  }
  require_once __DIR__.'/../src/Finance/OfficeFinanceDashboard.php';
  echo \Brebo\Office\Finance\OfficeFinanceDashboard::render($suite['finance_data'],static fn(string $path,array $query=[]):string=>'/office-test/'.$path.($query?'?'.http_build_query($query):''));
}
if($view==='quality'){
  // These are independent, authorized counts: they are deliberately not
  // represented as percentages of one another or as a fabricated trend.
  $qualityCounts=[];
  foreach([['quality','Open kwaliteitsacties','waiting'],['deviations','Geregistreerde afwijkingen','critical'],['risks','Open projectrisico’s','critical']] as [$id,$label,$kind]){
    $value=$widgets[$id]['value']??null;
    if(is_int($value)&&$value>=0)$qualityCounts[]=[$label,$value,$kind];
  }
  if($qualityCounts){
    $maximum=max(1,...array_column($qualityCounts,1));
    echo '<section class="office-dash-panel office-dash-visual"><div class="office-dash-visual-heading"><div><h2>Kwaliteit & risico’s</h2><p>Actuele aantallen uit toegankelijke Office-dossiers. Geen onderlinge percentages of fictieve historie.</p></div></div><div class="office-dash-bars">';
    foreach($qualityCounts as [$label,$value,$kind]){
      echo '<div class="office-dash-bar-row"><div class="office-dash-bar-label"><span>'.$e($label).'</span><strong>'.$e($value).'</strong></div><div class="office-dash-bar-track" role="img" aria-label="'.$e($label).': '.$e($value).'"><span data-status="'.$kind.'" style="width:'.number_format($value/$maximum*100,4,'.','').'%"></span></div></div>';
    }
    echo '</div></section>';
  }else echo '<section class="office-dash-panel"><h2>Kwaliteit & risico’s</h2><p>Grafische aantallen niet beschikbaar: de geautoriseerde bronnen leveren geen betrouwbare tellingen.</p></section>';
}
if($view==='quality')echo '<section class="office-dash-panel"><h2>Kennis en documenten</h2><p>Geaggregeerde inspectie- en proceshandboekstatus: niet beschikbaar. Projectdocumenten staan hierboven; documenten met meerdere contexten vereisen afzonderlijke autorisatie.</p><a href="/office-test/gebouwen">Gebouwen en inspecties ↗</a> · <a href="/office-test/documenten">Documenten ↗</a></section>';
echo '<script>'.$script.'</script></main></body></html>';
