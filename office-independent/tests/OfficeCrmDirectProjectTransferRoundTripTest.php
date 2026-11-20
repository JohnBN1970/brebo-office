<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmDirectProjectTransfer.php';
use Brebo\Office\Crm\OfficeCrmDirectProjectTransfer;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    throw new RuntimeException('SQLite PDO driver required for direct transfer test');
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec("CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY,relation_id INTEGER,title TEXT,stage TEXT,status TEXT)");
$db->exec("CREATE TABLE office_projects (id INTEGER PRIMARY KEY,title TEXT,status TEXT)");
$db->exec("CREATE TABLE office_crm_direct_project_transfers (opportunity_id INTEGER PRIMARY KEY,project_id INTEGER UNIQUE,relation_id INTEGER)");
$db->exec("CREATE TABLE office_calc_crm_acceptance_transfers (crm_opportunity_id INTEGER,office_project_id INTEGER)");
$db->exec("INSERT INTO office_crm_opportunities VALUES (1,5,'Directe opdracht','won','closed'),(2,5,'Nog niet gegund','proposal','open')");
$service=new OfficeCrmDirectProjectTransfer($db);
$calls=0;
$create=static function(PDO $connection,array $opportunity) use (&$calls):int {
    $calls++;
    $stmt=$connection->prepare("INSERT INTO office_projects (title,status) VALUES (?,'preparation')");
    $stmt->execute([$opportunity['title']]);
    return (int)$connection->lastInsertId();
};
$id=$service->transfer(1,$create);
if ($id<1 || $service->transfer(1,$create)!==$id || $calls!==1) {
    throw new RuntimeException('Direct transfer is not idempotent');
}
$denied=false;
try { $service->transfer(2,$create); } catch (DomainException $e) { $denied=true; }
if (!$denied || $calls!==1) throw new RuntimeException('Unaccepted opportunity created a project');
$db->prepare('DELETE FROM office_projects WHERE id=?')->execute([$id]);
$denied=false;
try { $service->transfer(1,$create); } catch (DomainException $e) { $denied=true; }
if (!$denied || $calls!==1) throw new RuntimeException('Orphaned transfer was accepted');
echo "CRM_DIRECT_PROJECT_TRANSFER_ROUNDTRIP=PASS\n";
