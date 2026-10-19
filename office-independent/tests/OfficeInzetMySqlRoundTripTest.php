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
    'OfficeInzetPlanningWorkbench.php',
    'OfficeInzetLocalTime.php',
    'OfficeInzetShiftCsrf.php',
    'OfficeInzetShiftSubmission.php',
    'OfficeInzetShiftPostHandler.php',
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
$shift = ['starts_at'=>'2026-10-19 06:00:00','ends_at'=>'2026-10-19 14:00:00'];
$id = $command->createForOperator('manager','legacy_drupal','node-4538','uid-8',$shift);
if ($id < 1) throw new RuntimeException('No shift ID returned');
$week = (new OfficeInzetWeekShiftReadRepository($db))->listWeek('manager',[17],2026,43);
if (count($week) !== 1 || (int)$week[0]['id'] !== $id || (int)$week[0]['person_id'] !== 101
    || $week[0]['person_name'] !== 'Test medewerker') {
    throw new RuntimeException('MySQL shift not returned in authorized week');
}
$html = \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek($week,[17],2026,43,[17=>'Testproject']);
if (!str_contains($html,'Test medewerker') || !str_contains($html,'Testproject')
    || !str_contains($html,'Weekplanning 2026-W43') || !str_contains($html,'08:00')) {
    throw new RuntimeException('MySQL persisted shift missing from native week UI');
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
// Exercise the real browser form boundary against MySQL, not just the native command.
$handler = new \Brebo\Office\Inzet\OfficeInzetShiftPostHandler(
    new \Brebo\Office\Inzet\OfficeInzetShiftSubmission(
        new \Brebo\Office\Inzet\OfficeInzetShiftCommand($db, new \Brebo\Office\Inzet\OfficeInzetShiftWriter($db))
    )
);
$form = [
    'project_id'=>17,'person_id'=>101,
    'starts_at_local'=>'2026-10-20T08:00',
    'ends_at_local'=>'2026-10-20T16:00',
    'csrf_token'=>str_repeat('a',64),
];
foreach ([
    ['GET','manager',$form,str_repeat('a',64)],
    ['POST','manager',$form,str_repeat('b',64)],
    ['POST','intruder',$form,str_repeat('a',64)],
] as $rejected) {
    try { $handler->handle(...$rejected); }
    catch (RuntimeException | InvalidArgumentException $expected) { continue; }
    throw new RuntimeException('Untrusted HTTP form write accepted');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 1) {
    throw new RuntimeException('Rejected form altered MySQL');
}
$formId = $handler->handle('POST','manager',$form,str_repeat('a',64));
if ($formId < 1 || (int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 2) {
    throw new RuntimeException('Authorized HTTP form not persisted');
}
$afterForm = (new \Brebo\Office\Inzet\OfficeInzetWeekShiftReadRepository($db))->listWeek('manager',[17],2026,43);
if (count($afterForm) !== 2) throw new RuntimeException('Form shift not visible in weekly read');

// Exercise the personnel profile POST application boundary against real MySQL ACL.
foreach (['OfficeInzetPersonnelProfileRepository.php','OfficeInzetPersonnelProfileCsrf.php',
    'OfficeInzetPersonnelProfileAction.php'] as $file) require_once $base . $file;
$profile = new \Brebo\Office\Inzet\OfficeInzetPersonnelProfileAction(
    new \Brebo\Office\Inzet\OfficeInzetPersonnelProfileRepository($db));
$profileSession = str_repeat('c',64);
$profileSecret = str_repeat('d',48);
$profileForm = [
    'csrf_token'=>\Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf::token($profileSession,$profileSecret),
    'project_id'=>'17','person_id'=>'101','personnel_number'=>'BREBO-102',
];
foreach ([
    ['intruder',$profileForm,$profileSession],
    ['manager',array_merge($profileForm,['csrf_token'=>str_repeat('0',64)]),$profileSession],
    ['manager',array_merge($profileForm,['person_id'=>'999']),$profileSession],
] as [$actor,$payload,$session]) {
    try {
        $changed = $profile->update($payload,$actor,$session,$profileSecret);
        if ($changed) throw new RuntimeException('Unauthorized personnel profile write succeeded');
    } catch (DomainException|InvalidArgumentException $expected) {}
}
if ($db->query('SELECT personnel_number FROM office_inzet_people WHERE id=101')->fetchColumn() !== 'BREBO-101') {
    throw new RuntimeException('Rejected personnel profile write changed MySQL data');
}
// A non-manager must not learn whether a personnel number is already taken.
$duplicateProbe = array_merge($profileForm,['personnel_number'=>'BREBO-101']);
if ($profile->update($duplicateProbe,'intruder',$profileSession,$profileSecret)) {
    throw new RuntimeException('Unauthorized duplicate-number probe accepted');
}
if (!$profile->update($profileForm,'manager',$profileSession,$profileSecret)) {
    throw new RuntimeException('Authorized personnel profile update rejected');
}
if ($db->query('SELECT personnel_number FROM office_inzet_people WHERE id=101')->fetchColumn() !== 'BREBO-102') {
    throw new RuntimeException('Personnel profile update not persisted');
}
echo "MySQL personnel profile authorized write and unauthorized rejection OK\n";
echo "MySQL 8 native shift insert, lock, overlap and week read OK\n";
