<?php
declare(strict_types=1);
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Inzet\OnSiteOfficeCircleCreateService;
use Brebo\Office\Inzet\OnSiteOfficeZoneFormToken;
use Brebo\Office\Inzet\OnSiteOfficeZonePostAction;
use Brebo\Office\Buildings\OfficeBuildingZoneReadiness;
use Brebo\Office\Buildings\OfficeBuildingZoneRepository;
foreach (['Intake/WebsiteCrmOperatorContext','Buildings/OfficeBuildingSchema','Buildings/OfficeBuildingZoneSchema',
  'Buildings/OfficeBuildingZoneRepository','Buildings/OfficeBuildingZoneReadiness','Project/OfficeNativeProjectAccess',
  'Inzet/OnSiteOfficeCircleCreateService','Inzet/OnSiteOfficeZoneFormToken','Inzet/OnSiteOfficeZonePostAction'] as $class) {
  require_once dirname(__DIR__).'/src/'.$class.'.php';
}
$dsn=getenv('OFFICE_INZET_TEST_DSN') ?: '';
if (!preg_match('/dbname=office_inzet_ci(?:;|$)/',$dsn)) throw new RuntimeException('Dedicated CI database required');
$base=new PDO($dsn,getenv('OFFICE_INZET_TEST_USER') ?: '',getenv('OFFICE_INZET_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$base->exec('CREATE DATABASE IF NOT EXISTS office_inzet_ci_zone_forms');
$dsn=str_replace('dbname=office_inzet_ci','dbname=office_inzet_ci_zone_forms',$dsn);
$db=new PDO($dsn,getenv('OFFICE_INZET_TEST_USER') ?: '',getenv('OFFICE_INZET_TEST_PASSWORD') ?: '',
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$actor=static fn(string $id,string $session='a')=>new WebsiteCrmOperatorContext($id,str_repeat($session,64),false);
$service=new OnSiteOfficeCircleCreateService($db);
if (($argv[1] ?? '')==='--worker') {
  $payload=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
  echo $service->create($actor('manager-test'),'17',$payload['input'],$payload['key']);exit;
}
if (($argv[1] ?? '')==='--render-map') {
  foreach (['OFFICE_BASE_PATH'=>'/office-test','OFFICE_NATIVE_PROJECTS_ENABLED'=>'enabled',
    'OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'=>'enabled','OFFICE_TEST_ZONE_WRITE_ENABLED'=>'enabled',
    'OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED'=>'enabled','OFFICE_CRM_SESSION_MODE'=>'enabled',
    'OFFICE_TEST_ZONE_CSRF_SECRET'=>str_repeat('s',32),'OFFICE_PUBLIC_HOST'=>'test.example',
    'OFFICE_INTAKE_PDO_DSN'=>$dsn,'OFFICE_INTAKE_DB_USER'=>getenv('OFFICE_INZET_TEST_USER') ?: '',
    'OFFICE_INTAKE_DB_PASSWORD'=>getenv('OFFICE_INZET_TEST_PASSWORD') ?: ''] as $name=>$value) putenv($name.'='.$value);
  $_SERVER=['REQUEST_METHOD'=>'GET','HTTP_HOST'=>'test.example','HTTPS'=>'on'];
  $_GET=['id'=>'17','building_id'=>'1'];
  $_COOKIE=['office_test_session'=>str_repeat('t',48)];
  require dirname(__DIR__).'/public/office-onsite-project-map.php';exit;
}
foreach (['office_building_zones','office_project_buildings','office_buildings','office_project_memberships','office_operator_accounts','office_projects'] as $table) $db->exec('DROP TABLE IF EXISTS '.$table);
foreach (\Brebo\Office\Buildings\OfficeBuildingSchema::statements() as $sql) $db->exec($sql);
$missing=OfficeBuildingZoneReadiness::inspect($db);
if (($missing['InnoDB office_building_zones'] ?? true)!==false) throw new RuntimeException('Missing zone table not detected');
// Exercise the actual fresh-install proposal, not only a parallel fixture string.
$db->exec(file_get_contents(dirname(__DIR__).'/deployment/hostinger/office-building-zone-create-review.sql'));
$db->exec('CREATE TABLE office_projects (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,active TINYINT,can_edit_crm TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED,operator_id VARCHAR(128),permission VARCHAR(16),PRIMARY KEY(project_id,operator_id)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_projects VALUES (17),(18)");
$db->exec("INSERT INTO office_operator_accounts (id,active) VALUES ('manager-test',1),('inactive-test',0),('other-test',1)");
$db->exec("INSERT INTO office_project_memberships VALUES (17,'manager-test','manage'),(18,'manager-test','read'),(17,'inactive-test','manage')");
$db->exec("INSERT INTO office_buildings (id,code,name) VALUES (1,'TEST-A','Fictief gebouw A'),(2,'TEST-B','Fictief gebouw B')");
$db->exec('INSERT INTO office_project_buildings VALUES (17,1),(18,2)');
OfficeBuildingZoneReadiness::requireReady($db);
$db->exec('DROP TABLE IF EXISTS office_operator_sessions');
$db->exec('CREATE TABLE office_operator_sessions (token_hash CHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME NULL) ENGINE=InnoDB');
$db->prepare('INSERT INTO office_operator_sessions VALUES (?,?,UTC_TIMESTAMP()+INTERVAL 1 HOUR,NULL)')->execute([hash('sha256',str_repeat('t',48)),'manager-test']);
$access=new \Brebo\Office\Project\OfficeNativeProjectAccess($db);
if (!$access->canManage('manager-test',17) || $access->canManage('manager-test',18)
  || $access->canManage('inactive-test',17) || $access->canManage('other-test',17)) throw new RuntimeException('Opaque account ID ACL failed');
$renderer=proc_open([PHP_BINARY,__FILE__,'--render-map'],[['pipe','r'],['pipe','w'],['pipe','w']],$renderPipes);
if (!is_resource($renderer)) throw new RuntimeException('Cannot start map rendering test');
fclose($renderPipes[0]);$html=stream_get_contents($renderPipes[1]);$renderError=stream_get_contents($renderPipes[2]);
fclose($renderPipes[1]);fclose($renderPipes[2]);
if (proc_close($renderer)!==0 || !str_contains($html,'<form id="zone-save"')
  || !preg_match('/id="start-lat"[^>]*value=""/', $html)
  || !preg_match('/name="form_token" value="([^"]+)"/', $html, $renderToken)) {
  throw new RuntimeException('Canonical-schema map render failed: '.$renderError.' '.substr($html,0,300));
}
OnSiteOfficeZoneFormToken::requestKey($renderToken[1],new WebsiteCrmOperatorContext('manager-test',hash('sha256',str_repeat('t',48)),false),'17','1',str_repeat('s',32),time());
if (!preg_match('/<script>([\s\S]+)<\/script>/', $html, $javascript)) throw new RuntimeException('Map script absent');
$syntax=proc_open(['node','--check'],[['pipe','r'],['pipe','w'],['pipe','w']],$syntaxPipes);
if (!is_resource($syntax)) throw new RuntimeException('Cannot check map JavaScript');
fwrite($syntaxPipes[0],$javascript[1]);fclose($syntaxPipes[0]);stream_get_contents($syntaxPipes[1]);$syntaxError=stream_get_contents($syntaxPipes[2]);
fclose($syntaxPipes[1]);fclose($syntaxPipes[2]);
if (proc_close($syntax)!==0) throw new RuntimeException('Map JavaScript syntax failed: '.$syntaxError);
$input=['building_id'=>'1','zone_name'=>'Werkzone','latitude'=>'51.5','longitude'=>'4.3','radius_metres'=>'150'];
$count=static fn():int=>(int)$db->query('SELECT COUNT(*) FROM office_building_zones')->fetchColumn();
$deny=static function(Closure $attempt,string $code) use($db,$count):void {
  $before=$count();
  try {$attempt();} catch (InvalidArgumentException|RuntimeException $e) {
    if ($e->getMessage()!==$code) throw $e;
    if ($count()!==$before || $db->inTransaction()) throw new RuntimeException('Denied request changed storage/transaction');
    return;
  }
  throw new RuntimeException('Expected rejection '.$code);
};
$key=hash('sha256','first request');
$id=$service->create($actor('manager-test'),'17',$input,$key);
if ($service->create($actor('manager-test'),'17',$input,$key)!==$id || $count()!==1) throw new RuntimeException('Retry duplicated zone');
$zones=new OfficeBuildingZoneRepository($db);
if (count($zones->forProject('17',static fn(string $id):bool=>$id==='17'))!==1) throw new RuntimeException('Building zone not visible through project');
$deny(fn()=>$service->create($actor('manager-test'),'17',array_replace($input,['zone_name'=>'Changed']),$key),'zone_request_conflict');
foreach (['inactive-test','other-test'] as $who) $deny(fn()=>$service->create($actor($who),'17',$input,$key),'unauthorized_building_zone');
$deny(fn()=>$service->create($actor('manager-test'),'18',$input,$key),'unauthorized_building_zone');
foreach (['2','3','999'] as $building) $deny(fn()=>$service->create($actor('manager-test'),'17',array_replace($input,['building_id'=>$building]),$key),'unauthorized_building_zone');
foreach (['operator_id'=>'manager-test','zone_id'=>'99','revision'=>'1','project_id'=>'17'] as $field=>$value) {
  $deny(fn()=>$service->create($actor('manager-test'),'17',$input+[$field=>$value],$key),'office_circle_unknown_fields');
}
foreach (['latitude'=>91,'longitude'=>181,'radius_metres'=>0,'zone_name'=>' '] as $field=>$value) {
  $deny(fn()=>$service->create($actor('manager-test'),'17',array_replace($input,[$field=>$value]),$key),'invalid_building_zone');
}
$db->exec("UPDATE office_operator_accounts SET active=0 WHERE id='manager-test'");
$deny(fn()=>$service->create($actor('manager-test'),'17',$input,$key),'unauthorized_building_zone');
$db->exec("UPDATE office_operator_accounts SET active=1 WHERE id='manager-test'");
$db->exec('DELETE FROM office_project_buildings WHERE project_id=17 AND building_id=1');
$deny(fn()=>$service->create($actor('manager-test'),'17',$input,$key),'unauthorized_building_zone');
$db->exec('INSERT INTO office_project_buildings VALUES(17,1)');
$secret=str_repeat('s',32);$now=1792494000;
$token=OnSiteOfficeZoneFormToken::issue($actor('manager-test'),'17','1',$secret,$now);
$post=$input+['project_id'=>'17','form_token'=>$token];
$action=new OnSiteOfficeZonePostAction($service);
$send=static fn(array $post,?WebsiteCrmOperatorContext $who=null,int $clock=1792494000)=>
  $action->handle('POST','test.example','test.example','https://test.example','application/x-www-form-urlencoded',
    http_build_query($post),$who ?? $actor('manager-test'),$secret,$clock);
$first=$send($post);$repeat=$send($post);
if ($first!==$repeat || $count()!==2) throw new RuntimeException('Form retry not idempotent');
foreach ([$actor('manager-test','b'),$actor('other-test')] as $who) $deny(fn()=>$send($post,$who),'invalid_zone_form_token');
$deny(fn()=>$send($post,null,$now+1800),'invalid_zone_form_token');
$deny(fn()=>$send(array_replace($post,['form_token'=>'bad'])),'invalid_zone_form_token');
$deny(fn()=>$send(array_replace($post,['project_id'=>'18'])),'invalid_zone_form_token');
$deny(fn()=>$send(array_replace($post,['building_id'=>'2'])),'invalid_zone_form_token');
$deny(fn()=>$send($post+['operator_id'=>'manager-test']),'invalid_zone_form_fields');
$deny(fn()=>$send(array_replace($post,['radius_metres'=>'151'])),'zone_request_conflict');
foreach ([
  ['GET','test.example','https://test.example','application/x-www-form-urlencoded','x','zone_method_not_allowed'],
  ['POST','evil.example','https://test.example','application/x-www-form-urlencoded','x','zone_origin_denied'],
  ['POST','test.example','https://evil.example','application/x-www-form-urlencoded','x','zone_origin_denied'],
  ['POST','test.example','','application/x-www-form-urlencoded','x','zone_origin_denied'],
  ['POST','test.example','https://test.example','application/json','{}','zone_media_type_unsupported'],
  ['POST','test.example','https://test.example','application/x-www-form-urlencoded',str_repeat('x',4097),'zone_payload_too_large'],
  ['POST','test.example','https://test.example','application/x-www-form-urlencoded',http_build_query($post).'&project_id=18','invalid_zone_form_fields'],
] as [$method,$host,$origin,$type,$body,$code]) {
  $deny(fn()=>$action->handle($method,$host,'test.example',$origin,$type,$body,$actor('manager-test'),$secret,$now),$code);
}
// SQL failure after authorization rolls back and leaves the request reusable.
$db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
$db->exec('ALTER TABLE office_building_zones MODIFY zone_name VARCHAR(10) NOT NULL');
$failed=false;$before=$count();$retryKey=hash('sha256','retry after rollback');
try {$service->create($actor('manager-test'),'17',array_replace($input,['zone_name'=>str_repeat('x',20)]),$retryKey);}
catch (PDOException) {$failed=true;}
if (!$failed || $count()!==$before || $db->inTransaction()) throw new RuntimeException('Rollback failed');
$db->exec('ALTER TABLE office_building_zones MODIFY zone_name VARCHAR(160) NOT NULL');
$service->create($actor('manager-test'),'17',$input,$retryKey);
$db->beginTransaction();$nested=false;
try {$service->create($actor('manager-test'),'17',$input,$key);}
catch (RuntimeException $e) {$nested=$e->getMessage()==='nested_building_zone_transaction';}
if (!$nested || !$db->inTransaction()) throw new RuntimeException('Caller transaction changed');
$db->rollBack();
// Two simultaneous submissions serialize on the building and return one ID.
$before=$count();$concurrentKey=hash('sha256','concurrent request');
$db->beginTransaction();$db->query('SELECT id FROM office_buildings WHERE id=1 FOR UPDATE')->fetchColumn();
$workers=[];
for ($i=0;$i<2;$i++) {
  $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
  if (!is_resource($process)) throw new RuntimeException('Cannot start concurrent request test');
  fwrite($pipes[0],json_encode(['input'=>$input,'key'=>$concurrentKey],JSON_THROW_ON_ERROR));fclose($pipes[0]);
  $workers[]=[$process,$pipes];
}
usleep(150000);$db->commit();$ids=[];
foreach ($workers as [$process,$pipes]) {
  $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
  fclose($pipes[1]);fclose($pipes[2]);
  if (proc_close($process)!==0) throw new RuntimeException('Concurrent request failed: '.$error);
  $ids[]=trim($output);
}
if ($ids[0]!==$ids[1] || !ctype_digit($ids[0]) || $count()!==$before+1) throw new RuntimeException('Concurrent retry duplicated zone');
// A missing unique key must fail schema readiness even when the feature flag is set.
$db->exec('ALTER TABLE office_building_zones DROP INDEX uq_office_building_zone_request');
$deny(fn()=>OfficeBuildingZoneReadiness::requireReady($db),'building_zone_schema_not_ready');
echo "Building-owned circle POST, session CSRF, concurrency, rollback and schema gates OK\n";

