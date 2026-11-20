<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCalculationSource.php';
use Brebo\Office\Buildings\OfficeBuildingCalculationSource;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "SQLite unavailable; large inventory guard skipped\n"; exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_buildings(id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_building_inventory_items(building_id INTEGER,component_code TEXT,description TEXT,unit TEXT,quantity NUMERIC,demarcation_id INTEGER,address_id INTEGER,dwelling_type_id INTEGER)');
$db->exec('INSERT INTO office_buildings(id) VALUES (1)');
$insert=$db->prepare('INSERT INTO office_building_inventory_items(building_id,component_code,description,unit,quantity) VALUES (1,?,?,?,1)');
for ($i=0;$i<501;$i++) $insert->execute([sprintf('C%04d',$i),'Component','st']);
$source=new OfficeBuildingCalculationSource($db);
try {
  $source->preview(1,'building');
  throw new LogicException('partial source unexpectedly accepted');
} catch (RuntimeException $expected) {
  if ($expected->getMessage()!=='inventory_source_too_large') throw $expected;
}
echo "Large building inventory is blocked before calculation transfer\n";
