<?php
declare(strict_types=1);
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Inzet\OperationalInzetStore;
use Brebo\Office\Inzet\OfficeInzetOperationalReview;
foreach (['Intake/WebsiteCrmOperatorContext','Inzet/OperationalInzetStore','Inzet/OfficeInzetOperationalReview','Inzet/OfficeInzetOperationalReviewWorkbench','Inzet/OfficeInzetOperationalReviewConfig'] as $class) {
  require_once dirname(__DIR__,2).'/src/'.$class.'.php';
}
function operationalFixture(?PDO $office=null,?PDO $db=null): array {
  $office??=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $db??=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  foreach ([
    'office_operator_accounts'=>'id VARCHAR(128) PRIMARY KEY, active INTEGER, can_edit_crm INTEGER',
    'office_operator_sessions'=>'token_hash VARCHAR(64) PRIMARY KEY, operator_id VARCHAR(128), expires_at VARCHAR(30), revoked_at VARCHAR(30)',
    'office_projects'=>'id INTEGER PRIMARY KEY, title VARCHAR(200)',
    'office_project_memberships'=>'operator_id VARCHAR(128), project_id INTEGER, permission VARCHAR(20), PRIMARY KEY(operator_id,project_id)',
    'office_inzet_people'=>'id INTEGER PRIMARY KEY,display_name VARCHAR(200),active INTEGER',
    'office_inzet_project_team'=>'project_id INTEGER,person_id INTEGER,PRIMARY KEY(project_id,person_id)',
  ] as $table=>$columns) $office->exec('CREATE TABLE '.$table.'('.$columns.')');
  $people=['manager'=>1101,'worker'=>2101,'viewer'=>1103,'outsider'=>1102,'worker-18'=>2102,'01'=>1104,'1'=>1105,'1-other'=>1106];
  $contexts=[];$tokens=[];
  foreach ($people as $actor=>$person) {
    $actor=(string)$actor;$token=str_repeat('x',40).$actor;$hash=hash('sha256',$token);
    $office->prepare('INSERT INTO office_operator_accounts VALUES(?,1,0)')->execute([$actor]);
    $office->prepare('INSERT INTO office_operator_sessions VALUES(?,?,?,NULL)')->execute([$hash,$actor,gmdate('Y-m-d H:i:s',time()+3600)]);
    $office->prepare('INSERT INTO office_inzet_people VALUES(?,?,1)')->execute([$person,'Voorbeeld '.$actor]);
    $contexts[$actor]=new WebsiteCrmOperatorContext($actor,$hash,false);$tokens[$actor]=$token;
  }
  $office->exec("INSERT INTO office_projects VALUES(17,'Project A'),(18,'Project B')");
  $office->exec("INSERT INTO office_project_memberships VALUES('manager',17,'manage'),('manager',18,'read'),('viewer',17,'read'),('worker',17,'manage'),('outsider',18,'manage'),('1',17,'manage')");
  $office->exec('INSERT INTO office_inzet_project_team VALUES(17,2101),(17,1101),(18,2102)');
  $store=new OperationalInzetStore($db);$store->migrate();
  foreach ([['R1','17','2101',480,'worker'],['R2','17','2101',420,'worker'],['R3','18','2102',480,'worker-18'],['R4','17','1101',480,'manager']] as [$id,$project,$person,$minutes,$actor]) {
    $store->addPlanning('plan-'.$id,$project,$person,'2026-10-22',480,'manager');
    $store->submit($id,'plan-'.$id,$minutes,'Kozijnen monteren',$actor);
  }
  $review=new OfficeInzetOperationalReview($office,$db,$people);
  return compact('office','db','people','contexts','tokens','store','review');
}
function operationalRow(array $f,string $id,string $actor='manager'): array {
  $report=$f['review']->listForOperator($f['contexts'][$actor]);
  foreach ($report['rows'] as $row) if ($row['id']===$id) return $row;
  throw new RuntimeException('Fixture registration not visible');
}
function operationalAssert(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
function operationalDenied(callable $call,?string $type=null):void {
  try{$call();}catch(Throwable $e){if($type===null || $e instanceof $type)return;throw $e;}
  throw new RuntimeException('Unsafe review unexpectedly accepted');
}
