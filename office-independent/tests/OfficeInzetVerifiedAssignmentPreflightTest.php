<?php
declare(strict_types=1);

$base = dirname(__DIR__) . '/src/Inzet/';
foreach ([
    'OfficeInzetIdentityMappingGuard.php',
    'OfficeInzetProjectIdentityGuard.php',
    'OfficeInzetVerifiedAssignmentResolver.php',
    'OfficeInzetIdentityTargetVerifier.php',
    'OfficeInzetVerifiedAssignmentEligibility.php',
    'OfficeInzetVerifiedAssignmentPreflight.php',
] as $file) {
    require_once $base . $file;
}

use Brebo\Office\Inzet\OfficeInzetVerifiedAssignmentPreflight;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER, person_id INTEGER)');
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)');
$db->exec('INSERT INTO office_projects VALUES (17)');
$db->exec('INSERT INTO office_inzet_people VALUES (101,1),(102,0)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101),(17,102)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1),('viewer',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage'),('viewer',17,'view')");

$preflight = new OfficeInzetVerifiedAssignmentPreflight(
    $db,
    [
        ['source_system'=>'legacy_drupal','source_person_id'=>'uid-8','office_person_id'=>101],
        ['source_system'=>'legacy_drupal','source_person_id'=>'uid-9','office_person_id'=>102],
    ],
    [['source_system'=>'legacy_drupal','source_project_id'=>'node-4538','office_project_id'=>17]]
);
if ($preflight->verify('manager','legacy_drupal','node-4538','uid-8') !== ['project_id'=>17,'person_id'=>101]) {
    throw new RuntimeException('Verified assignment preflight failed');
}
foreach ([
    ['viewer','legacy_drupal','node-4538','uid-8'],
    ['manager','legacy_drupal','node-4538','uid-9'],
    ['manager','legacy_drupal','unknown','uid-8'],
    ['manager','legacy_drupal','node-4538','unknown'],
] as $case) {
    try {
        $preflight->verify(...$case);
    } catch (RuntimeException $error) {
        continue;
    }
    throw new RuntimeException('Unverified assignment passed preflight');
}
echo "Office Inzet assignment preflight OK\n";
