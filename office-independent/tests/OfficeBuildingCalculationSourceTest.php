<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCalculationSource.php';

use Brebo\Office\Buildings\OfficeBuildingCalculationSource as Source;

$assert=static function(bool $condition,string $message):void {
  if (!$condition) {fwrite(STDERR,$message."\n");exit(1);}
};
$assert(Source::SCOPES===['building','demarcation','dwelling_type','address'],'supported source scopes');
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "SQLite unavailable; calculation source integration test skipped\n";exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach ([
  'CREATE TABLE office_buildings(id INTEGER PRIMARY KEY)',
  'CREATE TABLE office_building_demarcations(id INTEGER PRIMARY KEY,building_id INTEGER)',
  'CREATE TABLE office_building_dwelling_types(id INTEGER PRIMARY KEY,building_id INTEGER)',
  'CREATE TABLE office_building_addresses(id INTEGER PRIMARY KEY,building_id INTEGER)',
  'CREATE TABLE office_building_inventory_items(building_id INTEGER,demarcation_id INTEGER, dwelling_type_id INTEGER,address_id INTEGER,component_code TEXT,description TEXT,unit TEXT,quantity NUMERIC)'
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_buildings VALUES(1),(2)");
$db->exec("INSERT INTO office_building_demarcations VALUES(10,1),(20,2)");
$db->exec("INSERT INTO office_building_dwelling_types VALUES(11,1),(21,2)");
$db->exec("INSERT INTO office_building_addresses VALUES(12,1),(22,2)");
$db->exec("INSERT INTO office_building_inventory_items VALUES
 (1,10,11,12,'K01','Kozijn','st',2),
 (1,10,11,12,'K01','Kozijn','st',3),
 (2,20,21,22,'K01','Kozijn','st',99)");
$service=new Source($db);
foreach ([['building',null],['demarcation',10],['dwelling_type',11],['address',12]] as [$scope,$id]) {
  $rows=$service->preview(1,$scope,$id);
  $assert(count($rows)===1 && (float)$rows[0]['quantity']===5.0,$scope.' quantity');
}
foreach ([['demarcation',20],['dwelling_type',21],['address',22],['unknown',null],['address',null]] as [$scope,$id]) {
  try {$service->preview(1,$scope,$id);fwrite(STDERR,'cross-building or invalid source accepted'."\n");exit(1);}
  catch (InvalidArgumentException $expected) {}
}
echo "Office calculation source checks OK\n";
