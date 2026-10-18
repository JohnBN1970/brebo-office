<?php
declare(strict_types=1);
foreach ([
  'OnSiteEventInbox.php',
  'OnSiteAutomaticEventValidator.php',
  'OnSiteAutomaticIngest.php',
  'OnSitePresenceApiHandler.php',
  'OnSiteDeviceTokenRepository.php',
  'OnSitePresenceRequestService.php',
] as $file) require_once __DIR__ . '/../src/Inzet/' . $file;

use Brebo\Office\Inzet\OnSiteEventInbox;
use Brebo\Office\Inzet\OnSiteAutomaticIngest;
use Brebo\Office\Inzet\OnSitePresenceApiHandler;
use Brebo\Office\Inzet\OnSiteDeviceTokenRepository;
use Brebo\Office\Inzet\OnSitePresenceRequestService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$devices = new OnSiteDeviceTokenRepository($db);
$devices->migrate();
$inbox = new OnSiteEventInbox($db);
$inbox->migrate();
$service = new OnSitePresenceRequestService($devices, new OnSitePresenceApiHandler(new OnSiteAutomaticIngest($inbox)));
$token = str_repeat('t', 40);
$devices->register($token, 'john.boon');
$event = [
  'event_id' => '123e4567-e89b-12d3-a456-426614174004',
  'trigger' => 'automatic_geofence',
  'project_id' => '42',
  'zone_id' => '7',
  'kind' => 'in',
  'occurred_at' => '2026-10-18T11:59:00+00:00',
];
$now = new DateTimeImmutable('2026-10-18T12:00:00+00:00');
$allow = static fn(string $employee, string $project, string $zone, string $at): bool =>
  $employee === 'john.boon' && $project === '42' && $zone === '7';
$unknown = $service->submit($event, str_repeat('z', 40), $allow, $now);
if ($unknown['status'] !== 401) throw new RuntimeException('Unknown device accepted');
$accepted = $service->submit($event, $token, $allow, $now);
if ($accepted['status'] !== 201) throw new RuntimeException('Verified device rejected');
$retry = $service->submit($event, $token, $allow, $now);
if ($retry['status'] !== 200 || ($retry['body']['duplicate'] ?? null) !== true) {
  throw new RuntimeException('Retry not acknowledged');
}
$devices->revoke($token);
$revoked = $service->submit($event, $token, $allow, $now);
if ($revoked['status'] !== 401) throw new RuntimeException('Revoked device accepted');
if (count($inbox->eventsForEmployee('john.boon')) !== 1) throw new RuntimeException('Unexpected event count');
echo "OnSitePresenceRequestServiceTest OK\n";
