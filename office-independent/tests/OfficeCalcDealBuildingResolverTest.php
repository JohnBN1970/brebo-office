<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcDealBuildingResolver.php';
use Brebo\Office\Calculation\OfficeCalcDealBuildingResolver;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PDO SQLite is required.\n");
    exit(1);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_buildings (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_crm_leads (id INTEGER PRIMARY KEY, converted_opportunity_id INTEGER, building_id INTEGER)');
$db->exec('INSERT INTO office_crm_opportunities VALUES (10),(20),(30),(40)');
$db->exec('INSERT INTO office_buildings VALUES (1),(2)');
$db->exec('INSERT INTO office_crm_leads VALUES (1,10,1),(2,10,1),(3,20,1),(4,20,2),(5,30,NULL),(6,40,999)');
$db->exec('CREATE TABLE office_crm_deal_buildings (opportunity_id INTEGER PRIMARY KEY, building_id INTEGER)');
$db->exec('INSERT INTO office_crm_deal_buildings VALUES (10,1),(20,2),(30,2),(40,999)');
$resolver = new OfficeCalcDealBuildingResolver($db);
foreach ([10=>1,20=>null,30=>null,40=>null,999=>null] as $deal=>$expected) {
    if ($resolver->resolve($deal) !== $expected) {
        throw new RuntimeException('Unexpected deal building resolution for '.$deal);
    }
}
$canonical = new OfficeCalcDealBuildingResolver($db, true);
foreach ([10=>1,20=>null,30=>2,40=>null,999=>null] as $deal=>$expected) {
    if ($canonical->resolve($deal) !== $expected) {
        throw new RuntimeException('Unexpected canonical deal building for '.$deal);
    }
}
try { $resolver->resolve(0); throw new RuntimeException('Invalid ID accepted'); }
catch (InvalidArgumentException) {}
echo "OFFICE_CALC_DEAL_BUILDING_RESOLVER_PASS\n";
