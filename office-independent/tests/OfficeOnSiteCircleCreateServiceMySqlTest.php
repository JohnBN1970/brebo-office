<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Inzet\OnSiteOfficeCircleCreateService;
use Brebo\Office\Inzet\OnSiteCanonicalZoneSchema;
use Brebo\Office\Inzet\OnSiteCanonicalZoneRepository;

require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
foreach (['OnSiteOfficeCircleInput', 'OnSiteOfficeProjectMapSeed', 'OnSiteCanonicalZoneSync',
  'OnSiteCanonicalZoneSchema', 'OnSiteCanonicalZoneRepository', 'OnSiteOfficeZoneCreator',
  'OnSiteOfficeCircleCreateService'] as $class) {
  require_once dirname(__DIR__) . '/src/Inzet/' . $class . '.php';
}
$dsn = getenv('OFFICE_INZET_TEST_DSN') ?: '';
if (!str_contains($dsn, 'dbname=office_inzet_ci')) throw new RuntimeException('Dedicated CI database required');
$db = new PDO($dsn, getenv('OFFICE_INZET_TEST_USER') ?: '', getenv('OFFICE_INZET_TEST_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
// Temporary tables shadow other CI fixtures and disappear when this connection closes.
foreach (OnSiteCanonicalZoneSchema::statements() as $sql) {
  $db->exec(str_replace('CREATE TABLE ', 'CREATE TEMPORARY TABLE ', $sql));
}
$db->exec('CREATE TEMPORARY TABLE office_projects (id BIGINT PRIMARY KEY, latitude DECIMAL(10,7), longitude DECIMAL(10,7)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id BIGINT PRIMARY KEY, active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (project_id BIGINT, operator_id BIGINT, permission VARCHAR(20), PRIMARY KEY(project_id, operator_id)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_projects VALUES (17,51.5,4.3),(18,52,5),(19,NULL,NULL)");
$db->exec("INSERT INTO office_operator_accounts VALUES (10,1),(11,0),(12,1)");
$db->exec("INSERT INTO office_project_memberships VALUES (17,10,'manage'),(18,10,'read'),(17,11,'manage'),(19,10,'manage')");
$service = new OnSiteOfficeCircleCreateService($db);
$operator = static fn (string $id) => new WebsiteCrmOperatorContext($id, str_repeat('a', 64), false);
$input = ['project_id'=>'17', 'zone_name'=>'Fictieve werkzone', 'latitude'=>51.6, 'longitude'=>4.4, 'radius_metres'=>150];
$count = static fn (): int => (int)$db->query('SELECT COUNT(*) FROM office_onsite_canonical_zones')->fetchColumn();
$identities = static fn (): int => (int)$db->query('SELECT COUNT(*) FROM office_onsite_zone_identity')->fetchColumn();
$deny = static function (Closure $attempt, string $message) use ($db, $count, $identities): void {
  $before = [$count(), $identities()];
  try { $attempt(); } catch (InvalidArgumentException | RuntimeException $exception) {
    if ($exception->getMessage() !== $message) throw $exception;
    if ([$count(), $identities()] !== $before || $db->inTransaction()) {
      throw new RuntimeException('Rejected request changed storage or leaked transaction');
    }
    return;
  }
  throw new RuntimeException('Expected rejection: ' . $message);
};
$id = $service->create($operator('10'), '17', $input);
$check = (new OnSiteCanonicalZoneRepository($db))->callback();
if (!$check('17', $id) || $check('18', $id) || $count() !== 1 || $db->inTransaction()) {
  throw new RuntimeException('Created circle not available through canonical zone reader');
}
$stored = $db->query('SELECT * FROM office_onsite_canonical_zones')->fetch(PDO::FETCH_ASSOC);
if ($stored['zone_name'] !== $input['zone_name'] || (int)$stored['source_revision'] !== 1
  || abs((float)$stored['latitude'] - 51.6) > 0.0000001 || $stored['building_id'] !== null) {
  throw new RuntimeException('Office geometry or database-owned identity incorrect');
}
foreach (['11', '12', 'not-an-id'] as $actor) {
  $deny(fn () => $service->create($operator($actor), '17', $input),
    $actor === 'not-an-id' ? 'office_project_map_access_denied' : 'unauthorized_office_project');
}
foreach (['18', '999'] as $project) {
  $deny(fn () => $service->create($operator('10'), $project, array_replace($input, ['project_id'=>$project])), 'unauthorized_office_project');
}
$deny(fn () => $service->create($operator('10'), '19', array_replace($input, ['project_id'=>'19'])), 'office_project_map_seed_unavailable');
$deny(fn () => $service->create($operator('10'), '18', $input), 'office_circle_project_not_authorized');
foreach (['operator_id'=>'10', 'zone_id'=>'123', 'source_revision'=>1, 'expected_revision'=>0, 'building_id'=>'1', 'active'=>true] as $key=>$value) {
  $deny(fn () => $service->create($operator('10'), '17', $input + [$key=>$value]), 'office_circle_unknown_fields');
}
foreach (['latitude'=>91, 'longitude'=>181, 'radius_metres'=>0, 'zone_name'=>' '] as $key=>$value) {
  $deny(fn () => $service->create($operator('10'), '17', array_replace($input, [$key=>$value])), 'invalid_office_circle');
}
// Recheck current rights for every command, even with the same session context.
$db->exec("UPDATE office_project_memberships SET permission='read' WHERE operator_id=10 AND project_id=17");
$deny(fn () => $service->create($operator('10'), '17', $input), 'unauthorized_office_project');
$db->exec("UPDATE office_project_memberships SET permission='manage' WHERE operator_id=10 AND project_id=17");
// Failure after identity allocation must roll back both the ID row and the zone.
$db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
$db->exec('ALTER TABLE office_onsite_canonical_zones MODIFY zone_name VARCHAR(20) NULL');
$before = [$count(), $identities()];
$failed = false;
try { $service->create($operator('10'), '17', array_replace($input, ['zone_name'=>str_repeat('x', 30)])); }
catch (PDOException $exception) { $failed = true; }
if (!$failed || $db->inTransaction() || [$count(), $identities()] !== $before) {
  throw new RuntimeException('Failed insert not rolled back atomically');
}
// Caller transactions must be preserved, never committed or rolled back by the service.
$db->beginTransaction();
$nested = false;
try { $service->create($operator('10'), '17', $input); }
catch (RuntimeException $exception) { $nested = $exception->getMessage() === 'office_zone_nested_transaction'; }
if (!$nested || !$db->inTransaction()) throw new RuntimeException('Caller transaction was altered');
$db->rollBack();
// Native writes retain the existing fail-closed freshness policy.
$db->exec('UPDATE office_onsite_canonical_zones SET synchronized_at=UTC_TIMESTAMP()-INTERVAL 25 HOUR');
if ($check('17', $id)) throw new RuntimeException('Office-native write bypassed zone TTL');
echo "Office circle creation authorization, reader, rollback and TTL checks passed\n";
