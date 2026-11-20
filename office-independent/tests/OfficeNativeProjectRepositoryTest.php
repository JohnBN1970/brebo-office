<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContext.php';
require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectRepository.php';

use Brebo\Office\Project\OfficeNativeProjectRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: SQLite PDO unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_projects (id INTEGER PRIMARY KEY, code TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL)");
$db->exec("INSERT INTO office_projects VALUES (1, 'TEST-001', 'Testproject', 'active')");
$repo = new OfficeNativeProjectRepository($db);
$project = $repo->find(1);
if ($project === null || $project->identity->projectId !== 1
  || $project->identity->projectCode !== 'TEST-001'
  || $project->title !== 'Testproject' || $project->status !== 'active') {
  throw new RuntimeException('Canonical native project resolution failed');
}
if ($repo->find(2) !== null || $repo->find(0) !== null) {
  throw new RuntimeException('Unknown project must not resolve');
}
echo "Office native project repository OK\n";
