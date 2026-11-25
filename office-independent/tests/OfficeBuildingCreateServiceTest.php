<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateService.php';
use Brebo\Office\Buildings\OfficeBuildingCreateService;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_buildings (id INTEGER PRIMARY KEY, building_name TEXT NOT NULL, street_address TEXT, postal_code TEXT, city TEXT, latitude REAL, longitude REAL)');
$s=new OfficeBuildingCreateService($db);
$input=['building_name'=>'Blok A','street_address'=>'Teststraat 1','postal_code'=>'1234 AB','city'=>'Teststad'];
$reject=static function(Closure $fn,string $expected):void {
    try {$fn();} catch (DomainException $e) {if($e->getMessage()===$expected)return;throw $e;}
    throw new RuntimeException('Expected '.$expected);
};
$reject(fn()=>$s->create($input,fn()=>false),'building_create_forbidden');
if((int)$db->query('SELECT COUNT(*) FROM office_buildings')->fetchColumn()!==0)throw new RuntimeException('Unauthorized write');
$id=$s->create($input,fn()=>true);
if($id!=='1')throw new RuntimeException('Missing identity');
$reject(fn()=>$s->create(array_replace($input,['postal_code'=>'1234ab']),fn()=>true),'building_address_candidate_exists');
$reject(fn()=>$s->create(['building_name'=>[]],fn()=>true),'invalid_building_input');
$s->create(array_replace($input,['building_name'=>'Blok B','street_address'=>'Teststraat 2']),fn()=>true);
if((int)$db->query('SELECT COUNT(*) FROM office_buildings')->fetchColumn()!==2)throw new RuntimeException('Unexpected building count');
echo "OfficeBuildingCreateServiceTest OK\n";
