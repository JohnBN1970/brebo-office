<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Isolated MySQL test DSN required');
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$sql = file_get_contents(__DIR__ . '/../deployment/hostinger/sql/onsite-presence-mysql.sql');
if ($sql === false) throw new RuntimeException('Missing MySQL migration');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (explode(';', $sql) as $statement) {
  if (trim($statement) !== '') $db->exec($statement);
}
// Idempotent migration; explicitly isolated test database only.
foreach (explode(';', $sql) as $statement) {
  if (trim($statement) !== '') $db->exec($statement);
}
foreach (['onsite_device_tokens','onsite_project_zone_assignments','onsite_event_inbox'] as $table) {
  $check = $db->query("SHOW TABLES LIKE " . $db->quote($table))->fetchColumn();
  if ($check !== $table) throw new RuntimeException("Missing $table");
}
require_once __DIR__ . '/../src/Inzet/OnSiteDeviceTokenRepository.php';
require_once __DIR__ . '/../src/Inzet/OnSiteAssignmentRepository.php';
require_once __DIR__ . '/../src/Inzet/OnSiteEventInbox.php';
$devices = new Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db);
$assignments = new Brebo\Office\Inzet\OnSiteAssignmentRepository($db);
$inbox = new Brebo\Office\Inzet\OnSiteEventInbox($db);
$token = str_repeat('m', 40);
$devices->register($token, 'mysql-test-employee');
if ($devices->resolve($token) !== 'mysql-test-employee') throw new RuntimeException('MySQL device resolution failed');
$db->prepare('INSERT INTO onsite_project_zone_assignments (employee_id,project_id,zone_id,valid_from,valid_until,active) VALUES (?,?,?,?,?,?)')
 ->execute(['mysql-test-employee','37','100','2026-01-01T00:00:00+00:00',null,1]);
$db->prepare('INSERT INTO onsite_employees (employee_id,display_name,language,active) VALUES (?,?,?,1)')
 ->execute(['mysql-test-employee','Test medewerker','nl']);
$db->prepare('INSERT INTO onsite_projects (project_id,name,active) VALUES (?,?,1)')
 ->execute(['37','Testproject']);
$db->prepare('INSERT INTO onsite_zones (zone_id,project_id,name,latitude,longitude,radius_metres,active) VALUES (?,?,?,?,?,?,1)')
 ->execute(['100','37','Testzone',51.3,4.4,100]);
require_once __DIR__ . '/../src/Inzet/OnSiteBootstrapRepository.php';
$bootstrap = new Brebo\Office\Inzet\OnSiteBootstrapRepository($db);
$mobile = $bootstrap->forEmployee('mysql-test-employee', new DateTimeImmutable('2026-10-18T09:00:00+00:00'));
if (count($mobile['projects']) !== 1 || count($mobile['projects'][0]['zones']) !== 1)
 throw new RuntimeException('Assigned geofence missing from canonical bootstrap');
if (!$assignments->allows('mysql-test-employee','37','100','2026-10-18T09:00:00+00:00')) throw new RuntimeException('MySQL assignment failed');
$event = ['event_id'=>'bcd26651-9cc9-4c6e-a07d-501cdf4c2bb9','employee_id'=>'mysql-test-employee','project_id'=>'37','zone_id'=>'100','kind'=>'in','occurred_at'=>'2026-10-18T09:00:00+00:00'];
if (!$inbox->receive($event) || $inbox->receive($event)) throw new RuntimeException('MySQL idempotency failed');
if (count($inbox->eventsForEmployee('mysql-test-employee')) !== 1) throw new RuntimeException('MySQL evidence read failed');
$devices->revoke($token);
if ($devices->resolve($token) !== null) throw new RuntimeException('MySQL device revocation failed');
echo "OnSiteMySqlSchemaIntegrationTest OK\n";
