<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmDealHousingProjectTransfer.php';
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    echo "DEAL_HOUSING_TRANSFER=SKIP_NO_SQLITE\n";
    exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_deal_housing_types (opportunity_id INTEGER,building_id INTEGER,housing_type_id INTEGER,dwelling_count INTEGER)');
$db->exec('CREATE TABLE office_crm_deal_buildings (opportunity_id INTEGER,building_id INTEGER)');
$db->exec('CREATE TABLE office_housing_types (id INTEGER,building_id INTEGER)');
$db->exec('CREATE TABLE office_project_buildings (project_id INTEGER,building_id INTEGER,PRIMARY KEY(project_id,building_id))');
$db->exec('CREATE TABLE office_project_housing_types (project_id INTEGER,building_id INTEGER,housing_type_id INTEGER,dwelling_count INTEGER)');
$db->exec('INSERT INTO office_housing_types VALUES (10,5),(11,5)');
$db->exec('INSERT INTO office_crm_deal_buildings VALUES (1,5)');
$db->exec('INSERT INTO office_crm_deal_housing_types VALUES (1,5,10,8),(1,5,11,12),(1,6,12,99)');
$db->exec('INSERT INTO office_housing_types VALUES (12,6)');
$service=new \Brebo\Office\Crm\OfficeCrmDealHousingProjectTransfer($db);
try { $service->transfer(1,20); throw new RuntimeException('No transaction guard'); }
catch (DomainException) {}
$db->beginTransaction();
if ($service->transfer(1,20)!==2) throw new RuntimeException('Wrong transferred count');
$db->commit();
if ((int)$db->query('SELECT COUNT(*) FROM office_project_buildings')->fetchColumn()!==1) throw new RuntimeException('Duplicate building');
if ((int)$db->query('SELECT SUM(dwelling_count) FROM office_project_housing_types')->fetchColumn()!==20) throw new RuntimeException('Wrong quantities');
if ((int)$db->query('SELECT COUNT(*) FROM office_project_housing_types WHERE building_id=6')->fetchColumn()!==0) throw new RuntimeException('Unlinked building transferred');
$db->beginTransaction();
if ($service->transfer(1,20)!==2) throw new RuntimeException('Exact replay not accepted');
$db->exec('UPDATE office_project_housing_types SET dwelling_count=99 WHERE housing_type_id=10');
try { $service->transfer(1,20); throw new RuntimeException('Mismatched overwrite not blocked'); }
catch (DomainException) { $db->rollBack(); }
echo "DEAL_HOUSING_TRANSFER=PASS\n";
