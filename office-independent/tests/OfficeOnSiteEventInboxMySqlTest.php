<?php
declare(strict_types=1);

$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) {
  throw new RuntimeException('Dedicated MySQL CI database required');
}
$base = dirname(__DIR__) . '/src/Inzet/';
foreach ([
  'OnSiteAutomaticEventValidator.php',
  'OnSiteAssignmentRepository.php',
  'OnSiteAssignmentGate.php',
  'OnSiteOfficeTeamAssignmentRepository.php',
  'OnSiteEventInbox.php',
  'OnSiteEventInboxSchema.php',
  'OnSiteAutomaticIngest.php',
] as $file) require_once $base . $file;
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, active TINYINT NOT NULL)');
$db->exec('CREATE TABLE office_inzet_project_team (person_id BIGINT UNSIGNED NOT NULL, project_id BIGINT UNSIGNED NOT NULL)');
$db->exec('INSERT INTO office_inzet_people VALUES (101,1),(102,0)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (101,17),(102,17)');
foreach (\Brebo\Office\Inzet\OnSiteEventInboxSchema::statements() as $statement) $db->exec($statement);
$assignment = new \Brebo\Office\Inzet\OnSiteOfficeTeamAssignmentRepository(
  $db, static fn(string $project, string $zone): bool => $project === '17' && $zone === '8'
);
$ingest = new \Brebo\Office\Inzet\OnSiteAutomaticIngest(
  new \Brebo\Office\Inzet\OnSiteAutomaticEventValidator(),
  new \Brebo\Office\Inzet\OnSiteAssignmentGate($assignment),
  new \Brebo\Office\Inzet\OnSiteEventInbox($db)
);
$event = ['project_id'=>'17','zone_id'=>'8','kind'=>'in','occurred_at'=>'2026-10-19T08:00:00+02:00'];
$id = 'event_20261019_00000001';
$first = $ingest->ingest('101',$id,$event);
$second = $ingest->ingest('101',$id,$event);
if ($first !== ['accepted'=>true,'duplicate'=>false] || $second !== ['accepted'=>true,'duplicate'=>true]) {
  throw new RuntimeException('Idempotency failed');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_onsite_event_inbox')->fetchColumn() !== 1) {
  throw new RuntimeException('Duplicate event was persisted');
}
foreach ([
  ['102',$event],
  ['101',array_replace($event,['zone_id'=>'9'])],
  ['101',array_replace($event,['employee_id'=>'101'])],
] as [$employee,$payload]) {
  try {
    $ingest->ingest($employee,'event_20261019_00000002',$payload);
    throw new RuntimeException('Unauthorized event accepted');
  } catch (InvalidArgumentException $expected) {}
}
if ((int)$db->query('SELECT COUNT(*) FROM office_onsite_event_inbox')->fetchColumn() !== 1) {
  throw new RuntimeException('Rejected event altered inbox');
}
echo "OnSite MySQL inbox idempotency and authorization OK\n";
