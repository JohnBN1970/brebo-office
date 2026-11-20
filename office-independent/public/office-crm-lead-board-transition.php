<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled' || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
 || getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED')!=='enabled' || getenv('OFFICE_CRM_LEADS_WRITE_ENABLED')!=='enabled') { http_response_code(503); exit; }
$host=$_SERVER['HTTP_HOST']??'';
if (!is_string($host) || !hash_equals((string)getenv('OFFICE_PUBLIC_HOST'),$host)
 || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
 foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$provider->currentOperator();
 if (!$operator) { http_response_code(401); exit; }
 $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
 $active->execute([$operator->operatorId]); if (!$active->fetchColumn()) { http_response_code(403); exit; }
 if (session_status()!==PHP_SESSION_ACTIVE) {
  session_name('BREBO_CRM_LEAD_BOARD');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
  session_start();
 }
 $csrf=$_POST['csrf']??null;
 if (!is_string($csrf) || !isset($_SESSION['crm_lead_board_csrf']) || !hash_equals($_SESSION['crm_lead_board_csrf'],$csrf)) { http_response_code(403); exit; }
 $id=filter_var($_POST['lead_id']??null,FILTER_VALIDATE_INT);
 $target=$_POST['stage']??null;
 $next=['new'=>['contacted'],'contacted'=>['qualified','new'],'qualified'=>['contacted','accepted'],'accepted'=>['qualified']];
 if (!$id || !is_string($target) || !in_array($target,array_keys($next),true)) { http_response_code(400); exit; }
 $db->beginTransaction();
 $stmt=$db->prepare('SELECT stage,status,converted_opportunity_id FROM office_crm_leads WHERE id=? FOR UPDATE');
 $stmt->execute([$id]); $lead=$stmt->fetch(PDO::FETCH_ASSOC);
 if (!$lead || $lead['status']!=='active' || $lead['converted_opportunity_id']!==null || !in_array($target,$next[$lead['stage']]??[],true)) {
  $db->rollBack(); http_response_code(409); echo json_encode(['error'=>'Faseovergang niet toegestaan']); exit;
 }
 $stmt=$db->prepare('UPDATE office_crm_leads SET stage=? WHERE id=?'); $stmt->execute([$target,$id]);
 $stmt=$db->prepare("INSERT INTO office_crm_lead_events (lead_id,actor_operator_id,event_type,old_stage,new_stage) VALUES (?,?,'stage_changed',?,?)");
 $stmt->execute([$id,(string)$operator->operatorId,$lead['stage'],$target]);
 $db->commit(); echo json_encode(['ok'=>true]);
} catch (Throwable $e) { if (isset($db) && $db->inTransaction()) $db->rollBack(); http_response_code(503); echo json_encode(['error'=>'Opslaan mislukt']); }
