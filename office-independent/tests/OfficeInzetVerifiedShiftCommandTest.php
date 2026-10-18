<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet {
    class OfficeInzetShiftCommand {
        public array $calls = [];
        public function createForOperator(string $operator, array $request): int {
            $this->calls[] = [$operator, $request];
            return 77;
        }
    }
}

namespace {
    $base = dirname(__DIR__) . '/src/Inzet/';
    foreach ([
        'OfficeInzetIdentityMappingGuard.php',
        'OfficeInzetProjectIdentityGuard.php',
        'OfficeInzetVerifiedAssignmentResolver.php',
        'OfficeInzetIdentityTargetVerifier.php',
        'OfficeInzetVerifiedAssignmentEligibility.php',
        'OfficeInzetVerifiedAssignmentPreflight.php',
        'OfficeInzetVerifiedShiftCommand.php',
    ] as $file) require_once $base . $file;

    $db = new \PDO('sqlite::memory:');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
    $db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER)');
    $db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER, person_id INTEGER)');
    $db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
    $db->exec('CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)');
    $db->exec('INSERT INTO office_projects VALUES (17)');
    $db->exec('INSERT INTO office_inzet_people VALUES (101,1)');
    $db->exec('INSERT INTO office_inzet_project_team VALUES (17,101)');
    $db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1)");
    $db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage')");
    $preflight = new \Brebo\Office\Inzet\OfficeInzetVerifiedAssignmentPreflight(
        $db,
        [['source_system'=>'legacy_drupal','source_person_id'=>'uid-8','office_person_id'=>101]],
        [['source_system'=>'legacy_drupal','source_project_id'=>'node-4538','office_project_id'=>17]]
    );
    $native = new \Brebo\Office\Inzet\OfficeInzetShiftCommand();
    $adapter = new \Brebo\Office\Inzet\OfficeInzetVerifiedShiftCommand($preflight, $native);
    $request = ['starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00'];
    if ($adapter->createForOperator('manager','legacy_drupal','node-4538','uid-8',$request) !== 77) {
        throw new \RuntimeException('Native shift delegation failed');
    }
    if ($native->calls[0][1]['person_id'] !== 101 || $native->calls[0][1]['project_id'] !== 17) {
        throw new \RuntimeException('Verified Office IDs not delegated');
    }
    foreach ([
        ['manager','legacy_drupal','node-4538','uid-8',['person_id'=>999]],
        ['manager','legacy_drupal','node-4538','unknown',$request],
        ['intruder','legacy_drupal','node-4538','uid-8',$request],
    ] as $case) {
        try { $adapter->createForOperator(...$case); }
        catch (\RuntimeException $error) { continue; }
        throw new \RuntimeException('Invalid assignment reached native command');
    }
    if (count($native->calls) !== 1) throw new \RuntimeException('Unexpected native write attempt');
    echo "Verified native shift delegation OK\n";
}
