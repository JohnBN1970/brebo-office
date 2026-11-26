<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeOnSiteFourEyesGuard.php';
use Brebo\Office\Inzet\OfficeOnSiteFourEyesGuard;
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_account_personnel_links (operator_id INTEGER PRIMARY KEY,person_id INTEGER UNIQUE)');
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY,active INTEGER)');
$db->exec('INSERT INTO office_operator_accounts VALUES (1,1)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY,active INTEGER)');
$db->exec('INSERT INTO office_inzet_people VALUES (7,1),(8,1),(9,1)');
$guard=new OfficeOnSiteFourEyesGuard($db);
$reject=function(string $reviewer,int $person) use ($guard):void {
    try { $guard->assertIndependent($reviewer,$person); }
    catch (DomainException $expected) { return; }
    throw new RuntimeException('Unsafe four-eyes approval accepted');
};
$reject('1',7);
$db->exec("INSERT INTO office_account_personnel_links VALUES ('1',7)");
$reject('1',7);
$guard->assertIndependent('1',8);
$db->exec("UPDATE office_inzet_people SET active=0 WHERE id=7");
$reject('1',8);
$db->exec("UPDATE office_inzet_people SET active=1 WHERE id=7");
$guard->assertIndependent('1',8);
$db->exec("UPDATE office_operator_accounts SET active=0 WHERE id=1");
$reject('1',8);
$db->exec("UPDATE office_operator_accounts SET active=1 WHERE id=1");
$guard->assertIndependent('1',8);
echo "ONSITE_FOUR_EYES_GUARD_OK\n";
