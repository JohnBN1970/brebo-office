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
$calcFlags=[
    'OFFICE_BASE_PATH'=>'/office-test',
    'OFFICE_TEST_CRM_ENABLED'=>'enabled',
    'OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED'=>'enabled',
    'OFFICE_CALC_CRM_SCHEMA_VERIFIED'=>'enabled',
];
$previousCalcFlags=[];foreach($calcFlags as $flag=>$value){$previousCalcFlags[$flag]=getenv($flag);putenv($flag.'='.$value);}
$calcProbe=[['id'=>$lead,'name'=>'Adviesvraag','stage'=>'opportunity']];
$assert(str_contains(OfficeCrmFunnelViews::render($calcProbe,'list'),'/office-test/calculatie/start?opportunity_id='.$lead),'existing guarded CRM Calc action preserved');
putenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED=disabled');
$assert(!str_contains(OfficeCrmFunnelViews::render($calcProbe,'list'),'/calculatie/start'),'Calc action hidden without schema proof');
putenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED=enabled');
$calcProbe[0]['stage']='won';
$assert(!str_contains(OfficeCrmFunnelViews::render($calcProbe,'list'),'/calculatie/start'),'Calc action hidden on closed opportunity');
foreach($previousCalcFlags as $flag=>$value)putenv($value===false?$flag:$flag.'='.$value);

$other=$repo->create(2,'Andere kans',2,'editor',[],str_repeat('e',32));
$reject(fn()=>$repo->completeActivity('editor',$other,1,(int)$activities[0]['id'],str_repeat('f',32)),'cross opportunity activity');
$repo->completeActivity('editor',$lead,4,(int)$activities[0]['id'],str_repeat('f',32));
$item=$repo->detail('editor',$lead);$assert($item['next_action']==='Nabellen','next pending action advances');
$calendar=$repo->calendar('editor');
$assert(count($calendar)===1 && $calendar[0]['next_action']==='Nabellen','completed activity absent from active calendar');
$assert(!str_contains(OfficeCrmFunnelViews::render($calendar,'calendar'), 'Inmeting'),'completed activity absent from rendered calendar');
$reject(fn()=>$repo->transition($lead,'prospect','won','editor',5,'Akkoord',str_repeat('1',32)),'no shortcut');
$repo->transition($lead,'prospect','opportunity','editor',5,'',str_repeat('1',32));
$repo->transition($lead,'opportunity','proposal','editor',6,'',str_repeat('2',32));
$reject(fn()=>$repo->transition($lead,'proposal','won','editor',7,'',str_repeat('3',32)),'acceptance evidence');
$repo->transition($lead,'proposal','won','editor',7,'Opdrachtbevestiging DOC-1',str_repeat('3',32));
$assert(count($repo->listForViews('reader'))===2,'won visible to reader');
$assert($repo->calendar('reader')===[],'won opportunity keeps historical activity but has no pending calendar work');
$assert(count($repo->detail('reader',$lead)['activities'])>=2,'won opportunity activity audit preserved');
foreach(['kanban','list'] as $view){$html=OfficeCrmFunnelViews::render($repo->listForViews('reader'),$view);$assert(str_contains($html,'Advies gevel'),'closed won appears in '.$view);}
$reject(fn()=>OfficeCrmCalculationContext::fromOpportunity($repo->detail('editor',$lead)),'closed opportunity no editable Calc context');
$repo->transition($other,'prospect','lost','editor',1,'Niet gegund',str_repeat('4',32));
$assert(count(array_filter($repo->listForViews('reader'),static fn($row)=>$row['stage']==='lost'))===1,'lost opportunity remains in views');
$assert($repo->calendar('reader')===[],'lost opportunity has no calendar work');
foreach(['kanban','list'] as $view){$html=OfficeCrmFunnelViews::render($repo->listForViews('reader'),$view);$assert(str_contains($html,'Andere kans'),'closed lost appears in '.$view);}
$repo->transition($other,'lost','prospect','editor',2,'Nieuwe aanvraag',str_repeat('5',32));
$assert($repo->detail('editor',$other)['status']==='open','lost can reopen');
$repo->transition($other,'prospect','opportunity','editor',3,'',str_repeat('8',32));
$reject(fn()=>$repo->transition($other,'opportunity','won','editor',4,'',str_repeat('9',32)),'direct order requires acceptance evidence');
$repo->transition($other,'opportunity','won','editor',4,'Directe opdracht bevestigd per e-mail REF-2026-11',str_repeat('9',32));
$direct=$repo->detail('editor',$other);
$assert($direct['stage']==='won'&&$direct['status']==='closed','direct acceptance without quote or calculation');
$assert(str_contains($direct['outcome_note'],'REF-2026-11'),'direct order evidence retained');
$assert(!isset($direct['project_id']),'direct acceptance creates no implicit project');
// Historical wins/losses must not evict active opportunities from the funnel (>500 records).
$insert=$db->prepare("INSERT INTO office_crm_opportunities (relation_id,title,stage,status,next_action_at) VALUES (1,?,?, 'closed',?)");
for($n=0;$n<620;$n++)$insert->execute(['Historical '. $n,$n%2===0?'won':'lost','2026-01-01 09:00:00']);
$rows=$repo->listForViews('reader');
$assert(count($rows)>=622,'all active and closed opportunities available without historical 500-row cutoff');
$assert(count(array_filter($rows,static fn($row)=>$row['status']==='open'))===0,'no closed opportunity misreported as active');
$assert(count(array_filter($rows,static fn($row)=>$row['status']==='closed' && in_array($row['stage'],['won','lost'],true)))===622,'historical won and lost remain visible');
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
