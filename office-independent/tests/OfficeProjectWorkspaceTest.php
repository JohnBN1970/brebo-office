<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Project/Workspace/Schema.php';
require_once __DIR__.'/../src/Project/Workspace/View.php';
require_once __DIR__.'/../src/Project/Workspace/TaskViews.php';
use Brebo\Office\Project\Workspace\{Schema,Controls,Sources,View,TaskViews};
function ok(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function rejected(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException|DomainException|PDOException){return;}throw new RuntimeException($message);}
$dsn=getenv('WORKSPACE_TEST_DSN')?:'sqlite::memory:';
if(!in_array($dsn,['sqlite::memory:','mysql:host=127.0.0.1;dbname=office_workspace_test;charset=utf8mb4'],true))throw new RuntimeException('Only disposable fixture allowed');
$db=new PDO($dsn,getenv('WORKSPACE_TEST_USER')?:null,getenv('WORKSPACE_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);$sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
foreach([
 'office_projects(id INTEGER PRIMARY KEY,code VARCHAR(64),title VARCHAR(200),status VARCHAR(32))',
 'office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER)',
 'office_project_memberships(project_id INTEGER,operator_id INTEGER,permission VARCHAR(32))',
 'office_module_permissions(operator_id INTEGER,module VARCHAR(32),permission VARCHAR(32))',
 'office_inzet_people(id INTEGER PRIMARY KEY,display_name VARCHAR(100),active INTEGER)',
 'office_inzet_project_team(project_id INTEGER,person_id INTEGER,role_label VARCHAR(100))',
 'office_project_dwelling_targets(project_id INTEGER PRIMARY KEY,expected_dwelling_count INTEGER)',
 'office_inzet_shifts(id INTEGER PRIMARY KEY,project_id INTEGER,person_id INTEGER,starts_at VARCHAR(32),ends_at VARCHAR(32),status VARCHAR(32))',
 'office_onsite_hours_reviews(id INTEGER PRIMARY KEY,person_id INTEGER,project_id VARCHAR(128),in_event_id VARCHAR(128),approval_status VARCHAR(32),reviewed_seconds INTEGER)',
 'office_onsite_event_inbox(event_id VARCHAR(128),person_id INTEGER,project_id VARCHAR(128),occurred_at_utc VARCHAR(32))',
] as $ddl)$db->exec('CREATE TABLE '.$ddl);
foreach(Schema::statements($sqlite) as $ddl)$db->exec($ddl);
$db->exec("INSERT INTO office_projects VALUES(1,'TEST-1','Project <test>','active'),(2,'TEST-2','Private','active');INSERT INTO office_operator_accounts VALUES(1,1),(2,1),(3,0);INSERT INTO office_project_memberships VALUES(1,1,'manage'),(1,2,'read'),(1,3,'manage');INSERT INTO office_inzet_people VALUES(10,'Team member',1),(11,'Inactive',0),(20,'Other project',1);INSERT INTO office_inzet_project_team VALUES(1,10,'Uitvoerder'),(1,11,'Oud'),(2,20,'Ander');INSERT INTO office_project_dwelling_targets VALUES(1,232)");
// SQLite exec and MySQL exec support the fixture multi-statements; application statements remain prepared.
$c=new Controls($db,'1');$today='2026-11-08';$key=static fn()=>bin2hex(random_bytes(16));
$input=static fn(array $extra=[])=>array_replace(['title'=>'Task <script>alert(1)</script>','description'=>'Scope','starts_on'=>'2026-11-01','due_date'=>'2026-11-06','status'=>'planned','assignee_person_id'=>10,'estimated_minutes'=>480,'progress_percent'=>0,'reason'=>'','predecessors'=>[],'command_key'=>bin2hex(random_bytes(16))],$extra);
rejected(fn()=>(new Controls($db,'2'))->taskSave(1,0,0,$input(),$today),'Read only wrote');
rejected(fn()=>(new Controls($db,'3'))->tasks(1),'Inactive operator accessed');
rejected(fn()=>$c->tasks(2),'Cross-project access');
foreach([11,20,999] as $person)rejected(fn()=>$c->taskSave(1,0,0,$input(['assignee_person_id'=>$person]),$today),'Invalid delegate accepted');
$a=$c->taskSave(1,0,0,$input(),$today);$other=$c->taskSave(1,0,0,$input(['title'=>'Dependency','starts_on'=>'2026-11-07','due_date'=>'2026-11-09','predecessors'=>[$a]]),$today);
rejected(fn()=>$c->taskSave(1,$other,1,$input(['starts_on'=>'2026-11-07','due_date'=>'2026-11-09','predecessors'=>[$a],'status'=>'active']),$today),'Started before predecessor complete');
rejected(fn()=>$c->taskSave(1,$a,1,$input(['predecessors'=>[$other]]),$today),'Cycle accepted');
rejected(fn()=>$c->taskSave(1,$a,1,$input(['assignee_person_id'=>0]),$today),'Delegation without reason');
$c->taskSave(1,$a,1,$input(['status'=>'done']),$today);
rejected(fn()=>$c->taskSave(1,$a,1,$input(),$today),'Stale revision accepted');
$c->taskSave(1,$other,1,$input(['starts_on'=>'2026-11-07','due_date'=>'2026-11-10','status'=>'active','predecessors'=>[$a],'progress_percent'=>25]),$today);
rejected(fn()=>$c->taskSave(1,$a,2,$input(),$today),'Reopened predecessor of active task');
$c->taskSave(1,$other,2,$input(['starts_on'=>'2026-11-07','due_date'=>'2026-11-10','status'=>'blocked','predecessors'=>[$a],'reason'=>'Delivery missing','assignee_person_id'=>0]),$today);
$rows=$c->tasks(1);$by=[];foreach($rows as $t)$by[(int)$t['id']]=$t;ok($by[$other]['baseline_end']==='2026-11-09','Baseline changed');ok((int)$by[$other]['revision']===3,'CAS revision wrong');
ok(TaskViews::signal($by[$other],$today)[0]==='red','Blocked must be red');ok(TaskViews::signal($by[$a],$today)[0]==='green','Done must be green');
$future=$c->taskSave(1,0,0,$input(['starts_on'=>'2026-11-20','due_date'=>'2026-12-03','title'=>'Future task']),$today);
$replay=$input(['title'=>'Replay']);$c->taskSave(1,0,0,$replay,$today);$auditBefore=(int)$db->query('SELECT COUNT(*) FROM office_project_control_audit')->fetchColumn();rejected(fn()=>$c->taskSave(1,0,0,$replay,$today),'Duplicate command accepted');ok((int)$db->query('SELECT COUNT(*) FROM office_project_control_audit')->fetchColumn()===$auditBefore,'Rejected command changed audit');
$c->save(1,0,['planned_start'=>'2026-11-01','planned_end'=>'2026-12-31','scope_note'=>'Scope'], $today);
rejected(fn()=>$c->save(1,0,[], $today),'Stale settings accepted');
$c->measure(1,0,['measured_date'=>$today,'basis'=>'dwellings','completed_units'=>116,'evidence_reference'=>'Inspection record','command_key'=>$key()],$today);
ok((int)$c->progress(1)[0]['percent_units']===5000,'Wrong progress');
rejected(fn()=>$c->measure(1,0,['measured_date'=>$today,'basis'=>'percent','percent'=>'75','evidence_reference'=>'Record','command_key'=>$key()],$today),'Concurrent measurement lost');
rejected(fn()=>$c->forecastSave(1,0,['as_of'=>$today,'direct'=>'100','labor'=>'100','overhead'=>'0','evidence_reference'=>'Budget'],$today),'Project manage bypassed finance ACL');
$db->exec("INSERT INTO office_inzet_shifts VALUES(1,1,10,'2026-11-01 07:00:00','2026-11-01 15:00:00','planned'),(2,1,10,'2026-11-02 07:00:00','2026-11-02 15:00:00','cancelled');INSERT INTO office_onsite_event_inbox VALUES('e1',10,'1','2026-11-01 07:00:00'),('e2',10,'1','2026-11-02 07:00:00'),('e3',20,'2','2026-11-01 07:00:00');INSERT INTO office_onsite_hours_reviews VALUES(1,10,'1','e1','approved',21600),(2,10,'1','e2','pending',28800),(3,20,'2','e3','approved',28800)");
$r=(new Sources($db,'1'))->read(1,$today);ok($r['planned_seconds']===28800&&$r['approved_seconds']===21600,'Unapproved/cancelled/cross-project hours counted');ok($r['finance']===null,'Finance leaked');ok($r['clients']===null,'Missing source fabricated');
foreach(['gantt','kanban','kalender','lijst'] as $view){$html=View::styles().TaskViews::render($r,'csrf',['view'=>$view,'month'=>'2026-11']);ok(!str_contains($html,'<script>alert(1)</script>'),'XSS');ok(str_contains($html,'taak-'.$a),'View lost shared task');if(getenv('WORKSPACE_PREVIEW_DIR'))file_put_contents(getenv('WORKSPACE_PREVIEW_DIR').'/'.$view.'.html','<!doctype html><html lang="nl"><meta charset="utf-8"><title>Projecttaken</title><style>body{background:#f4f6f9;padding:25px;max-width:1280px;margin:auto}details{display:none}</style>'.$html.'</html>');}
$filtered=TaskViews::render($r,'csrf',['view'=>'lijst','status'=>'blocked']);ok(str_contains($filtered,'taak-'.$other)&&!str_contains($filtered,'id="taak-'.$a.'"'),'Filter did not persist');
$empty=$r;$empty['tasks']=[];ok(str_contains(TaskViews::signals($empty),'onbekend'),'Empty got green');ok(str_contains(TaskViews::labor($r),'6,00 uur'),'Labor missing approved hours');
$overview=View::overview($r);ok(str_contains($overview,'50,0%'),'Overview lost measurement');ok(!str_contains($overview,'4.850.000'),'Demo amount leaked');
echo 'PROJECT_WORKSPACE_'.strtoupper($db->getAttribute(PDO::ATTR_DRIVER_NAME))."_TESTS_OK\n";
