<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Transactional SQLite integration fixture for the native MySQL shift writer.
 * SQLite has no FOR UPDATE, so the test-only PDO adapter removes that clause.
 * Production SQL and authorization logic are unchanged.
 */
final class OfficeInzetSqliteTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}

$base = dirname(__DIR__) . '/src/Inzet/';
foreach ([
    'OfficeInzetIdentityMappingGuard.php',
    'OfficeInzetProjectIdentityGuard.php',
    'OfficeInzetVerifiedAssignmentResolver.php',
    'OfficeInzetIdentityTargetVerifier.php',
    'OfficeInzetVerifiedAssignmentEligibility.php',
    'OfficeInzetVerifiedAssignmentPreflight.php',
    'OfficeInzetShiftAssignmentControl.php',
    'OfficeInzetShiftWriter.php',
    'OfficeInzetShiftCommand.php',
    'OfficeInzetVerifiedShiftCommand.php',
] as $file) require_once $base . $file;

$db = new OfficeInzetSqliteTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER, person_id INTEGER)');
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_inzet_shifts (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, person_id INTEGER, starts_at TEXT, ends_at TEXT, status TEXT)');
$db->exec('INSERT INTO office_projects VALUES (17)');
$db->exec('INSERT INTO office_inzet_people VALUES (101,1)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage')");

$preflight = new OfficeInzetVerifiedAssignmentPreflight(
    $db,
    [['source_system'=>'legacy_drupal','source_person_id'=>'uid-8','office_person_id'=>101]],
    [['source_system'=>'legacy_drupal','source_project_id'=>'node-4538','office_project_id'=>17]]
);
$writer = new OfficeInzetShiftWriter($db);
$native = new OfficeInzetShiftCommand($db, $writer);
$adapter = new OfficeInzetVerifiedShiftCommand($preflight, $native);
$request = ['starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00'];
$id = $adapter->createForOperator('manager','legacy_drupal','node-4538','uid-8',$request);
if ($id < 1) throw new RuntimeException('Shift insert did not return an ID');
$stored = $db->query('SELECT project_id,person_id,starts_at,ends_at,status FROM office_inzet_shifts')->fetch(PDO::FETCH_ASSOC);
if ($stored === false || (int)$stored['project_id'] !== 17 || (int)$stored['person_id'] !== 101
    || $stored['starts_at'] !== $request['starts_at'] || $stored['ends_at'] !== $request['ends_at']
    || $stored['status'] !== 'planned') {
    throw new RuntimeException('Persisted shift did not round-trip');
}
foreach ([
    ['manager','legacy_drupal','node-4538','uid-8',$request],
    ['intruder','legacy_drupal','node-4538','uid-8',['starts_at'=>'2026-10-20 08:00:00','ends_at'=>'2026-10-20 16:00:00']],
] as $case) {
    try { $adapter->createForOperator(...$case); }
    catch (\RuntimeException | \InvalidArgumentException $error) { continue; }
    throw new RuntimeException('Invalid shift was inserted');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 1) {
    throw new RuntimeException('Rejected shift altered persisted rows');
}
echo "Native Office shift persisted and read back OK\n";
