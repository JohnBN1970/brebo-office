<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeBuildingElementRepository.php';
use Brebo\Office\Project\OfficeBuildingElementRepository;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_buildings (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_housing_types (id INTEGER PRIMARY KEY,building_id INTEGER)');
$db->exec('CREATE TABLE office_building_elements (id INTEGER PRIMARY KEY,building_id INTEGER,housing_type_id INTEGER,classification_code TEXT,element_code TEXT,description TEXT,unit TEXT,quantity TEXT,source_kind TEXT,source_reference TEXT)');
$db->exec('INSERT INTO office_buildings VALUES (1),(2)');
$db->exec('INSERT INTO office_housing_types VALUES (10,1),(20,2)');
$r=new OfficeBuildingElementRepository($db);
$r->create(1,10,'31','K01','Kozijn','st','2.1250','measurement','M1');
$r->create(1,null,'27','D01','Dak','m2','10','manual');
$r->create(2,20,'31','K02','Ander gebouw','st','3','manual');
$rows=$r->forBuilding(1);
if(count($rows)!==2 || $rows[0]['classification_code']!=='27' || $rows[1]['quantity']!=='2.1250')throw new RuntimeException('Building scope or precision failed');
foreach([[1,20,'2'],[99,null,'2'],[1,10,'-1'],[1,10,'1.12345']] as [$b,$h,$q]){
    $rejected=false;
    try{$r->create($b,$h,'31','BAD','Invalid','st',$q,'manual');}
    catch(InvalidArgumentException|RuntimeException $e){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Invalid element accepted');
}
if(count($r->forBuilding(1))!==2 || count($r->forBuilding(2))!==1)throw new RuntimeException('Rejected writes changed data');
$db->exec("INSERT INTO office_building_elements (building_id,housing_type_id,classification_code,element_code,description,unit,quantity,source_kind) VALUES (1,20,'31','INVALID','Wrong scope','st','1','manual')");
if(count($r->forBuilding(1))!==2)throw new RuntimeException('Cross-building housing reference leaked into read model');
foreach(['1','123','AB'] as $classification){
    $rejected=false;
    try{$r->create(1,null,$classification,'BAD','Invalid','st','1','manual');}
    catch(InvalidArgumentException $e){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Invalid classification accepted');
}
echo "BUILDING_ELEMENT_CONTRACT=PASS\n";
