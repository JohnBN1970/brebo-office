<?php
declare(strict_types=1);

/**
 * Browser-journey contract test without a live Hostinger deployment:
 * HTML form -> POST application boundary -> persisted shift -> weekly HTML.
 */
namespace Brebo\Office\Inzet;
use PDO;
use RuntimeException;

final class OfficeInzetJourneyPdo extends PDO {
    public function prepare(string $query, array $options = []): \PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$src = dirname(__DIR__).'/src/Inzet/';
foreach ([
    'OfficeInzetShiftAssignmentControl.php','OfficeInzetShiftWriter.php',
    'OfficeInzetShiftCommand.php','OfficeInzetLocalTime.php',
    'OfficeInzetShiftCsrf.php','OfficeInzetShiftSubmission.php',
    'OfficeInzetShiftPostHandler.php','OfficeInzetShiftForm.php',
    'OfficeInzetWeekShiftReadRepository.php','OfficeInzetPlanningWorkbench.php',
] as $file) require_once $src.$file;
$db = new OfficeInzetJourneyPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER, display_name TEXT, personnel_number TEXT)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER, person_id INTEGER)');
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_inzet_shifts (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, person_id INTEGER, starts_at TEXT, ends_at TEXT, status TEXT)');
$db->exec("INSERT INTO office_projects VALUES (17)");
$db->exec("INSERT INTO office_inzet_people VALUES (101,1,'Test medewerker','BREBO-101')");
$db->exec("INSERT INTO office_inzet_project_team VALUES (17,101)");
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage')");
$csrf = str_repeat('a',64);
$html = OfficeInzetShiftForm::renderEditable(
    ['id'=>17,'title'=>'Testproject'],
    [['id'=>101,'display_name'=>'Test medewerker','personnel_number'=>'BREBO-101']],
    '/office-test/inzet/dienst/nieuw',$csrf
);
if (!str_contains($html,'method="post"') || !str_contains($html,'value="'.$csrf.'"')
    || !str_contains($html,'name="person_id"')) throw new RuntimeException('Browser form incomplete');
$handler = new OfficeInzetShiftPostHandler(
    new OfficeInzetShiftSubmission(new OfficeInzetShiftCommand($db,new OfficeInzetShiftWriter($db)))
);
$form = ['project_id'=>'17','person_id'=>'101','starts_at_local'=>'2026-10-20T08:00',
    'ends_at_local'=>'2026-10-20T16:00','csrf_token'=>$csrf];
foreach ([['GET','manager',$form,$csrf],['POST','manager',$form,str_repeat('b',64)],['POST','intruder',$form,$csrf]] as $bad) {
    try { $handler->handle(...$bad); }
    catch (RuntimeException | \InvalidArgumentException $expected) { continue; }
    throw new RuntimeException('Unauthorized browser submission succeeded');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 0) {
    throw new RuntimeException('Rejected browser request wrote a shift');
}
$id = $handler->handle('POST','manager',$form,$csrf);
if ($id < 1) throw new RuntimeException('Authorized browser submission not persisted');
$rows = (new OfficeInzetWeekShiftReadRepository($db))->listWeek('manager',[17],2026,43);
if (count($rows) !== 1 || (int)$rows[0]['id'] !== $id) {
    throw new RuntimeException('New shift missing from authorized week');
}
$weekHtml = OfficeInzetPlanningWorkbench::renderWeek($rows,[17],2026,43,[17=>'Testproject']);
foreach (['Test medewerker','Testproject','08:00','Weekplanning 2026-W43'] as $part) {
    if (!str_contains($weekHtml,$part)) throw new RuntimeException('Week UI missing '.$part);
}
echo "Office Inzet form-to-POST-to-week HTML journey OK\n";
