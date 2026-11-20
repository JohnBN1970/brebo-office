<?php
declare(strict_types=1);
// Explicit test-only mail-to-dossier action. Never auto-associate buildings.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/plain; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_MAIL_CONTEXT_LINKS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED') !== 'enabled') { http_response_code(404); exit; }
$host=(string)($_SERVER['HTTP_HOST']??'');
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed==='' || !hash_equals($allowed,$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if ($origin!=='' && !hash_equals('https://'.$allowed,$origin)) { http_response_code(403); exit; }
$csrf=(string)($_POST['csrf']??'');
$cookie=(string)($_COOKIE['office_mail_context_csrf']??'');
if (strlen($csrf)!==64 || !ctype_xdigit($csrf) || !hash_equals($cookie,$csrf)) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
$message=filter_var($_POST['message_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$entity=filter_var($_POST['entity_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$module=$_POST['module']??'';
$action=$_POST['action']??'';
if (!is_int($message) || !is_int($entity) || !is_string($module)
  || !in_array($module,['crm','leads','projects'],true)
  || !in_array($action,['link','unlink'],true)) { http_response_code(400); exit('Ongeldige koppeling.'); }
try {
  foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Mail/OfficeMailContextAccess.php';
  require_once dirname(__DIR__).'/src/Mail/OfficeMailContextLinksRepository.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) { http_response_code(401); exit('Aanmelden vereist.'); }
  $access=new \Brebo\Office\Mail\OfficeMailContextAccess($db);
  $links=new \Brebo\Office\Mail\OfficeMailContextLinksRepository($db);
  $actor=(string)$operator->operatorId;
  if ($action==='link') $links->link($message,$module,$entity,$actor,[$access,'canReadMessage'],[$access,'canAccessEntity']);
  else $links->unlink($message,$module,$entity,$actor,[$access,'canReadMessage'],[$access,'canAccessEntity']);
  echo $action==='link' ? 'Koppeling opgeslagen.' : 'Koppeling verwijderd.';
} catch (RuntimeException $e) { http_response_code(403); echo 'Geen toegang of koppeling niet beschikbaar.'; }
catch (Throwable $e) { http_response_code(503); echo 'Mailkoppeling tijdelijk niet beschikbaar.'; }
