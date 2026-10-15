<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeCalcScopedProjectList.php';

use Brebo\Office\Project\OfficeCalcScopedProjectList;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Calc-scoped project list SQLite test skipped: driver unavailable.\n";
  return;
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_calc_project_access (operator_id TEXT NOT NULL, project_id INTEGER NOT NULL)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('a',1),('b',1),('inactive',0)");
$db->exec("INSERT INTO office_calc_project_access VALUES ('a',42),('a',42),('a',43),('b',99),('inactive',88)");
$list = new OfficeCalcScopedProjectList($db);
if ($list->idsForOperator('a') !== [42,43] || $list->idsForOperator('b') !== [99]) {
  throw new RuntimeException('Project scope leaked or duplicates were not removed.');
}
if ($list->idsForOperator('inactive') !== [] || $list->idsForOperator('unknown') !== [] || $list->idsForOperator('a', 0) !== []) {
  throw new RuntimeException('Invalid or inactive project listing was permitted.');
}
if ($list->idsForOperator('a', 1) !== [42]) {
  throw new RuntimeException('Project list limit not enforced.');
}
$db->exec('DROP TABLE office_calc_project_access');
if ($list->idsForOperator('a') !== []) {
  throw new RuntimeException('Database failure must fail closed.');
}
echo "Calc-scoped project listing passed.\n";
