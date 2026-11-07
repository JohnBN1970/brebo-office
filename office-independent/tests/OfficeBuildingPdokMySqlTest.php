<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokSchemaMigration.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokSaveAction.php';
use Brebo\Office\Buildings\OfficeBuildingPdokSchemaMigration as Migration;
use Brebo\Office\Buildings\OfficeBuildingPdokLocationRepository as Repository;
use Brebo\Office\Buildings\OfficeBuildingPdokSaveAction as Action;
use Brebo\Office\Buildings\OfficeBuildingPdokClient as Client;
use Brebo\Office\Buildings\OfficeBuildingPdokRequestGuard as Guard;

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function denied(Closure $call): void {
  try { $call(); } catch (DomainException | RuntimeException | InvalidArgumentException $e) { return; }
  throw new LogicException('request should fail');
}
$dsn = getenv('OFFICE_PDOK_TEST_DSN');
// Fixture creation is confined to a fresh CI database; never accept live DSNs.
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=office_pdok_ci;charset=utf8mb4') throw new RuntimeException('isolated_ci_database_required');
$db = new PDO($dsn, 'root', 'test-root-only', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$db->exec('CREATE TABLE office_buildings (id INT PRIMARY KEY, name VARCHAR(160), address_line VARCHAR(255), postal_code VARCHAR(32), city VARCHAR(160)) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_building_zones (id INT PRIMARY KEY, building_id INT, active TINYINT, latitude DECIMAL(10,7), longitude DECIMAL(10,7)) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_project_buildings (project_id INT, building_id INT, PRIMARY KEY(project_id,building_id)) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY, active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_project_memberships (project_id INT, operator_id VARCHAR(128), permission VARCHAR(16), PRIMARY KEY(project_id,operator_id)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_buildings VALUES (1,'Existing A','Original','1234AB','Breda'), (2,'Existing B','Other','1234AB','Breda')");
$db->exec('INSERT INTO office_building_zones VALUES (1,1,1,51.5,4.3),(2,1,1,51.5,4.3),(3,1,1,51.5,4.3),(4,1,1,51.5,4.3)');
$db->exec('INSERT INTO office_project_buildings VALUES (10,1),(20,2)');
$db->exec('INSERT INTO office_operator_accounts VALUES ('manager',1),('reader',1),('disabled',0)');
$db->exec("INSERT INTO office_project_memberships VALUES (10,'manager','manage'),(10,'reader','read'),(10,'disabled','manage')");
$rows = fn($table)=>$db->query('SELECT * FROM '.$table.' ORDER BY 1,2')->fetchAll(PDO::FETCH_ASSOC);
$before = $rows('office_buildings'); $zones = $rows('office_building_zones'); $links = $rows('office_project_buildings');
$repo = new Repository($db);
check(in_array(false,$repo->readiness(),true),'missing schema considered ready');
check(Migration::apply($db)===['latitude','longitude','bag_id'],'migration additions wrong');
check(Migration::apply($db)===[],'migration not idempotent');
check($db->query('SELECT id,name,address_line,postal_code,city FROM office_buildings ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$before,'migration changed data');
check($rows('office_building_zones')===$zones && $rows('office_project_buildings')===$links,'migration changed zones/links');
$doc = ['id'=>'adr-fixture','bron'=>'BAG','type'=>'adres','nummeraanduiding_id'=>'0363200003761447','straatnaam'=>'Dam','huis_nlt'=>'1','postcode'=>'1012JS','woonplaatsnaam'=>'Amsterdam','centroide_ll'=>'POINT(4.8937175 52.37329259)','weergavenaam'=>'Dam 1'];
$calls = 0;
$client = new Client(function($url) use (&$calls,$doc) { $calls++; return ['response'=>['docs'=>[$doc]]]; });
$action = new Action($repo,$client);
$secret = str_repeat('a',64); $now = 1800000000;
$server = ['REQUEST_METHOD'=>'POST','HTTP_ORIGIN'=>'https://example.test','HTTP_HOST'=>'example.test','HTTPS'=>'on'];
$post = ['action'=>'save_bag','pdok_id'=>'adr-fixture','csrf'=>Guard::token($secret,1,'/office-test',$now),
  'latitude'=>'0','longitude'=>'0','address'=>'FORGED','bag_id'=>'FORGED'];
$save = fn($operator='manager',$building=1,$s=null,$p=null,$a=null)=>($a ?? $action)->execute($operator,$building,$secret,'/office-test','example.test',$s ?? $server,$p ?? $post,$now);
$original = $rows('office_buildings');
denied(fn()=>$save('reader')); denied(fn()=>$save('disabled')); denied(fn()=>$save('manager',2));
denied(fn()=>$save('manager',1,array_replace($server,['HTTP_ORIGIN'=>'https://evil.test'])));
denied(fn()=>$save('manager',1,$server,array_replace($post,['csrf'=>'bad'])));
check($calls===0 && $rows('office_buildings')===$original,'unauthorized call reached PDOK or mutated data');
$save(); $save(); // Same-location resubmission must succeed with rowCount=0.
$saved = $rows('office_buildings');
check($saved[0]['bag_id']==='0363200003761447' && $saved[0]['address_line']==='Dam 1' && (float)$saved[0]['latitude']>52,'unverified browser data persisted');
check($saved[1]===$original[1] && $saved[0]['name']==='Existing A','unrelated building/name changed');
// Revocation during PDOK lookup must be detected by the second locked ACL check.
$revoking = new Client(function($url) use ($db,$doc) { $db->exec("UPDATE office_project_memberships SET permission='read' WHERE operator_id='manager'"); return ['response'=>['docs'=>[$doc]]]; });
denied(fn()=>$save('manager',1,$server,$post,new Action($repo,$revoking)));
check(!$db->inTransaction() && $rows('office_buildings')===$saved,'revoked permission mutated data');
$db->exec("UPDATE office_project_memberships SET permission='manage' WHERE operator_id=1");
$offline = new Client(fn($url)=>throw new RuntimeException('offline'));
denied(fn()=>$save('manager',1,$server,$post,new Action($repo,$offline)));
$mismatch = new Client(fn($url)=>['response'=>['docs'=>[array_replace($doc,['id'=>'adr-other'])]]]);
denied(fn()=>$save('manager',1,$server,$post,new Action($repo,$mismatch)));
check($rows('office_buildings')===$saved && $rows('office_building_zones')===$zones && $rows('office_project_buildings')===$links,'failed save changed data/zones/links');
echo "PDOK_MYSQL_PRESERVATION_AND_ACL_OK\n";
