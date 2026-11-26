<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/scripts/deployment/OfficeOnSiteRolloutReadiness.php';
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE office_projects (id INTEGER PRIMARY KEY, code TEXT)',
    'CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY,active INTEGER)',
    'CREATE TABLE office_project_memberships (project_id INTEGER,operator_id TEXT,permission TEXT)',
    'CREATE TABLE office_account_personnel_links (operator_id TEXT PRIMARY KEY,person_id INTEGER UNIQUE)',
    'CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY,active INTEGER)',
    'CREATE TABLE office_onsite_event_inbox (project_id INTEGER,event_kind TEXT)',
    'CREATE TABLE office_inzet_approved_hours (id INTEGER PRIMARY KEY,project_id INTEGER,person_id INTEGER,work_date TEXT,source_kind TEXT,approved_seconds INTEGER)',
    'CREATE TABLE office_inzet_actual_labour_costs (id INTEGER PRIMARY KEY,approved_hours_id INTEGER,project_id INTEGER)',
    'CREATE TABLE office_inzet_actual_labour_rates (id INTEGER PRIMARY KEY,project_id INTEGER,person_id INTEGER,valid_from TEXT,valid_until TEXT,revoked_at TEXT,hourly_rate_cents INTEGER,authorized_by TEXT)',
] as $ddl) $db->exec($ddl);
$check=static function(string $expectedFour,string $expectedCost,int $invalidMappings,int $invalidRates) use($db):void {
    $r=OfficeOnSiteRolloutReadiness::inspect($db);
    if ($r['four_eyes_readiness']!==$expectedFour || $r['actual_labour_readiness']!==$expectedCost
        || $r['reviewer_assignments_without_unique_active_mapping']!==$invalidMappings
        || $r['unposted_entries_without_unique_authorized_rate']!==$invalidRates) {
        throw new RuntimeException('OnSite readiness classification mismatch');
    }
};
$check('NO_REAL_EVENTS','NO_UNPOSTED_APPROVALS',0,0);
$db->exec("INSERT INTO office_projects VALUES (1,'BREBO-CI-TEST'),(2,'REAL-01')");
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1)");
$db->exec("INSERT INTO office_project_memberships VALUES (2,'manager','manage')");
$db->exec("INSERT INTO office_onsite_event_inbox VALUES (1,'in'),(2,'in')");
$db->exec("INSERT INTO office_inzet_people VALUES (7,1),(8,1)");
$db->exec("INSERT INTO office_inzet_approved_hours VALUES (1,2,7,'2026-11-15','onsite_review',21600)");
$check('BLOCKED','BLOCKED',1,1);
$db->exec("INSERT INTO office_account_personnel_links VALUES ('manager',8)");
$db->exec("INSERT INTO office_inzet_actual_labour_rates VALUES (1,2,7,'2026-01-01',NULL,NULL,4250,'manager')");
$check('READY_FOR_CONTROLLED_TRIAL','READY_FOR_CONTROLLED_TRIAL',0,0);
$db->exec("UPDATE office_inzet_people SET active=0 WHERE id=8");
$db->exec("INSERT INTO office_inzet_actual_labour_rates VALUES (2,2,7,'2026-01-01',NULL,NULL,4250,'manager')");
$check('BLOCKED','BLOCKED',1,1);
$db->exec("INSERT INTO office_inzet_actual_labour_costs VALUES (1,1,2)");
$check('BLOCKED','NO_UNPOSTED_APPROVALS',1,0);
$ready=OfficeOnSiteRolloutReadiness::inspect($db);
if (OfficeOnSiteRolloutReadiness::enableFourEyesForTest($ready,false)) throw new RuntimeException('Blocked mapping must not activate');
if (!OfficeOnSiteRolloutReadiness::enableFourEyesForTest($ready,true)) throw new RuntimeException('Existing four-eyes policy must not downgrade');
$db->exec("UPDATE office_inzet_people SET active=1 WHERE id=8");
$ready=OfficeOnSiteRolloutReadiness::inspect($db);
if (!OfficeOnSiteRolloutReadiness::enableFourEyesForTest($ready,false)) throw new RuntimeException('Verified mapping must activate');
$ready['onsite_real_arrivals']=0;
if (OfficeOnSiteRolloutReadiness::enableFourEyesForTest($ready,false)) throw new RuntimeException('No real events must not activate');
echo "ONSITE_READINESS_CONTRACT_OK\\n";
