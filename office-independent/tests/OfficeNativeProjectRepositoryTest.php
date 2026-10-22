<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContext.php';
require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectRepository.php';

use Brebo\Office\Project\OfficeNativeProjectRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Native Office project repository test skipped: SQLite unavailable.\n";
  return;
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY, code TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL)');
$db->exec("INSERT INTO office_projects (id,code,title,status) VALUES (42,'BREBO-42','Testproject','preparation')");
$repository = new OfficeNativeProjectRepository($db);
$project = $repository->find(42);
if ($project === null || $project->identity->projectId !== 42 || $project->title !== 'Testproject' || $project->status !== 'preparation') {
  throw new RuntimeException('Native Office project was not loaded correctly.');
}
if ($repository->find(43) !== null || $repository->find(0) !== null) {
  throw new RuntimeException('Unknown native Office project must not resolve.');
}
echo "Native Office project repository tests passed.\n";
