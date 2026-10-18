<?php
declare(strict_types=1);

use Brebo\Office\Inzet\OfficeInzetShiftCommand;
use Brebo\Office\Inzet\OfficeInzetShiftWriter;
use Brebo\Office\Inzet\OfficeInzetVerifiedAssignmentPreflight;
use Brebo\Office\Inzet\OfficeInzetVerifiedShiftCommand;
use Brebo\Office\Inzet\OfficeInzetWeekShiftReadRepository;

$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:') || !str_contains($dsn, 'dbname=office_inzet_ci')) {
    throw new RuntimeException('Dedicated office_inzet_ci MySQL database required');
}
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
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
    'OfficeInzetWeekShiftReadRepository.php',
    'OfficeInzetSchema.php',
] as $file) require_once $base . $file;

$db->exec('CREATE TABLE office_projects (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_operator_accounts (id VARCHAR(128) NOT NULL PRIMARY KEY, active TINYINT(1) NOT NULL) ENGINE=InnoDB');
$db->exec("CREATE TABLE office_project_memberships (operator_id VARCHAR(128) NOT NULL, project_id BIGINT UNSIGNED NOT NULL, permission VARCHAR(16) NOT NULL) ENGINE=InnoDB");
foreach (\Brebo\Office\Inzet\OfficeInzetSchema::statements() as $sql) $db->exec($sql);
$db->exec('INSERT INTO office_projects VALUES (17)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1),('intruder',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage')");
$db->exec("INSERT INTO office_inzet_people (id,display_name,personnel_number,active) VALUES (101,'Test medewerker','BREBO-101',1)");
$db->exec("INSERT INTO office_inzet_project_team (project_id,person_id) VALUES (17,101)");

$preflight = new OfficeInzetVerifiedAssignmentPreflight($db,
    [['source_system'=>'legacy_drupal','source_person_id'=>'uid-8','office_person_id'=>101]],
    [['source_system'=>'legacy_drupal','source_project_id'=>'node-4538','office_project_id'=>17]]
);
$command = new OfficeInzetVerifiedShiftCommand($preflight, new OfficeInzetShiftCommand($db, new OfficeInzetShiftWriter($db)));
$shift = ['starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00'];
$id = $command->createForOperator('manager','legacy_drupal','node-4538','uid-8',$shift);
if ($id < 1) throw new RuntimeException('No shift ID returned');
$week = (new OfficeInzetWeekShiftReadRepository($db))->listWeek('manager',[17],2026,43);
if (count($week) !== 1 || (int)$week[0]['id'] !== $id || (int)$week[0]['person_id'] !== 101
    || $week[0]['person_name'] !== 'Test medewerker') {
    throw new RuntimeException('MySQL shift not returned in authorized week');
}
if ((new OfficeInzetWeekShiftReadRepository($db))->listWeek('intruder',[17],2026,43) !== []) {
    throw new RuntimeException('Unauthorized MySQL week read');
}
foreach ([
    ['manager','legacy_drupal','node-4538','uid-8',$shift],
    ['intruder','legacy_drupal','node-4538','uid-8',['starts_at'=>'2026-10-20 08:00:00','ends_at'=>'2026-10-20 16:00:00']],
] as $attempt) {
    try { $command->createForOperator(...$attempt); }
    catch (RuntimeException | InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Rejected MySQL write unexpectedly succeeded');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 1) {
    throw new RuntimeException('MySQL failed-write transaction altered shift count');
}
echo "MySQL 8 native shift insert, lock, overlap and week read OK\n";
