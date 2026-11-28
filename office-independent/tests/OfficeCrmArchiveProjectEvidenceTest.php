<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmArchiveProjectEvidence.php';
use Brebo\Office\Crm\OfficeCrmArchiveProjectEvidence;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
'CREATE TABLE office_crm_opportunities (id INTEGER,relation_id INTEGER,stage TEXT,status TEXT)',
'CREATE TABLE office_crm_leads (relation_id INTEGER,status TEXT)',
'CREATE TABLE office_projects (id INTEGER PRIMARY KEY,status TEXT)',
'CREATE TABLE office_calc_crm_acceptance_transfers (crm_opportunity_id INTEGER,office_project_id INTEGER)',
'CREATE TABLE office_crm_direct_project_transfers (opportunity_id INTEGER,project_id INTEGER)',
'CREATE TABLE office_process_form_instances (id INTEGER PRIMARY KEY,context_type TEXT,context_id INTEGER,document_code TEXT,status TEXT)'
] as $sql) $db->exec($sql);
$r=new OfficeCrmArchiveProjectEvidence($db);
$check=static function(bool $actual,bool $expected,string $case):void {if($actual!==$expected) throw new RuntimeException($case);};
$check($r->allOrdersAndProjectsCompleted(1),false,'No orders');
$db->exec("INSERT INTO office_crm_opportunities VALUES (10,1,'won','closed')");
$check($r->allOrdersAndProjectsCompleted(1),false,'Missing project');
$db->exec("INSERT INTO office_projects VALUES (20,'execution')");
$db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (10,20)");
$check($r->allOrdersAndProjectsCompleted(1),false,'Execution');
$db->exec("UPDATE office_projects SET status='completed'");
$check($r->allOrdersAndProjectsCompleted(1),false,'Missing closure');
$db->exec("INSERT INTO office_process_form_instances VALUES (1,'project',20,'CL-600','concept')");
$check($r->allOrdersAndProjectsCompleted(1),false,'Unapproved closure');
$db->exec("UPDATE office_process_form_instances SET status='akkoord'");
$check($r->allOrdersAndProjectsCompleted(1),true,'Completed with closure');
$db->exec("INSERT INTO office_crm_direct_project_transfers VALUES (10,20)");
$check($r->allOrdersAndProjectsCompleted(1),false,'Duplicate mapping');
$db->exec("DELETE FROM office_crm_direct_project_transfers");
$db->exec("INSERT INTO office_crm_leads VALUES (1,'active')");
$check($r->noOpenCrmWork(1),false,'Active lead');
$db->exec("UPDATE office_crm_leads SET status='converted'");
$check($r->noOpenCrmWork(1),true,'No open CRM work');
$db->exec("INSERT INTO office_crm_opportunities VALUES (11,1,'proposal','open')");
$check($r->noOpenCrmWork(1),false,'Open deal');
$check($r->allOrdersAndProjectsCompleted(1),false,'Unfinished deal');
echo "CRM project evidence integration passed\n";
