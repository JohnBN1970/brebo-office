<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectAccess.php';

use Brebo\Office\Project\OfficeProjectAccess;
use Brebo\Office\Project\OfficeProjectIdentity;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Office project access SQLite test skipped: driver unavailable.\n";
  return;
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_calc_project_access (operator_id TEXT NOT NULL, project_id INTEGER NOT NULL)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('allowed', 1), ('inactive', 0), ('other', 1)");
$db->exec("INSERT INTO office_calc_project_access VALUES ('allowed', 42), ('inactive', 42)");
$access = new OfficeProjectAccess($db);
$project = new OfficeProjectIdentity(42, 'BREBO-2026-042');
if (!$access->canRead('allowed', $project)) {
  throw new RuntimeException('Explicit active project grant was rejected.');
}
foreach (['inactive', 'other', '', str_repeat('x', 129)] as $operator) {
  if ($access->canRead($operator, $project)) {
    throw new RuntimeException('Project access was granted without active explicit permission.');
  }
}
if ($access->canRead('allowed', new OfficeProjectIdentity(43, 'BREBO-2026-043'))) {
  throw new RuntimeException('Project grant leaked across projects.');
}
$db->exec('DROP TABLE office_calc_project_access');
if ($access->canRead('allowed', $project)) {
  throw new RuntimeException('Database failure must deny access.');
}
echo "Office project access boundary passed.\n";
