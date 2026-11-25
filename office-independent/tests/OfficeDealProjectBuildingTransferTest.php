<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Buildings/OfficeDealProjectBuildingTransfer.php';
use Brebo\Office\Buildings\OfficeDealProjectBuildingTransfer;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_projects(id INTEGER PRIMARY KEY);
CREATE TABLE office_buildings(id INTEGER PRIMARY KEY,active INTEGER);
CREATE TABLE office_crm_deal_buildings(opportunity_id INTEGER,building_id INTEGER,PRIMARY KEY(opportunity_id,building_id));
CREATE TABLE office_project_buildings(project_id INTEGER,building_id INTEGER,PRIMARY KEY(project_id,building_id));
INSERT INTO office_projects VALUES(1),(2),(3);
INSERT INTO office_buildings VALUES(10,1),(20,1),(30,1),(40,0);
INSERT INTO office_crm_deal_buildings VALUES(1,10),(1,20),(1,30),(2,10),(2,40);
INSERT INTO office_project_buildings VALUES(3,20);');
$s=new OfficeDealProjectBuildingTransfer($db);
$db->beginTransaction();
$s->transfer(1,1);
$s->transfer(1,1);
$db->commit();
if ((int)$db->query('SELECT COUNT(*) FROM office_project_buildings WHERE project_id=1')->fetchColumn()!==3) throw new RuntimeException('Three-building transfer failed');
foreach ([[2,2],[2,3]] as [$deal,$project]) {
    $db->beginTransaction();
    try {$s->transfer($deal,$project);throw new RuntimeException('Expected rejection');}
    catch (DomainException $e) {$db->rollBack();}
}
if ((int)$db->query('SELECT COUNT(*) FROM office_project_buildings WHERE project_id=2')->fetchColumn()!==0) throw new RuntimeException('Rollback failed');
if ((int)$db->query('SELECT COUNT(*) FROM office_project_buildings WHERE project_id=3')->fetchColumn()!==1) throw new RuntimeException('Existing link changed');
echo "OfficeDealProjectBuildingTransferTest OK\n";
