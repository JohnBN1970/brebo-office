<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeCalcHousingQuantityReader.php';

use Brebo\Office\Project\OfficeCalcHousingQuantityReader;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_project_buildings (project_id INTEGER, building_id INTEGER)');
$db->exec('CREATE TABLE office_project_housing_types (project_id INTEGER, building_id INTEGER, housing_type_id INTEGER, dwelling_count INTEGER)');
$db->exec('CREATE TABLE office_housing_types (id INTEGER, building_id INTEGER, code TEXT, name TEXT)');
$db->exec('CREATE TABLE office_project_calculation_scopes (id INTEGER, project_id INTEGER, building_id INTEGER, housing_type_id INTEGER, scope_kind TEXT, calc_calculation_id INTEGER)');
$db->exec('CREATE TABLE office_scope_quantity_lines (id INTEGER, scope_id INTEGER, line_code TEXT, description TEXT, unit TEXT, quantity_per_dwelling TEXT)');
$db->exec('INSERT INTO office_project_buildings VALUES (1,10),(2,20)');
$db->exec('INSERT INTO office_project_housing_types VALUES (1,10,100,8),(2,20,200,99)');
$db->exec("INSERT INTO office_housing_types VALUES (100,10,'NL-SFB','Appartement'),(200,20,'STABU','Andere')");
$db->exec("INSERT INTO office_project_calculation_scopes VALUES (1,1,10,100,'housing_type',NULL),(2,2,20,200,'housing_type',5),(3,1,20,200,'housing_type',NULL)");
$db->exec("INSERT INTO office_scope_quantity_lines VALUES (1,1,'KOZ-01','Kozijn','st','1.1250'),(2,2,'SECRET','Other','st','5.0000'),(3,3,'INVALID','Other','st','7.0000')");
$result = (new OfficeCalcHousingQuantityReader($db))->forProject(1);
if (count($result['scopes']) !== 1
    || $result['scopes'][0]['dwelling_count'] !== 8
    || $result['scopes'][0]['lines'][0]['quantity_per_dwelling'] !== '1.1250'
    || count($result['scopes'][0]['lines']) !== 1) {
    throw new RuntimeException('Housing quantity isolation or precision contract failed: ' . json_encode($result));
}
echo "OFFICE_CALC_HOUSING_QUANTITY_READER_PASS\n";
