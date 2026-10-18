<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftAssignmentControl.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftWriter.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCommand.php';

use Brebo\Office\Inzet\OfficeInzetShiftCommand;
use Brebo\Office\Inzet\OfficeInzetShiftWriter;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id INTEGER,project_id INTEGER,permission TEXT)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY,active INTEGER)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER,person_id INTEGER)');
$db->exec('INSERT INTO office_operator_accounts VALUES (1,1),(2,0)');
$db->exec("INSERT INTO office_project_memberships VALUES (1,17,'manage'),(2,17,'manage')");
$db->exec('INSERT INTO office_inzet_people VALUES (8,1),(9,0)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,8),(17,9)');
$writer = new OfficeInzetShiftWriter($db);
$command = new OfficeInzetShiftCommand($db,$writer);
$base = ['project_id'=>17,'person_id'=>8,'starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00'];
foreach ([[2,$base],[1,array_replace($base,['person_id'=>9])],[1,array_replace($base,['project_id'=>18])]] as [$operator,$request]) {
  $rejected = false;
  try { $command->createForOperator($operator,$request); } catch (RuntimeException $e) { $rejected = true; }
  if (!$rejected) throw new RuntimeException('Unauthorized shift accepted');
}
echo "Office shift authorization checks OK\n";
