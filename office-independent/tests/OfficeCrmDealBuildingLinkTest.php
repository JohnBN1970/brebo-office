<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Crm/OfficeCrmDealBuildingLink.php';

use Brebo\Office\Crm\OfficeCrmDealBuildingLink;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PDO SQLite required\n");
    exit(1);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY, revision INTEGER NOT NULL, stage TEXT NOT NULL, status TEXT NOT NULL)');
$db->exec('CREATE TABLE office_buildings (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_crm_deal_buildings (opportunity_id INTEGER NOT NULL, building_id INTEGER NOT NULL, PRIMARY KEY(opportunity_id,building_id))');
$db->exec("INSERT INTO office_crm_opportunities VALUES (1,1,'prospect','open'),(2,1,'won','closed')");
$db->exec('INSERT INTO office_buildings VALUES (10),(11)');
$link = new OfficeCrmDealBuildingLink($db);
if ($link->buildingId(1) !== null) throw new RuntimeException('Unexpected initial link');
$link->assign(1,10,1);
if ($link->buildingId(1) !== 10) throw new RuntimeException('Assignment failed');
if ((int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn() !== 2) throw new RuntimeException('Revision not incremented');
$link->assign(1,10,2);
if ((int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn() !== 2) throw new RuntimeException('Idempotent assignment changed revision');
foreach ([[1,11,1],[1,999,2],[2,10,1]] as [$deal,$building,$revision]) {
    try {
        $link->assign($deal,$building,$revision);
        throw new RuntimeException('Unsafe assignment accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage()==='Unsafe assignment accepted') throw $e;
    }
}
if ($link->buildingId(1) !== 10) throw new RuntimeException('Rejected assignment modified link');

$db->exec('INSERT INTO office_buildings VALUES (12)');
$link->replaceBuildings(1,[12,10,11,10],2);

if ($link->buildingIds(1)!==[10,11,12])
    throw new RuntimeException('Multiple buildings failed');

if ((int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn()!==3)
    throw new RuntimeException('Multiple building revision failed');

$link->replaceBuildings(1,[11,12,10],3);

if ((int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn()!==3)
    throw new RuntimeException('Idempotency failed');

$reject = static function(callable $action): void {
    try {
        $action();
    } catch (RuntimeException $e) {
        return;
    }
    throw new RuntimeException('Unsafe operation accepted');
};

$reject(fn()=> $link->replaceBuildings(1,[10],2));
$reject(fn()=> $link->replaceBuildings(1,[10,999],3));
$reject(fn()=> $link->replaceBuildings(2,[10,11],1));
$reject(fn()=> $link->assign(1,10,3));
$reject(fn()=> $link->buildingId(1));

if ($link->buildingIds(1)!==[10,11,12])
    throw new RuntimeException('Rejected operation modified links');

$link->replaceBuildings(1,[10,12],3);

if ($link->buildingIds(1)!==[10,12])
    throw new RuntimeException('Building removal failed');

if ((int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn()!==4)
    throw new RuntimeException('Removal revision failed');

echo "MULTIPLE_BUILDINGS_TEST=PASS\\n";

echo "OFFICE_CRM_DEAL_BUILDING_LINK_PASS\n";
