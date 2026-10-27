<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {header('Allow: POST');http_response_code(405);exit;}
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
 || getenv('OFFICE_NATIVE_PROJECTS_ENABLED')!=='enabled'
 || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')!=='enabled'
 || getenv('OFFICE_TEST_PROJECT_MAP_COORDINATES_VERIFIED')!=='enabled'
 || getenv('OFFICE_TEST_ZONE_WRITE_ENABLED')!=='enabled'
 || getenv('OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED')!=='enabled'
 || getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'
 || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {http_response_code(503);exit('Zoneopslag niet beschikbaar.');}
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST')?:'';
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$referer=(string)($_SERVER['HTTP_REFERER']??'');
$refererOrigin='';
if ($referer!=='') {
  $parts=parse_url($referer);
  if (is_array($parts) && ($parts['scheme']??'')==='https' && isset($parts['host'])
    && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']))
    $refererOrigin='https://'.$parts['host'];
}
$expectedOrigin='https://'.$allowed;
if (!is_string($host) || $allowed==='' || !hash_equals($allowed,$host)
  || !($origin!=='' ? hash_equals($expectedOrigin,$origin) : hash_equals($expectedOrigin,$refererOrigin))) {
  http_response_code(403);exit('Ongeldige herkomst.');
}
if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) {http_response_code(413);exit('Formulier te groot.');}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  foreach (['OnSiteOfficeCircleCreateService','OnSiteOfficeZoneFormToken','OnSiteOfficeZonePostAction'] as $class)
    require_once dirname(__DIR__).'/src/Inzet/'.$class.'.php';
  foreach (['OfficeBuildingZoneRepository','OfficeBuildingZoneReadiness'] as $class)
    require_once dirname(__DIR__).'/src/Buildings/'.$class.'.php';
  $secret=getenv('OFFICE_TEST_ZONE_CSRF_SECRET')?:'';
  if (strlen($secret)<32) throw new RuntimeException('zone_secret_unavailable');
  $dsn=getenv('OFFICE_INTAKE_PDO_DSN')?:'';
  if (!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)
    || getenv('OFFICE_INTAKE_DB_USER')!=='u213420663_office_test')
    throw new RuntimeException('zone_database_unavailable');
  $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER'),getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $actor=$provider->currentOperator();
  if ($actor===null) {http_response_code(401);exit('Sessie verlopen.');}
  \Brebo\Office\Buildings\OfficeBuildingZoneReadiness::requireReady($db);
  $body=file_get_contents('php://input',false,null,0,4097);
  if (!is_string($body)) throw new RuntimeException('zone_body_unavailable');
  $action=new \Brebo\Office\Inzet\OnSiteOfficeZonePostAction(new \Brebo\Office\Inzet\OnSiteOfficeCircleCreateService($db));
  $result=$action->handle('POST',$host,$allowed,$origin!==''?$origin:$refererOrigin,(string)($_SERVER['CONTENT_TYPE']??''),$body,$actor,$secret,time());
  header('Location: /office-test/inzet/projectkaart?id='.rawurlencode($result['project_id'])
    .'&building_id='.rawurlencode($result['building_id']).'&zone_created='.rawurlencode($result['zone_id']),true,303);
} catch (InvalidArgumentException $e) {
  http_response_code(in_array($e->getMessage(),['invalid_zone_form_token','invalid_zone_form_context','unauthorized_building_zone'],true)?403:422);
  exit('Zonegegevens of formulierbevestiging ongeldig.');
} catch (Throwable $e) {
  http_response_code(match ($e->getMessage()) {
    'zone_revision_conflict'=>409,'zone_media_type_unsupported'=>415,
    'zone_payload_too_large'=>413,default=>503
  });
  exit($e->getMessage()==='zone_revision_conflict'?'Zone is inmiddels gewijzigd. Vernieuw de pagina.':'Zone kon niet worden opgeslagen.');
}
