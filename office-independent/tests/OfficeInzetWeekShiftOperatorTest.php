<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetWeekShiftReadRepository.php';

use Brebo\Office\Inzet\OfficeInzetWeekShiftReadRepository;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)");
$db->exec("CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)");
$db->exec("CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, display_name TEXT)");
$db->exec("CREATE TABLE office_inzet_shifts (id INTEGER PRIMARY KEY, project_id INTEGER, person_id INTEGER, starts_at TEXT, ends_at TEXT, status TEXT)");
$db->exec("INSERT INTO office_operator_accounts VALUES ('john.boon',1),('other.user',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('john.boon',17,'read'),('other.user',18,'read')");
$db->exec("INSERT INTO office_inzet_people VALUES (8,'Medewerker')");
$db->exec("INSERT INTO office_inzet_shifts VALUES (1,17,8,'2026-10-19 07:00:00','2026-10-19 15:00:00','planned'),(2,18,8,'2026-10-19 07:00:00','2026-10-19 15:00:00','planned')");
$repo = new OfficeInzetWeekShiftReadRepository($db);
$rows = $repo->listWeek('john.boon',[17,18],2026,43);
if (count($rows) !== 1 || (int)$rows[0]['project_id'] !== 17 || $rows[0]['person_name'] !== 'Medewerker') {
  throw new RuntimeException('Weekly roster exposed unauthorized project or lost employee name');
}
if ($repo->listWeek('other.user',[17],2026,43) !== []) throw new RuntimeException('Membership bypass');
$rejected = false;
try { $repo->listWeek('',[17],2026,43); } catch (InvalidArgumentException $e) { $rejected = true; }
if (!$rejected) throw new RuntimeException('Empty operator accepted');
echo "Office week roster string operator authorization OK\n";
