<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmDealHousingWriter.php';
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) { echo "DEAL_HOUSING_WRITER=SKIP_NO_SQLITE\n"; exit(0); }
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY,stage TEXT,status TEXT)');
$db->exec('CREATE TABLE office_crm_deal_buildings (opportunity_id INTEGER,building_id INTEGER)');
$db->exec('CREATE TABLE office_housing_types (id INTEGER PRIMARY KEY,building_id INTEGER)');
$db->exec('CREATE TABLE office_crm_deal_housing_types (opportunity_id INTEGER,building_id INTEGER,housing_type_id INTEGER,dwelling_count INTEGER,revision INTEGER,updated_by INTEGER,PRIMARY KEY(opportunity_id,building_id,housing_type_id))');
$db->exec('CREATE TABLE office_crm_deal_housing_events (opportunity_id INTEGER,building_id INTEGER,housing_type_id INTEGER,old_count INTEGER,new_count INTEGER,revision INTEGER,changed_by INTEGER)');
$db->exec("INSERT INTO office_crm_opportunities VALUES (1,'proposal','open'),(2,'won','closed')");
$db->exec('INSERT INTO office_housing_types VALUES (10,5)');
$db->exec('INSERT INTO office_crm_deal_buildings VALUES (1,5)');
$writer=new \Brebo\Office\Crm\OfficeCrmDealHousingWriter($db);
if ($writer->save(1,5,10,8,3,0)!==1) throw new RuntimeException('First revision');
if ($writer->save(1,5,10,8,3,1)!==1) throw new RuntimeException('No-op revision');
if ($writer->save(1,5,10,12,3,1)!==2) throw new RuntimeException('Update revision');
foreach ([[1,5,10,13,3,1],[2,5,10,8,3,0],[1,6,10,8,3,0]] as $args) {
    try { $writer->save(...$args); throw new RuntimeException('Expected rejection'); }
    catch (DomainException) {}
}
if ((int)$db->query('SELECT COUNT(*) FROM office_crm_deal_housing_events')->fetchColumn()!==2) throw new RuntimeException('Audit count');
if ((int)$db->query('SELECT dwelling_count FROM office_crm_deal_housing_types')->fetchColumn()!==12) throw new RuntimeException('Stored quantity');
echo "DEAL_HOUSING_WRITER=PASS\n";
