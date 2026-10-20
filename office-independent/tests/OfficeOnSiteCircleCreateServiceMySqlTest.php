<?php
declare(strict_types=1);
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Inzet\OnSiteOfficeCircleCreateService;
use Brebo\Office\Inzet\OnSiteOfficeZoneFormToken;
use Brebo\Office\Inzet\OnSiteOfficeZonePostAction;
use Brebo\Office\Buildings\OfficeBuildingZoneReadiness;
use Brebo\Office\Buildings\OfficeBuildingZoneRepository;
foreach (['Intake/WebsiteCrmOperatorContext','Buildings/OfficeBuildingSchema','Buildings/OfficeBuildingZoneSchema',
  'Buildings/OfficeBuildingZoneRepository','Buildings/OfficeBuildingZoneReadiness',
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
  echo $service->create($actor('10'),'17',$payload['input'],$payload['key']);exit;
}
foreach (['office_building_zones','office_project_buildings','office_buildings','office_project_memberships','office_operator_accounts','office_projects'] as $table) $db->exec('DROP TABLE IF EXISTS '.$table);
foreach (\Brebo\Office\Buildings\OfficeBuildingSchema::statements() as $sql) $db->exec($sql);
foreach (\Brebo\Office\Buildings\OfficeBuildingZoneSchema::statements() as $sql) $db->exec($sql);
$db->exec('CREATE TABLE office_projects (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_operator_accounts (id BIGINT UNSIGNED PRIMARY KEY,active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED,operator_id BIGINT UNSIGNED,permission VARCHAR(20),PRIMARY KEY(project_id,operator_id)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_projects VALUES (17),(18)");
$db->exec("INSERT INTO office_operator_accounts VALUES (10,1),(11,0),(12,1)");
$db->exec("INSERT INTO office_project_memberships VALUES (17,10,'manage'),(18,10,'read'),(17,11,'manage')");
$db->exec("INSERT INTO office_buildings (id,building_name,active) VALUES (1,'Fictief gebouw A',1),(2,'Fictief gebouw B',1),(3,'Inactief gebouw',0)");
$db->exec('INSERT INTO office_project_buildings VALUES (17,1),(18,2),(17,3)');
OfficeBuildingZoneReadiness::requireReady($db);
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
$id=$service->create($actor('10'),'17',$input,$key);
if ($service->create($actor('10'),'17',$input,$key)!==$id || $count()!==1) throw new RuntimeException('Retry duplicated zone');
$zones=new OfficeBuildingZoneRepository($db);
if (count($zones->forProject('17',static fn(string $id):bool=>$id==='17'))!==1) throw new RuntimeException('Building zone not visible through project');
$deny(fn()=>$service->create($actor('10'),'17',array_replace($input,['zone_name'=>'Changed']),$key),'zone_request_conflict');
foreach (['11','12'] as $who) $deny(fn()=>$service->create($actor($who),'17',$input,$key),'unauthorized_building_zone');
$deny(fn()=>$service->create($actor('10'),'18',$input,$key),'unauthorized_building_zone');
foreach (['2','3','999'] as $building) $deny(fn()=>$service->create($actor('10'),'17',array_replace($input,['building_id'=>$building]),$key),'unauthorized_building_zone');
foreach (['operator_id'=>'10','zone_id'=>'99','revision'=>'1','project_id'=>'17'] as $field=>$value) {
  $deny(fn()=>$service->create($actor('10'),'17',$input+[$field=>$value],$key),'office_circle_unknown_fields');
}
foreach (['latitude'=>91,'longitude'=>181,'radius_metres'=>0,'zone_name'=>' '] as $field=>$value) {
  $deny(fn()=>$service->create($actor('10'),'17',array_replace($input,[$field=>$value]),$key),'invalid_building_zone');
}
$db->exec('UPDATE office_operator_accounts SET active=0 WHERE id=10');
$deny(fn()=>$service->create($actor('10'),'17',$input,$key),'unauthorized_building_zone');
$db->exec('UPDATE office_operator_accounts SET active=1 WHERE id=10');
$db->exec('DELETE FROM office_project_buildings WHERE project_id=17 AND building_id=1');
$deny(fn()=>$service->create($actor('10'),'17',$input,$key),'unauthorized_building_zone');
$db->exec('INSERT INTO office_project_buildings VALUES(17,1)');
$secret=str_repeat('s',32);$now=1792494000;
$token=OnSiteOfficeZoneFormToken::issue($actor('10'),'17','1',$secret,$now);
$post=$input+['project_id'=>'17','form_token'=>$token];
$action=new OnSiteOfficeZonePostAction($service);
$send=static fn(array $post,?WebsiteCrmOperatorContext $who=null,int $clock=1792494000)=>
  $action->handle('POST','test.example','test.example','https://test.example','application/x-www-form-urlencoded',
    http_build_query($post),$who ?? $actor('10'),$secret,$clock);
$first=$send($post);$repeat=$send($post);
if ($first!==$repeat || $count()!==2) throw new RuntimeException('Form retry not idempotent');
foreach ([$actor('10','b'),$actor('12')] as $who) $deny(fn()=>$send($post,$who),'invalid_zone_form_token');
$deny(fn()=>$send($post,null,$now+1800),'invalid_zone_form_token');
$deny(fn()=>$send(array_replace($post,['form_token'=>'bad'])),'invalid_zone_form_token');
$deny(fn()=>$send(array_replace($post,['project_id'=>'18'])),'invalid_zone_form_token');
$deny(fn()=>$send(array_replace($post,['building_id'=>'2'])),'invalid_zone_form_token');
$deny(fn()=>$send($post+['operator_id'=>'10']),'invalid_zone_form_fields');
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
  $deny(fn()=>$action->handle($method,$host,'test.example',$origin,$type,$body,$actor('10'),$secret,$now),$code);
}
// SQL failure after authorization rolls back and leaves the request reusable.
$db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
$db->exec('ALTER TABLE office_building_zones MODIFY zone_name VARCHAR(10) NOT NULL');
$failed=false;$before=$count();$retryKey=hash('sha256','retry after rollback');
try {$service->create($actor('10'),'17',array_replace($input,['zone_name'=>str_repeat('x',20)]),$retryKey);}
catch (PDOException) {$failed=true;}
if (!$failed || $count()!==$before || $db->inTransaction()) throw new RuntimeException('Rollback failed');
$db->exec('ALTER TABLE office_building_zones MODIFY zone_name VARCHAR(160) NOT NULL');
$service->create($actor('10'),'17',$input,$retryKey);
$db->beginTransaction();$nested=false;
try {$service->create($actor('10'),'17',$input,$key);}
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

