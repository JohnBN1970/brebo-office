<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcProjectContextRepository.php';

use Brebo\Office\Calculation\OfficeCalcProjectContextRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: PDO SQLite not installed.\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_calc_project_context (
 project_id INTEGER PRIMARY KEY, project_code TEXT, title TEXT, status TEXT,
 client_name TEXT, client_organization_id INTEGER, client_organization_title TEXT,
 project_kind TEXT, disciplines_json TEXT, description TEXT)');
$db->exec('CREATE TABLE office_calc_project_context_buildings (
 project_id INTEGER, building_id INTEGER, title TEXT, PRIMARY KEY(project_id,building_id))');
$db->exec("INSERT INTO office_calc_project_context VALUES
 (42,'BREBO-42','Project 42','active','Klant',NULL,NULL,'renovation','[\"glass\"]','Test'),
 (43,'BREBO-43','Project 43','inactive','Klant',NULL,NULL,'renovation','[]','Test'),
 (44,'BREBO-44','','active','Klant',NULL,NULL,'renovation','[]','Test')");
$db->exec("INSERT INTO office_calc_project_context_buildings VALUES (42,5,'Gebouw 5')");
$repo = new OfficeCalcProjectContextRepository($db);
$result = $repo->find(42);
if ($result === null || $result['project']['id'] !== 42 || count($result['project']['buildings']) !== 1
 || $result['project']['disciplines'] !== ['glass']) {
  throw new RuntimeException('Valid project context not returned.');
}
foreach ([0, 43, 44, 999] as $id) {
  if ($repo->find($id) !== null) {
    throw new RuntimeException('Invalid project context accepted: ' . $id);
  }
}
echo "Office Calc project context repository checks passed.\n";
