<?php
declare(strict_types=1);
// Disposable SQLite fixture for the complete existing-week -> action -> week journey.
// MySQL locking is exercised separately on both CI database engines.
use Brebo\Office\Inzet\OfficeInzetWeekShiftReadRepository;
use Brebo\Office\Inzet\OfficeInzetPlanningWorkbench;
use Brebo\Office\Inzet\OfficeInzetShiftCancellation;
use Brebo\Office\Inzet\OfficeInzetShiftRescheduler;
foreach (['OfficeInzetWeekShiftReadRepository','OfficeInzetPlanningWorkbench','OfficeInzetShiftCancellation','OfficeInzetShiftRescheduler'] as $class) require_once dirname(__DIR__).'/src/Inzet/'.$class.'.php';
class LifecycleFixturePdo extends PDO {
  public function prepare(string $query, array $options=[]): PDOStatement|false {
    return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);
  }
}
function lifecycleCheck(bool $ok,string $why):void { if(!$ok)throw new RuntimeException($why); }
function lifecycleDeny(callable $call):void {
  try{$call();}catch(RuntimeException|InvalidArgumentException){return;}
  throw new RuntimeException('Unauthorized or stale lifecycle action accepted');
}
$db=new LifecycleFixturePdo('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts(id TEXT PRIMARY KEY,active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships(operator_id TEXT,project_id INTEGER,permission TEXT)');
$db->exec('CREATE TABLE office_inzet_people(id INTEGER PRIMARY KEY,active INTEGER,display_name TEXT,personnel_number TEXT)');
$db->exec('CREATE TABLE office_inzet_project_team(project_id INTEGER,person_id INTEGER)');
$db->exec('CREATE TABLE office_inzet_shifts(id INTEGER PRIMARY KEY,project_id INTEGER,person_id INTEGER,starts_at TEXT,ends_at TEXT,status TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES('manager',1),('viewer',1),('inactive',0)");
$db->exec("INSERT INTO office_project_memberships VALUES('manager',17,'manage'),('viewer',17,'read'),('inactive',17,'manage')");
$db->exec("INSERT INTO office_inzet_people VALUES(101,1,'Example worker','EMP-101')");
$db->exec('INSERT INTO office_inzet_project_team VALUES(17,101)');
$db->exec("INSERT INTO office_inzet_shifts VALUES(31,17,101,'2026-10-19 08:00:00','2026-10-19 12:00:00','planned'),(32,17,101,'2026-10-19 14:00:00','2026-10-19 18:00:00','confirmed')");
$reader=new OfficeInzetWeekShiftReadRepository($db);
$rows=$reader->listWeek('manager',[17],2026,43);
$csrf=str_repeat('a',64);
$html=OfficeInzetPlanningWorkbench::renderWeek($rows,[17],2026,43,[],[17],$csrf,true,false);
lifecycleCheck(str_contains($html,'name="shift_id" value="31"') && str_contains($html,'Tijden wijzigen'),'Stable database IDs reach enabled edit form');
lifecycleCheck(!str_contains($html,'>Annuleren<'),'Reschedule flag does not implicitly enable cancellation');
$readonly=OfficeInzetPlanningWorkbench::renderWeek($rows,[17],2026,43);
lifecycleCheck(!str_contains($readonly,'method="post"'),'Readers have no mutation forms');
$change=new OfficeInzetShiftRescheduler($db);$cancel=new OfficeInzetShiftCancellation($db);
foreach(['viewer','inactive','unknown'] as $actor){
  lifecycleDeny(fn()=>$change->rescheduleForOperator($actor,31,'2026-10-19 08:00:00','2026-10-19 12:00:00','2026-10-19 09:00:00','2026-10-19 13:00:00'));
  lifecycleDeny(fn()=>$cancel->cancelForOperator($actor,31));
}
lifecycleDeny(fn()=>$change->rescheduleForOperator('manager',31,'2026-10-19 08:00:00','2026-10-19 12:00:00','2026-10-19 11:00:00','2026-10-19 15:00:00'));
$change->rescheduleForOperator('manager',31,'2026-10-19 08:00:00','2026-10-19 12:00:00','2026-10-19 09:00:00','2026-10-19 13:00:00');
lifecycleDeny(fn()=>$change->rescheduleForOperator('manager',31,'2026-10-19 08:00:00','2026-10-19 12:00:00','2026-10-19 10:00:00','2026-10-19 13:00:00'));
$html=OfficeInzetPlanningWorkbench::renderWeek($reader->listWeek('manager',[17],2026,43),[17],2026,43,[],[17],$csrf,false,true);
lifecycleCheck(str_contains($html,'11:00') && str_contains($html,'>Annuleren<'),'Changed UTC time renders in Brussels with cancel control');
lifecycleCheck($cancel->cancelForOperator('manager',31) && !$cancel->cancelForOperator('manager',31),'Cancellation is idempotent');
$html=OfficeInzetPlanningWorkbench::renderWeek($reader->listWeek('manager',[17],2026,43),[17],2026,43,[],[17],$csrf,true,true);
lifecycleCheck(!str_contains($html,'name="shift_id" value="31"'),'Cancelled shift cannot be edited again');
lifecycleCheck((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn()===2,'Shift history retained');
lifecycleCheck($reader->listWeek('unknown',[17],2026,43)===[],'Unknown actor cannot read week');
echo "Office shift lifecycle: retained IDs, independent flags, ACL, overlap, revisions, readback and cancellation OK\n";
