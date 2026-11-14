<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingDuplicateCandidates.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingProjectCandidates.php';

use Brebo\Office\Buildings\OfficeBuildingDuplicateCandidates;
use Brebo\Office\Buildings\OfficeBuildingProjectCandidates;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "duplicate-candidates=SKIP (pdo_sqlite unavailable)\n";
    exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_buildings (id INTEGER PRIMARY KEY,building_name TEXT,street_address TEXT,postal_code TEXT,city TEXT,active INTEGER)");
$db->exec("CREATE TABLE office_project_buildings (project_id INTEGER,building_id INTEGER)");
$db->exec("INSERT INTO office_buildings VALUES (1,'Gebouw A','Hoofdstraat 12','4811 AB','Breda',1),(2,'Gebouw B','Hoofdstraat 12A','4811AB','Breda',1),(3,'Inactief','Hoofdstraat 12','4811AB','Breda',0)");
$db->exec("INSERT INTO office_project_buildings VALUES (100,1),(101,1),(200,2)");
$buildings = new OfficeBuildingDuplicateCandidates($db);
$found = $buildings->find('4811 ab',' Hoofdstraat 12 ',static fn(string $id):bool=>$id==='1');
if (count($found)!==1 || $found[0]['id']!=='1') throw new RuntimeException('building matching or ACL failed');
if ($buildings->find('4811AB','Hoofdstraat 12',static fn(string $id):bool=>false)!==[]) throw new RuntimeException('building ACL leak');
$projects = new OfficeBuildingProjectCandidates($db);
$foundProjects=$projects->forBuilding('1',static fn(string $id):bool=>true,static fn(string $id):bool=>$id==='101');
if (count($foundProjects)!==1 || $foundProjects[0]['project_id']!=='101') throw new RuntimeException('project ACL failed');
if ($projects->forBuilding('1',static fn(string $id):bool=>false,static fn(string $id):bool=>true)!==[]) throw new RuntimeException('building access leak');
echo "duplicate-candidates=OK\n";
