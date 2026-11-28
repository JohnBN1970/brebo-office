<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmArchiveQualityEvidence.php';
use Brebo\Office\Crm\OfficeCrmArchiveQualityEvidence;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
'CREATE TABLE office_crm_opportunities (id INTEGER,relation_id INTEGER,stage TEXT,status TEXT)',
'CREATE TABLE office_crm_opportunity_activities (opportunity_id INTEGER,completed_at TEXT)',
'CREATE TABLE office_projects (id INTEGER PRIMARY KEY)',
'CREATE TABLE office_calc_crm_acceptance_transfers (crm_opportunity_id INTEGER,office_project_id INTEGER)',
'CREATE TABLE office_crm_direct_project_transfers (opportunity_id INTEGER,project_id INTEGER)',
'CREATE TABLE office_quality_checks (project_id INTEGER,status TEXT)',
'CREATE TABLE office_project_start_dossiers (id INTEGER,project_id INTEGER,critical_open_points INTEGER)',
'CREATE TABLE office_project_start_questions (dossier_id INTEGER,is_critical INTEGER,decision_text TEXT)',
'CREATE TABLE office_process_form_instances (document_code TEXT,context_type TEXT,context_id INTEGER,status TEXT)'
] as $sql) $db->exec($sql);
$r=new OfficeCrmArchiveQualityEvidence($db);
$check=static function(bool $actual,bool $expected,string $case):void {if($actual!==$expected) throw new RuntimeException($case);};
$check($r->knownRestpointsAndCrmActivitiesClosed(1),false,'No won deal');
$db->exec("INSERT INTO office_crm_opportunities VALUES (10,1,'won','closed')");
$db->exec("INSERT INTO office_projects VALUES (20)");
$db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (10,20)");
$db->exec("INSERT INTO office_project_start_dossiers VALUES (30,20,0)");
$check($r->knownRestpointsAndCrmActivitiesClosed(1),true,'Known sources clear');
foreach ([
["INSERT INTO office_crm_opportunity_activities VALUES (10,NULL)","DELETE FROM office_crm_opportunity_activities"],
["INSERT INTO office_quality_checks VALUES (20,'open')","DELETE FROM office_quality_checks"],
["UPDATE office_project_start_dossiers SET critical_open_points=1","UPDATE office_project_start_dossiers SET critical_open_points=0"],
["INSERT INTO office_project_start_questions VALUES (30,1,'')","DELETE FROM office_project_start_questions"]
] as [$add,$clear]) {
    $db->exec($add);
    $check($r->knownRestpointsAndCrmActivitiesClosed(1),false,$add);
    $db->exec($clear);
    $check($r->knownRestpointsAndCrmActivitiesClosed(1),true,$clear);
}
$db->exec("INSERT INTO office_process_form_instances VALUES ('RG-520','project',20,'concept')");
$check($r->noUnapprovedServiceForms(1),false,'Pending service form');
$db->exec("UPDATE office_process_form_instances SET status='akkoord'");
$check($r->noUnapprovedServiceForms(1),true,'No pending service form; not proof of resolved complaints');
echo "Quality evidence integration passed\n";
