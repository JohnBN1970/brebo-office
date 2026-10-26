<?php
declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
ini_set('display_errors','0');
require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST');http_response_code(405);exit; }
if (!\Brebo\Office\Intake\OfficeMount::isTest() || getenv('OFFICE_TEST_INZET_OPERATIONAL_REVIEW_ENABLED')!=='enabled') {
  http_response_code(404);exit;
}
$host=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host==='' || !is_string($_SERVER['HTTP_HOST']??null)
  || !hash_equals($host,$_SERVER['HTTP_HOST']) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {
  http_response_code(403);exit('Herkomst niet toegestaan.');
}
$writeEnabled=getenv('OFFICE_TEST_INZET_OPERATIONAL_REVIEW_WRITE_ENABLED')==='enabled';
if ($method==='POST') {
  if (!$writeEnabled) {http_response_code(403);exit('Opslaan is nog niet ingeschakeld.');}
  if (strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))!=='application/x-www-form-urlencoded'
    || (int)($_SERVER['CONTENT_LENGTH']??0)>32768) {http_response_code(400);exit('Ongeldig verzoek.');}
  $origin=$_SERVER['HTTP_ORIGIN']??null;$referer=$_SERVER['HTTP_REFERER']??null;
  $sameOrigin=static function(mixed $url) use($host):bool {
    if (!is_string($url)) return false;$parts=parse_url($url);
    return is_array($parts) && ($parts['scheme']??null)==='https' && ($parts['host']??null)===$host
      && (!isset($parts['port']) || $parts['port']===443) && !isset($parts['user']) && !isset($parts['pass']);
  };
  if ((($origin===null || $origin==='null') && !$sameOrigin($referer))
    || ($origin!==null && $origin!=='null' && !$sameOrigin($origin))
    || ($referer!==null && !$sameOrigin($referer))) {http_response_code(403);exit('Herkomst niet toegestaan.');}
}
if (getenv('OFFICE_TEST_INZET_OPERATIONAL_SCHEMA_VERIFIED')!=='enabled'
  || getenv('OFFICE_TEST_INZET_OPERATIONAL_IDENTITIES_VERIFIED')!=='enabled') {
  http_response_code(503);exit('Urenbeoordeling is nog niet vrijgegeven.');
}
try {
  foreach (['WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider'] as $file) {
    require_once dirname(__DIR__).'/src/Intake/'.$file.'.php';
  }
  foreach (['OfficeInzetOperationalReviewConfig','OfficeInzetOperationalReview','OfficeInzetOperationalReviewWorkbench'] as $file) {
    require_once dirname(__DIR__).'/src/Inzet/'.$file.'.php';
  }
  $office=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($office),$_COOKIE,time()))->currentOperator();
  if ($operator===null) {
    if ($method==='POST') {http_response_code(401);exit('Meld je opnieuw aan.');}
    header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;
  }
  $secret=null;
  if ($writeEnabled) {
    $secret=getenv('OFFICE_TEST_INZET_OPERATIONAL_CSRF_SECRET');
    if (!is_string($secret) || strlen($secret)<32) throw new RuntimeException('Operational form configuration required');
  }
  $root=getenv('OFFICE_TEST_INZET_OPERATIONAL_DATA_ROOT')?:'';$web=(string)($_SERVER['DOCUMENT_ROOT']??'');
  $people=\Brebo\Office\Inzet\OfficeInzetOperationalReviewConfig::identityMap(
    getenv('OFFICE_TEST_INZET_OPERATIONAL_IDENTITY_MAP_PATH')?:'',$root,$web);
  $operational=\Brebo\Office\Inzet\OfficeInzetOperationalReviewConfig::open(
    getenv('OFFICE_TEST_INZET_OPERATIONAL_SQLITE_PATH')?:'',$root,$web,$method==='POST',[
      getenv('OFFICE_TEST_INZET_SQLITE_PATH'),getenv('OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'),
    ]);
  $review=new \Brebo\Office\Inzet\OfficeInzetOperationalReview($office,$operational,$people);
  $rawPage=$_GET['page']??'1';$rawProject=$_GET['project']??'';
  $page=filter_var($rawPage,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
  $project=$rawProject===''?null:filter_var($rawProject,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if (!is_int($page) || ($project!==null && !is_int($project))) {http_response_code(400);exit('Ongeldig filter.');}
  $base=\Brebo\Office\Intake\OfficeMount::basePath();$path=$base.'/inzet/uren-beoordelen';
  if ($method==='POST') {
    $id=$_POST['id']??null;$revision=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $state=$_POST['decision']??null;$reason=$_POST['reason']??null;$snapshot=$_POST['snapshot']??null;
    if (!is_string($id) || $id==='' || strlen($id)>128 || !is_int($revision) || !is_string($state) || !is_string($reason) || !is_string($snapshot) || !preg_match('/^[a-f0-9]{64}$/D',$snapshot)) {
      http_response_code(400);exit('Ongeldige beoordeling.');
    }
    if (!\Brebo\Office\Inzet\OfficeInzetOperationalReviewCsrf::verify($_POST['csrf']??null,$operator,$id,$revision,$snapshot,$secret)) {
      http_response_code(403);exit('Ongeldig of verlopen formulier.');
    }
    $review->decide($operator,$id,$revision,$state,$reason,$snapshot);
    $query=['page'=>$page,'saved'=>$id];if ($project!==null) $query['project']=$project;
    header('Location: '.$path.'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986).'#registration-'.substr(hash('sha256',$id),0,16),true,303);exit;
  }
  $operational->beginTransaction();
  try {$report=$review->listForOperator($operator,$project,$page);}finally{$operational->rollBack();}
  $saved=$_GET['saved']??null;if (!is_string($saved) || strlen($saved)>128) $saved=null;
  $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Uren beoordelen · BREBO Office test</title>'
    .'<style>*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#182638;font:16px system-ui,sans-serif}main{max-width:1050px;margin:28px auto;padding:0 20px}a{color:#175a91}h1{margin-bottom:8px}h2{font-size:19px;margin:0}.registration{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:22px;margin:18px 0}.heading{display:flex;gap:12px;justify-content:space-between;align-items:baseline;flex-wrap:wrap}.state{background:#eaf0f7;border-radius:20px;padding:5px 12px;font-size:14px}dl{display:flex;gap:32px;flex-wrap:wrap}dt{color:#526273;font-size:14px}dd{margin:4px 0;font-size:22px;font-weight:600}form{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.registration form{display:grid}textarea{width:100%;resize:vertical}input,select,textarea,button{font:inherit;border:1px solid #a4b0be;border-radius:5px;padding:9px 12px}button{cursor:pointer;background:white}.approve{background:#175a91;color:white;border-color:#175a91}.actions{display:flex;gap:10px;flex-wrap:wrap}small,.footer-note{color:#526273}.description{white-space:pre-wrap;overflow-wrap:anywhere}nav{margin:20px 0}p[role=status]{border-left:4px solid #216d43;padding:10px 14px;background:#edf8f0}@media(max-width:600px){main{padding:0 12px}.registration{padding:16px}.actions button{width:100%}dl{gap:18px}select{max-width:100%}}</style></head><body><main>'
    .'<p><a href="'.$e($base.'/projecten?view=inzet').'">← Terug naar Inzet</a></p>'
    .\Brebo\Office\Inzet\OfficeInzetOperationalReviewWorkbench::render($report,$operator,$secret,$saved).'</main></body></html>';
} catch (DomainException $denied) {
  http_response_code(403);exit('Geen actuele bevoegdheid voor deze beoordeling. Controleer je aanmelding en projectrechten.');
} catch (InvalidArgumentException $invalid) {
  http_response_code(422);exit('Vul een geldige beoordeling in. Geef een toelichting bij afwijzen, correctie vragen of een urenafwijking.');
} catch (LogicException $changed) {
  http_response_code(409);exit('De registratie is gewijzigd of moet eerst worden gecontroleerd. Ververs de pagina.');
} catch (PDOException $database) {
  $busy=in_array((int)($database->errorInfo[1]??0),[5,6],true);
  http_response_code($busy?409:503);exit($busy?'De registratie wordt bijgewerkt. Ververs de pagina.':'Urenbeoordeling tijdelijk niet beschikbaar.');
} catch (Throwable $unavailable) {
  http_response_code(503);exit('Urenbeoordeling tijdelijk niet beschikbaar.');
}
