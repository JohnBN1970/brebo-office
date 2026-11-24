<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Project/OfficeBuildingElementAccess.php';
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_building_element_grants(operator_id INTEGER,building_id INTEGER,opportunity_id INTEGER);
CREATE TABLE office_operator_accounts(id INTEGER,active INTEGER,can_edit_crm INTEGER);
CREATE TABLE office_crm_deal_buildings(opportunity_id INTEGER,building_id INTEGER);
CREATE TABLE office_crm_opportunities(id INTEGER,status TEXT,stage TEXT);
INSERT INTO office_operator_accounts VALUES(1,1,1),(2,1,1),(3,0,1),(4,1,0);
INSERT INTO office_crm_deal_buildings VALUES(10,100),(20,200);
INSERT INTO office_crm_opportunities VALUES(10,'open','proposal'),(20,'open','proposal');
INSERT INTO office_building_element_grants VALUES(1,100,10),(3,100,10),(4,100,10);");
putenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED=enabled');
putenv('OFFICE_BUILDING_ELEMENT_GRANTS_SCHEMA_VERIFIED=enabled');
$a=new \Brebo\Office\Project\OfficeBuildingElementAccess($db);
foreach([[1,100,10,true],[2,100,10,false],[3,100,10,false],[4,100,10,false],[1,200,10,false],[1,100,20,false]] as [$actor,$building,$deal,$expected]){
 if($a->canManageBeforeProject($actor,$building,$deal)!==$expected)throw new RuntimeException('Unexpected access result');
}
$db->exec("UPDATE office_crm_opportunities SET status='closed' WHERE id=10");
if($a->canManageBeforeProject(1,100,10))throw new RuntimeException('Closed deal allowed');
putenv('OFFICE_BUILDING_ELEMENT_GRANTS_SCHEMA_VERIFIED');
if($a->canManageBeforeProject(1,100,10))throw new RuntimeException('Disabled schema allowed');
echo "BUILDING_ELEMENT_ACCESS=PASS\n";
