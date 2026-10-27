<?php
declare(strict_types=1);
// Authenticated, read-only entry points. No implied finance/calculation permissions.
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);header('Allow: GET');exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'||($_SERVER['HTTP_HOST']??'')!==getenv('OFFICE_PUBLIC_HOST')||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(503);exit;}
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);$area=basename((string)$path);
$descriptions=['calculatie-offertes'=>['Calculatie & Offertes','Calculaties, offerteversies en hun voortgang. Vanuit een project zie je uitsluitend de onderdelen van dat project.','De calculatie- en offertewerkbank is nog niet aangesloten op deze ingang.'], 'finance'=>['Finance','Begrotingen, verplichtingen, facturen, betalingen en resultaten. Bedrijfsbreed of binnen één project.','De financiële administratie is nog niet aangesloten. Er worden hier geen bedragen of betalingen getoond.'], 'informatie'=>['Informatie','Documenten, correspondentie, afspraken en kennis. Bedrijfsbreed of binnen één project.','Het centrale documenten- en informatiebeheer is nog niet aangesloten.']];
if(!isset($descriptions[$area])){http_response_code(404);exit;}
try{
 foreach(['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider']as$name)require_once dirname(__DIR__).'/src/Intake/'.$name.'.php';
 if(getenv('OFFICE_INTAKE_DB_USER')!=='u213420663_office_test'||!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',getenv('OFFICE_INTAKE_PDO_DSN')?:''))throw new RuntimeException('test_only');
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN'),getenv('OFFICE_INTAKE_DB_USER'),getenv('OFFICE_INTAKE_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $actor=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
 if($actor===null){header('Location: /office-test/inloggen',true,302);exit;}
 $project=null;if(isset($_GET['project_id'])){$project=filter_var($_GET['project_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($project)){http_response_code(400);exit('Ongeldig project.');}}
 $query=$db->prepare("SELECT DISTINCT p.id,p.code,p.title FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id WHERE m.operator_id=? AND m.permission IN ('read','manage') AND a.active=1".($project===null?'':' AND p.id=?').' ORDER BY p.id DESC LIMIT 100');
 $query->execute($project===null?[$actor->operatorId]:[$actor->operatorId,$project]);$projects=$query->fetchAll(PDO::FETCH_ASSOC);
 if($project!==null&&count($projects)!==1){http_response_code(404);exit('Project niet beschikbaar.');}
}catch(Throwable){http_response_code(503);exit('Overzicht tijdelijk niet beschikbaar.');}
$h=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');[$title,$intro,$state]=$descriptions[$area];
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$h($title).' · BREBO Office</title><style>body{font:16px system-ui;color:#243244;max-width:1180px;padding:24px;margin:auto}a{color:#175a91}.notice{padding:18px;background:#fff4d8;border-radius:9px}li{margin:12px 0}</style></head><body><main><h1>'.$h($title).'</h1><p>'.$h($intro).'</p><p class="notice">'.$h($state).'</p><h2>'.($project===null?'Toegankelijke projecten':'Geselecteerd project').'</h2><ul>';
foreach($projects as$row)echo '<li><a href="/office-test/projecten/detail?id='.(int)$row['id'].'">'.$h($row['code'].' · '.$row['title']).'</a></li>';
if(!$projects)echo '<li>Geen projecten beschikbaar binnen je toegang.</li>';
echo '</ul><p>De ingangen gebruiken dezelfde projectgegevens. Bestaande toegangscontroles blijven voor iedere handeling gelden.</p></main></body></html>';
