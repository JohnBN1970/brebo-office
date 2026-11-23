<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcDealHousingReader.php';
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) { echo "CALC_DEAL_HOUSING_READER=SKIP_NO_SQLITE\n"; exit(0); }
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_calc_crm_drafts (id INTEGER,opportunity_id INTEGER,project_id INTEGER)');
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER,status TEXT)');
$db->exec('CREATE TABLE office_crm_deal_housing_types (opportunity_id INTEGER,building_id INTEGER,housing_type_id INTEGER,dwelling_count INTEGER,revision INTEGER)');
$db->exec('CREATE TABLE office_crm_deal_buildings (opportunity_id INTEGER,building_id INTEGER)');
$db->exec('CREATE TABLE office_housing_types (id INTEGER,building_id INTEGER,code TEXT,name TEXT)');
$db->exec('CREATE TABLE office_buildings (id INTEGER,building_name TEXT)');
$db->exec("INSERT INTO office_calc_crm_drafts VALUES (1,7,NULL),(2,7,99),(3,8,NULL)");
$db->exec("INSERT INTO office_crm_opportunities VALUES (7,'open'),(8,'closed')");
$db->exec("INSERT INTO office_buildings VALUES (5,'A'),(6,'B')");
$db->exec("INSERT INTO office_housing_types VALUES (10,5,'A','Type A'),(11,6,'B','Type B')");
$db->exec("INSERT INTO office_crm_deal_buildings VALUES (7,5),(8,6)");
$db->exec("INSERT INTO office_crm_deal_housing_types VALUES (7,5,10,8,1),(7,6,11,99,1),(8,6,11,2,1)");
$reader=new \Brebo\Office\Calculation\OfficeCalcDealHousingReader($db);
$rows=$reader->forDraft(1);
if (count($rows)!==1 || (int)$rows[0]['dwelling_count']!==8 || (int)$rows[0]['building_id']!==5) throw new RuntimeException('Unlinked building leaked into Calc');
if ($reader->forDraft(2)!==[] || $reader->forDraft(3)!==[] || $reader->forDraft(0)!==[]) throw new RuntimeException('Closed/project draft leaked into Calc');
echo "CALC_DEAL_HOUSING_READER=PASS\n";
