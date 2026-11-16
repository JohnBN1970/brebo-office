<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeOnSiteFourEyesGuard.php';
use Brebo\Office\Inzet\OfficeOnSiteFourEyesGuard;
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_people (operator_id TEXT,person_id INTEGER)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY,active INTEGER)');
$db->exec('INSERT INTO office_inzet_people VALUES (7,1),(8,1),(9,1)');
$guard=new OfficeOnSiteFourEyesGuard($db);
$reject=function(string $reviewer,int $person) use ($guard):void {
    try { $guard->assertIndependent($reviewer,$person); }
    catch (DomainException $expected) { return; }
    throw new RuntimeException('Unsafe four-eyes approval accepted');
};
$reject('manager',7);
$db->exec("INSERT INTO office_operator_people VALUES ('manager',7)");
$reject('manager',7);
$guard->assertIndependent('manager',8);
$db->exec("UPDATE office_inzet_people SET active=0 WHERE id=7");
$reject('manager',8);
$db->exec("UPDATE office_inzet_people SET active=1 WHERE id=7");
$guard->assertIndependent('manager',8);
$db->exec("INSERT INTO office_operator_people VALUES ('manager',9)");
$reject('manager',8);
echo "ONSITE_FOUR_EYES_GUARD_OK\n";
