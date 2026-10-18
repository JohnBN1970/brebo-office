<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetVerifiedAssignmentEligibility.php';

use Brebo\Office\Inzet\OfficeInzetVerifiedAssignmentEligibility;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY,active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT,project_id INTEGER,permission TEXT)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER,person_id INTEGER)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY,active INTEGER)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1),('disabled',0),('viewer',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage'),('disabled',17,'manage'),('viewer',17,'view')");
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101),(17,102)');
$db->exec('INSERT INTO office_inzet_people VALUES (101,1),(102,0),(103,1)');
$eligibility = new OfficeInzetVerifiedAssignmentEligibility($db);
$eligibility->assertManageable('manager',['project_id'=>17,'person_id'=>101]);
foreach ([
    ['manager',17,102],
    ['manager',17,103],
    ['manager',18,101],
    ['disabled',17,101],
    ['viewer',17,101],
    ['other',17,101],
] as [$operator,$project,$person]) {
    try {
        $eligibility->assertManageable($operator,['project_id'=>$project,'person_id'=>$person]);
    } catch (RuntimeException $error) {
        continue;
    }
    throw new RuntimeException('Unauthorized assignment accepted');
}
echo "Office verified assignment eligibility OK\n";
