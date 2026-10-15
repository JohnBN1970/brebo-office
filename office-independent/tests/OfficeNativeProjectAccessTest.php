<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';

use Brebo\Office\Project\OfficeNativeProjectAccess;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Native Office project ACL SQLite test skipped: driver unavailable.\n";
  return;
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_project_memberships (project_id INTEGER, operator_id TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_calc_project_access (project_id INTEGER, operator_id TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('allowed',1),('inactive',0),('calc-only',1)");
$db->exec('INSERT INTO office_projects VALUES (42),(43)');
$db->exec("INSERT INTO office_project_memberships VALUES (42,'allowed','read'),(42,'inactive','manage')");
$db->exec("INSERT INTO office_calc_project_access VALUES (42,'calc-only')");
$access = new OfficeNativeProjectAccess($db);
if (!$access->canRead('allowed', 42)) throw new RuntimeException('Native member denied.');
foreach ([['inactive',42],['calc-only',42],['allowed',43],['',42],['allowed',0]] as [$operator,$project]) {
  if ($access->canRead($operator,$project)) throw new RuntimeException('Unauthorized native project access.');
}
echo "Native project ACL checks passed.\n";
