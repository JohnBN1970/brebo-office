<?php
declare(strict_types=1);
foreach ([
  'OnSiteDeviceTokenRepository','OnSiteAssignmentRepository',
  'OnSiteAutomaticEventValidator','OnSiteEventInbox','OnSiteAutomaticIngest',
  'OnSitePresenceApiHandler','OnSitePresenceRequestService','OnSitePresenceApplication'
] as $class) require_once __DIR__ . '/../src/Inzet/' . $class . '.php';
use Brebo\Office\Inzet\OnSitePresenceApplication;
use Brebo\Office\Inzet\OnSiteDeviceTokenRepository;
use Brebo\Office\Inzet\OnSiteAssignmentRepository;
use Brebo\Office\Inzet\OnSiteEventInbox;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$devices = new OnSiteDeviceTokenRepository($db);
$devices->migrate();
(new OnSiteAssignmentRepository($db))->migrate();
(new OnSiteEventInbox($db))->migrate();
$token = str_repeat('x', 40);
$devices->register($token, 'employee-1');
$db->prepare('INSERT INTO onsite_project_zone_assignments
 (employee_id,project_id,zone_id,valid_from,valid_until,active) VALUES (?,?,?,?,?,?)')
 ->execute(['employee-1','project-1','zone-1','2026-01-01T00:00:00+00:00',null,1]);
$app = new OnSitePresenceApplication($db);
$payload = [
 'event_id'=>'d26e6961-71f4-48cb-84bd-90b31cc22e39',
 'trigger'=>'automatic_geofence', 'project_id'=>'project-1','zone_id'=>'zone-1',
 'kind'=>'in','occurred_at'=>'2026-10-18T08:00:00+00:00'
];
$now = new DateTimeImmutable('2026-10-18T08:01:00+00:00');
foreach ([
  [$payload, null, 401],
  [$payload, str_repeat('z',40), 401],
  [$payload, $token, 201],
  [$payload, $token, 200],
  [array_replace($payload,['zone_id'=>'zone-2','event_id'=>'67f8b5f3-9957-42fd-8fb6-501c9621dcee']), $token, 400],
] as [$event,$bearer,$status]) {
  $actual = $app->submit($event,$bearer,$now);
  if ($actual['status'] !== $status) throw new RuntimeException('Unexpected HTTP status: ' . json_encode($actual));
}
$devices->revoke($token);
if ($app->submit($payload,$token,$now)['status'] !== 401) throw new RuntimeException('Revoked device accepted');
if ((int)$db->query('SELECT COUNT(*) FROM onsite_event_inbox')->fetchColumn() !== 1) throw new RuntimeException('Unexpected evidence count');
echo "OnSitePresenceApplicationTest OK\n";
