<?php
declare(strict_types=1);
foreach(['OfficeCrmFunnel.php','OfficeCrmOpportunityPolicy.php','OfficeCrmOpportunitySchema.php','OfficeCrmOpportunityRepository.php','OfficeCrmFunnelViews.php','OfficeCrmCalculationContext.php'] as $f)require_once dirname(__DIR__).'/src/Crm/'.$f;
use Brebo\Office\Crm\OfficeCrmOpportunitySchema;
use Brebo\Office\Crm\OfficeCrmOpportunityRepository;
use Brebo\Office\Crm\OfficeCrmCalculationContext;
use Brebo\Office\Crm\OfficeCrmFunnelViews;
$dsn=getenv('CRM_TEST_DSN')?:'sqlite::memory:';
if($dsn!=='sqlite::memory:'&&!str_contains($dsn,'dbname=crm_completion_ci'))throw new RuntimeException('Disposable database required');
$db=new PDO($dsn,getenv('CRM_TEST_USER')?:'',getenv('CRM_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);$driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
if($driver==='sqlite')$db->exec('PRAGMA foreign_keys=ON');
foreach([
    'CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,active INTEGER NOT NULL,can_edit_crm INTEGER NOT NULL)',
    'CREATE TABLE office_operator_sessions (token_hash CHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME NULL)',
    'CREATE TABLE office_crm_relations (id BIGINT UNSIGNED PRIMARY KEY,display_name VARCHAR(255),status VARCHAR(24))',
    'CREATE TABLE office_crm_contact_people (id BIGINT UNSIGNED PRIMARY KEY,given_name VARCHAR(128),family_name VARCHAR(128),status VARCHAR(24))',
    'CREATE TABLE office_crm_relation_contacts (relation_id BIGINT UNSIGNED,contact_id BIGINT UNSIGNED)',
    'CREATE TABLE office_crm_relation_roles (relation_id BIGINT UNSIGNED,role_code VARCHAR(24),PRIMARY KEY(relation_id,role_code))',
] as $sql)$db->exec($sql);
foreach(OfficeCrmOpportunitySchema::statements($driver) as $sql)$db->exec($sql);
$db->exec("INSERT INTO office_operator_accounts VALUES ('editor',1,1),('reader',1,0),('inactive',0,1)");
$db->exec("INSERT INTO office_crm_relations VALUES (1,'Bestaande relatie','active'),(2,'Andere relatie','active'),(3,'Inactief','inactive')");
$db->exec("INSERT INTO office_crm_contact_people VALUES (1,'Contact','Een','active'),(2,'Contact','Twee','active')");
$db->exec('INSERT INTO office_crm_relation_contacts VALUES (1,1),(2,2)');
$db->exec("INSERT INTO office_crm_relation_roles VALUES (1,'supplier')");
$repo=new OfficeCrmOpportunityRepository($db);
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $f,string $why):void{try{$f();}catch(DomainException|InvalidArgumentException|UnexpectedValueException|OutOfBoundsException $e){return;}throw new RuntimeException('Expected rejection: '.$why);};
$lead=$repo->createLead('editor','Adviesvraag','Inspectie van gevel','Telefoon',str_repeat('a',32));
$assert(count($repo->listForViews('editor',true))===1&&$repo->listForViews('editor')===[],'lead in marketing only');
$assert($repo->createLead('editor','Adviesvraag','Inspectie van gevel','Telefoon',str_repeat('a',32))===$lead,'create lead retry');
$reject(fn()=>$repo->createLead('reader','Onbevoegd','','',str_repeat('b',32)),'reader create');
$reject(fn()=>$repo->listForViews('inactive'),'inactive read');
$reject(fn()=>$repo->transition($lead,'lead','prospect','editor',1,'',str_repeat('b',32)),'no qualification bypass');
$details=['relation_id'=>'1','contact_id'=>'1','title'=>'Advies gevel','description'=>'Bestaande scope','service_type'=>'advice','expected_value'=>'1250,50','expected_close_date'=>'2026-11-30'];
$reject(fn()=>$repo->update('editor',$lead,1,array_replace($details,['contact_id'=>'2']),str_repeat('b',32),true),'contact belongs to other relation');
$repo->update('editor',$lead,1,$details,str_repeat('b',32),true);
$item=$repo->detail('editor',$lead);$assert($item['stage']==='prospect'&&(int)$item['relation_id']===1,'same lead qualified');
$assert((int)$db->query('SELECT COUNT(*) FROM office_crm_opportunities')->fetchColumn()===1,'no duplicate opportunity');
$assert((int)$db->query("SELECT COUNT(*) FROM office_crm_relation_roles WHERE relation_id=1")->fetchColumn()===2,'role added without losing supplier');
$assert($repo->listForViews('editor',true)===[]&&count($repo->listForViews('editor'))===1,'one canonical pipeline');
$context=OfficeCrmCalculationContext::fromOpportunity($item);$assert($context['opportunity_id']===$lead&&!isset($context['project_id']),'pre-project Calc context');
$reject(fn()=>$repo->update('editor',$lead,1,$details,str_repeat('c',32)),'stale revision');
$activity=['activity_type'=>'opname','opname_type'=>'measurement','title'=>'Inmeting','scheduled_at'=>'2026-11-12T09:30'];
$repo->addActivity('editor',$lead,2,$activity,str_repeat('c',32));
$repo->addActivity('editor',$lead,2,$activity,str_repeat('c',32));
$item=$repo->detail('editor',$lead);$assert((int)$item['revision']===3&&$item['stage']==='prospect','opname optional no extra funnel stage');
$assert($item['next_action']==='Inmeting'&&$item['next_action_at']==='2026-11-12 09:30:00','next action single source');
$activities=array_values(array_filter($item['activities'],static fn($a)=>$a['scheduled_at']!==null));$assert(count($activities)===1,'no duplicate activity');
$repo->addActivity('editor',$lead,3,['activity_type'=>'call','title'=>'Nabellen','scheduled_at'=>'2026-11-13T11:00'],str_repeat('d',32));
$assert(count($repo->calendar('editor'))===2,'calendar includes both appointments');
$html=OfficeCrmFunnelViews::render($repo->calendar('editor'),'calendar','/office-test/crm/verkoopkansen','2026-11');
$assert(str_contains($html,'Inmeting')&&str_contains($html,'Nabellen')&&str_contains($html,'2026-11-30'),'month calendar');
$other=$repo->create(2,'Andere kans',2,'editor',[],str_repeat('e',32));
$reject(fn()=>$repo->completeActivity('editor',$other,1,(int)$activities[0]['id'],str_repeat('f',32)),'cross opportunity activity');
$repo->completeActivity('editor',$lead,4,(int)$activities[0]['id'],str_repeat('f',32));
$item=$repo->detail('editor',$lead);$assert($item['next_action']==='Nabellen','next pending action advances');
$reject(fn()=>$repo->transition($lead,'prospect','won','editor',5,'Akkoord',str_repeat('1',32)),'no shortcut');
$repo->transition($lead,'prospect','opportunity','editor',5,'',str_repeat('1',32));
$repo->transition($lead,'opportunity','proposal','editor',6,'',str_repeat('2',32));
$reject(fn()=>$repo->transition($lead,'proposal','won','editor',7,'',str_repeat('3',32)),'acceptance evidence');
$repo->transition($lead,'proposal','won','editor',7,'Opdrachtbevestiging DOC-1',str_repeat('3',32));
$assert(count($repo->listForViews('reader'))===2,'won visible to reader');
foreach(['kanban','list'] as $view){$html=OfficeCrmFunnelViews::render($repo->listForViews('reader'),$view);$assert(str_contains($html,'Advies gevel'),'closed won appears in '.$view);}
$reject(fn()=>OfficeCrmCalculationContext::fromOpportunity($repo->detail('editor',$lead)),'closed opportunity no editable Calc context');
$repo->transition($other,'prospect','lost','editor',1,'Niet gegund',str_repeat('4',32));
$repo->transition($other,'lost','prospect','editor',2,'Nieuwe aanvraag',str_repeat('5',32));
$assert($repo->detail('editor',$other)['status']==='open','lost can reopen');
$reject(fn()=>$repo->create(1,'Onbevoegd',null,'reader',[],str_repeat('6',32)),'read-only actor cannot create');
// Duplicate key with different body is rejected without another record.
$reject(fn()=>$repo->createLead('editor','Anders','','',str_repeat('a',32)),'idempotency body binding');
if($driver==='sqlite'&&getenv('CRM_HTTP_FIXTURE')){
    $q=$db->prepare('INSERT INTO office_operator_sessions VALUES (?,?,?,NULL)');foreach(['editor','reader','inactive'] as $actor)$q->execute([hash('sha256',hash('sha256',$actor)),$actor,gmdate('Y-m-d H:i:s',time()+3600)]);
    $db->exec('VACUUM INTO '.$db->quote(getenv('CRM_HTTP_FIXTURE')));
}
$db->exec('DROP TABLE office_crm_opportunity_activities');
$before=(int)$db->query('SELECT COUNT(*) FROM office_crm_opportunities')->fetchColumn();
try{$repo->create(1,'Auditopslag ontbreekt',null,'editor',[],str_repeat('7',32));throw new RuntimeException('audit failure expected');}catch(PDOException $e){}
$assert((int)$db->query('SELECT COUNT(*) FROM office_crm_opportunities')->fetchColumn()===$before,'missing history cannot create partial opportunity');
echo "CRM_COMPLETION_INTEGRATION_OK ($driver): marketing qualification, native roles, contacts, actions, calendar, revisions, history and retries\n";
