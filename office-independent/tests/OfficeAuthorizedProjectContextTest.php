<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectAccess.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContext.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContextClient.php';
require_once dirname(__DIR__) . '/src/Project/OfficeAuthorizedProjectContext.php';

use Brebo\Office\Project\OfficeProjectAccess;
use Brebo\Office\Project\OfficeCanonicalProjectContextClient;
use Brebo\Office\Project\OfficeAuthorizedProjectContext;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Authorized project context SQLite test skipped: driver unavailable.\n";
  return;
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_calc_project_access (operator_id TEXT NOT NULL, project_id INTEGER NOT NULL)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('a',1),('inactive',0)");
$db->exec("INSERT INTO office_calc_project_access VALUES ('a',42),('inactive',42)");
$service = new OfficeAuthorizedProjectContext(new OfficeProjectAccess($db), new OfficeCanonicalProjectContextClient('https://example.com', str_repeat('s', 32)));
foreach ([['unknown',42], ['inactive',42], ['a',43], ['',42]] as [$operator, $project]) {
  try {
    $service->getForOperator($operator, $project);
    throw new RuntimeException('Unauthorized context was accepted.');
  } catch (DomainException $expected) {
  }
}
echo "Unauthorized project context denied before HTTP.\n";
