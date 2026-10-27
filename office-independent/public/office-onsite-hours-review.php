<?php
declare(strict_types=1);
header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'
  ||getenv('OFFICE_TEST_ONSITE_HOURS_REVIEW_ENABLED')!=='enabled'||getenv('OFFICE_ONSITE_HOURS_REVIEWS_SCHEMA_VERIFIED')!=='enabled'){
  http_response_code(503);exit('Urenbeoordeling is nog niet vrijgegeven.');
}
$host=$_SERVER['HTTP_HOST']??''; $allowed=getenv('OFFICE_PUBLIC_HOST')?:'';
if(!is_string($host)||$allowed===''||!hash_equals($allowed,$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') {http_response_code(403);exit;}
if($method==='POST'&&(!is_string($_SERVER['HTTP_ORIGIN']??null)||!hash_equals('https://'.$allowed,$_SERVER['HTTP_ORIGIN']))){http_response_code(403);exit('Ongeldige herkomst.');}
if($method==='POST'&&(int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit('Formulier te groot.');}
try {
  foreach(['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider'] as $name)require_once dirname(__DIR__).'/src/Intake/'.$name.'.php';
  foreach(['OnSiteSessionReviewWriter','OnSiteSessionReviewReader','OnSiteHoursReviewToken','OnSiteHoursReviewPostAction','OnSiteHoursReviewReadiness','OnSitePresenceIntervalProjector'] as $name)require_once dirname(__DIR__).'/src/Inzet/'.$name.'.php';
  $dsn=getenv('OFFICE_INTAKE_PDO_DSN')?:'';
  if(!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)||getenv('OFFICE_INTAKE_DB_USER')!=='u213420663_office_test')throw new RuntimeException('hours_test_database_required');
  $secret=getenv('OFFICE_TEST_ONSITE_HOURS_REVIEW_CSRF_SECRET')?:'';
  if(strlen($secret)<32)throw new RuntimeException('hours_secret_required');
  $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER'),getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $actor=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
  if($actor===null){if($method==='POST'){http_response_code(401);exit('Sessie verlopen.');}header('Location: /office-test/inloggen',true,302);exit;}
  \Brebo\Office\Inzet\OnSiteHoursReviewReadiness::requireReady($db);
  if($method==='POST'){
    $body=file_get_contents('php://input',false,null,0,4097);
    if(!is_string($body))throw new RuntimeException('hours_body_required');
    $result=(new \Brebo\Office\Inzet\OnSiteHoursReviewPostAction(new \Brebo\Office\Inzet\OnSiteSessionReviewWriter($db)))->handle((string)($_SERVER['CONTENT_TYPE']??''),$body,$actor,$secret,time());
    header('Location: /office-test/inzet/onsite/uren-beoordelen?id='.rawurlencode($result['project_id']).'&saved=1',true,303);exit;
  }
  $project=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!is_int($project)){http_response_code(400);exit('Ongeldig project.');}
  $review=(new \Brebo\Office\Inzet\OnSiteSessionReviewReader($db))->forManager($actor->operatorId,(string)$project);
}catch(InvalidArgumentException|DomainException $e){http_response_code(in_array($e->getMessage(),['invalid_hours_form_token','invalid_hours_form_context'],true)?403:422);exit('Controleer het formulier, de reden en de sessie.');}
catch(Throwable $e){http_response_code(match($e->getMessage()){'hours_review_access_denied','hours_review_person_unavailable'=>403,'hours_review_request_conflict','hours_session_already_reviewed','ambiguous_review_evidence'=>409,'hours_review_media_type'=>415,'hours_review_payload_limit'=>413,default=>503});exit('Beoordeling niet verwerkt. Vernieuw de pagina of controleer de vrijgave.');}
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Uren beoordelen · Office</title><style>body{font:16px system-ui;max-width:1100px;margin:2rem auto;padding:1rem}table{border-collapse:collapse;width:100%}td,th{padding:1rem;border-bottom:1px solid #ccd5df;text-align:left}label{display:block;margin:.5rem 0}button{padding:.6rem}input,textarea{max-width:100%}</style><main><a href="/office-test/projecten/detail?id='.$project.'">← Project</a><h1>OnSite · uren beoordelen</h1><p>IN/UIT is aanwezigheidsbewijs. Alleen een expliciete beoordeling legt goedgekeurde tijd vast. Er wordt niets geboekt of betaald.</p>';
if(($_GET['saved']??'')==='1')echo '<p role="status">Beoordeling verwerkt. Hieronder staat de actuele Office-status.</p>';
if($review['active'])echo '<p>Er zijn open registraties; die kunnen nog niet worden goedgekeurd.</p>';
if($review['exceptions'])echo '<p>Er zijn afwijkende of overlappende registraties. Deze vereisen afzonderlijke controle.</p>';
echo '<table><thead><tr><th>Medewerker</th><th>IN / UIT (UTC)</th><th>Duur</th><th>Beoordeling</th></tr></thead><tbody>';
foreach($review['sessions'] as $session){
 echo '<tr><td>'.$h($session['display_name']).'</td><td>'.$h($session['entered_at']).'<br>'.$h($session['left_at']).'</td><td>'.$h($session['duration_seconds']).' seconden</td><td>';
 if($session['approval_status']==='pending'){
  $context=[(string)$project,$session['person_id'],$session['in_event_id'],$session['out_event_id']];
  $token=\Brebo\Office\Inzet\OnSiteHoursReviewToken::issue($actor,$context,$secret,time());
  echo '<form method="post" action="/office-test/inzet/onsite/uren-beoordelen">';
  foreach(array_combine(['project_id','person_id','in_event_id','out_event_id'],$context)+['form_token'=>$token] as $key=>$value)echo '<input type="hidden" name="'.$h($key).'" value="'.$h($value).'">';
  echo '<label>Correctie in seconden (leeg = gemeten duur)<input type="number" name="corrected_seconds" min="0" max="86400" step="1"></label><label>Reden (verplicht bij afkeuren of correctie)<textarea name="reason" maxlength="2000"></textarea></label><button name="decision" value="approved" type="submit">Uren goedkeuren</button> <button name="decision" value="rejected" type="submit">Afkeuren</button></form>';
 }else{echo '<strong>'.$h($session['approval_status']==='approved'?'Goedgekeurd':'Afgekeurd').'</strong><br>'.$h($session['reviewed_seconds']).' seconden<br>'.$h($session['reviewer_operator_id']).' · '.$h($session['reviewed_at']).' UTC<br>'.$h($session['reason']);}
 echo '</td></tr>';
}
if(!$review['sessions'])echo '<tr><td colspan="4">Geen volledige, beoordeelbare sessies beschikbaar.</td></tr>';
echo '</tbody></table></main></html>';
