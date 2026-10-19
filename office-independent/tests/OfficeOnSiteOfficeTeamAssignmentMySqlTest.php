<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) {
  throw new RuntimeException('Dedicated MySQL CI database required');
}
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAssignmentRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeTeamAssignmentRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAssignmentGate.php';
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec('CREATE TABLE office_inzet_people (id BIGINT PRIMARY KEY, active TINYINT NOT NULL)');
$db->exec('CREATE TABLE office_inzet_project_team (person_id BIGINT NOT NULL, project_id BIGINT NOT NULL)');
$db->exec('INSERT INTO office_inzet_people VALUES (101,1),(102,0),(103,1)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (101,17),(102,17),(103,18)');
$repo = new \Brebo\Office\Inzet\OnSiteOfficeTeamAssignmentRepository(
  $db,
  static fn(string $project, string $zone): bool => $project === '17' && $zone === '8'
);
$gate = new \Brebo\Office\Inzet\OnSiteAssignmentGate($repo);
foreach ([
  ['101','17','8',true],
  ['101','17','9',false],
  ['101','18','8',false],
  ['102','17','8',false],
  ['103','17','8',false],
  ['999','17','8',false],
] as [$employee,$project,$zone,$expected]) {
  $actual = $gate->authorize($employee, ['project_id'=>$project,'zone_id'=>$zone]);
  if ($actual !== $expected) throw new RuntimeException('Assignment check failed');
}
echo "MySQL OnSite team and verified zone checks passed\n";
