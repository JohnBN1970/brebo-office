<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmFunnel.php';
require_once __DIR__.'/../src/Crm/OfficeCrmOpportunityRepository.php';
use Brebo\Office\Crm\OfficeCrmOpportunityRepository;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_crm_relations(id INTEGER PRIMARY KEY,relation_kind TEXT,status TEXT);
CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,relation_id INTEGER,stage TEXT,status TEXT,revision INTEGER);
CREATE TABLE office_crm_relation_roles(relation_id INTEGER,role_code TEXT,PRIMARY KEY(relation_id,role_code));
CREATE TABLE office_crm_award_events(opportunity_id INTEGER PRIMARY KEY,relation_id INTEGER,relation_kind TEXT,awarded_at TEXT,actor_id INTEGER,source TEXT);
CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER,can_edit_crm INTEGER);
INSERT INTO office_operator_accounts VALUES(7,1,1),(8,1,0);
INSERT INTO office_crm_relations VALUES(1,'person','active'),(2,'person','archived');
INSERT INTO office_crm_opportunities VALUES(1,1,'proposal','open',1),(2,2,'proposal','open',1)");
putenv('OFFICE_CRM_AWARD_HISTORY_SCHEMA_VERIFIED=enabled');
$r=new OfficeCrmOpportunityRepository($db);
foreach([[1,8],[2,7]] as [$id,$actor]){
    $rejected=false;
    try{$r->transition($id,'proposal','won',$actor);}catch(Throwable $e){$rejected=true;}
    if(!$rejected || $db->inTransaction())throw new RuntimeException('Expected atomic rejection');
}
if((int)$db->query("SELECT COUNT(*) FROM office_crm_opportunities WHERE stage='proposal' AND revision=1")->fetchColumn()!==2)throw new RuntimeException('Rejected transition changed deal');
if(!$r->transition(1,'proposal','won',7))throw new RuntimeException('Award failed');
if($r->transition(1,'proposal','won',7))throw new RuntimeException('Duplicate transition');
if((int)$db->query('SELECT COUNT(*) FROM office_crm_award_events')->fetchColumn()!==1)throw new RuntimeException('Award count');
if((int)$db->query("SELECT COUNT(*) FROM office_crm_relation_roles WHERE role_code='customer'")->fetchColumn()!==1)throw new RuntimeException('Customer role missing');
putenv('OFFICE_CRM_AWARD_HISTORY_SCHEMA_VERIFIED');
echo "CRM award transition regression passed\n";
