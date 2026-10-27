<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Intake/OfficeOverviewKpis.php';
use Brebo\Office\Intake\OfficeOverviewKpis as Kpis;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_projects(id INTEGER PRIMARY KEY);CREATE TABLE office_operator_accounts(id TEXT,active INT);CREATE TABLE office_project_memberships(operator_id TEXT,project_id INT,permission TEXT);CREATE TABLE office_inzet_people(id INT,display_name TEXT);CREATE TABLE office_onsite_event_inbox(event_id TEXT,person_id INT,project_id TEXT,zone_id TEXT,event_kind TEXT,occurred_at_utc TEXT);CREATE TABLE office_onsite_hours_reviews(person_id INT,in_event_id TEXT,out_event_id TEXT,project_id TEXT,approval_status TEXT,reviewed_seconds INT,reviewer_operator_id TEXT,reviewed_at TEXT,approved_at TEXT,reason TEXT,revision INT)");
$db->exec("INSERT INTO office_projects VALUES(1),(2),(3);INSERT INTO office_operator_accounts VALUES('manager',1),('reader',1),('disabled',0);INSERT INTO office_project_memberships VALUES('manager',1,'manage'),('manager',2,'read'),('reader',1,'read'),('disabled',1,'manage');INSERT INTO office_inzet_people VALUES(7,'Test'),(8,'Other')");
$q=$db->prepare('INSERT INTO office_onsite_event_inbox VALUES(?,?,?,?,?,?)');
foreach([['in-a',7,'1','1','in','2026-10-26T07:00:00Z'],['out-a',7,'1','1','out','2026-10-26T08:00:00Z'],['in-open',8,'1','1','in','2026-10-26T09:00:00Z'],['in-other',7,'2','1','in','2026-10-26T09:00:00Z']]as$row)$q->execute($row);
$q=$db->prepare('INSERT INTO office_onsite_hours_reviews VALUES(?,?,?,?,?,?,?,?,?,?,?)');
foreach([[7,'prior-in','prior-out','1','approved',3600,'manager','2026-10-26 08:00:00','2026-10-26 08:00:00',null,1],[7,'outside-in','outside-out','2','approved',7200,'other','2026-10-26 08:00:00','2026-10-26 08:00:00',null,1],[7,'old-in','old-out','1','approved',500,'manager','2026-10-25 22:59:59','2026-10-25 22:59:59',null,1],[7,'boundary-in','boundary-out','1','approved',900,'manager','2026-10-25 23:00:00','2026-10-25 23:00:00',null,1],[7,'next-in','next-out','1','approved',700,'manager','2026-11-01 23:00:00','2026-11-01 23:00:00',null,1],[7,'reject-in','reject-out','1','rejected',0,'manager','2026-10-26 09:00:00',null,'Test',1]]as$row)$q->execute($row);
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS | '.$label.PHP_EOL;};$now=new DateTimeImmutable('2026-10-27T12:00:00Z');
$kpi=Kpis::hours($db,'manager',$now,true);
$check($kpi['pending']===1&&$kpi['open']===1,'pending complete sessions and open evidence counted separately');
$check($kpi['approved_seconds']===4500,'approved sum excludes read-only projects, prior week, next week and rejection');
$check($kpi['period']==='26-10 t/m 01-11','week is defined in Dutch local time');
foreach(['reader','disabled','unknown']as$actor){$kpi=Kpis::hours($db,$actor,$now,true);$check($kpi['pending']===null&&$kpi['open']===null&&$kpi['approved_seconds']===null,'no approval KPI access: '.$actor);}
$check(Kpis::hours($db,'manager',$now,false)['pending']===null,'disabled source is unknown, not zero');
$db->exec("INSERT INTO office_project_memberships VALUES('manager',1,'manage')");$check(Kpis::hours($db,'manager',$now,true)['approved_seconds']===4500,'duplicate grants never double count approvals');
$html=Kpis::cards($db,'manager',0,false,null,true,$now);
$check(str_contains($html,'1 uur 15 min')&&str_contains($html,'op beoordelingsdatum'),'approval duration and period definition are clear');
$check(!str_contains($html,'/inzet/importcontrole')&&str_contains($html,'Niet beschikbaar'),'private import link is hidden without the existing reviewer grant');
$check(str_contains($html,'Finance is nog niet aangesloten')&&!str_contains($html,'€ 0'),'missing financial source is not a fabricated zero');
$db->exec('DROP TABLE office_onsite_event_inbox');$kpi=Kpis::hours($db,'manager',$now,true);$check($kpi['pending']===null&&$kpi['open']===null,'failed evidence source never reports clean zero counts');
echo 'Overview KPIs passed | '.$checks.' checks'.PHP_EOL;
