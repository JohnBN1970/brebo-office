<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingQuantityReport.php';
use Brebo\Office\Buildings\OfficeBuildingQuantityReport as Report;

$assert=static function(bool $ok,string $message):void {
  if (!$ok) {fwrite(STDERR,$message."\n");exit(1);}
};
$assert(count(Report::LEVELS)===6,'six report levels');
$csv=Report::csv([[
  'group_label'=>'Voorgevel','component_code'=>'K01',
  'description'=>'=HYPERLINK("https://example.invalid")',
  'quantity'=>'2.500','unit'=>'st','source_lines'=>1
]]);
$assert(str_starts_with($csv,"\xEF\xBB\xBF"),'UTF8 BOM');
$assert(str_contains($csv,"'=HYPERLINK"),'CSV formula escaped');
$assert(str_contains($csv,'Voorgevel'),'CSV contains group');
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "SQLite unavailable; quantity report integration skipped\n";exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach ([
  'CREATE TABLE office_building_inventory_items(building_id INTEGER,demarcation_id INTEGER,dwelling_type_id INTEGER,address_id INTEGER,component_code TEXT,description TEXT,unit TEXT,quantity NUMERIC)',
  'CREATE TABLE office_building_demarcations(id INTEGER,building_id INTEGER,name TEXT)',
  'CREATE TABLE office_building_dwelling_types(id INTEGER,building_id INTEGER,type_code TEXT)',
  'CREATE TABLE office_building_addresses(id INTEGER,building_id INTEGER,street TEXT,house_number INTEGER,house_letter TEXT,house_addition TEXT,primary_floor INTEGER)'
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_building_demarcations VALUES(1,1,'Voorgevel'),(2,2,'Ander gebouw')");
$db->exec("INSERT INTO office_building_inventory_items VALUES(1,1,NULL,NULL,'K01','Kozijn','st',2),(1,1,NULL,NULL,'K01','Kozijn','st',3),(2,2,NULL,NULL,'K01','Kozijn','st',99)");
$report=new Report($db);
$rows=$report->rows(1,'location');
$assert(count($rows)===1 && (float)$rows[0]['quantity']===5.0,'building isolation and aggregation');
try {$report->rows(1,'unknown');exit(1);} catch (InvalidArgumentException $expected) {}
echo "Office quantity report checks OK\n";
