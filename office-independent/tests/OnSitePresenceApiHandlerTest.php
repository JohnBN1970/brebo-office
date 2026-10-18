<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteEventInbox.php';
require_once __DIR__ . '/../src/Inzet/OnSiteAutomaticEventValidator.php';
require_once __DIR__ . '/../src/Inzet/OnSiteAutomaticIngest.php';
require_once __DIR__ . '/../src/Inzet/OnSitePresenceApiHandler.php';

use Brebo\Office\Inzet\OnSiteEventInbox;
use Brebo\Office\Inzet\OnSiteAutomaticIngest;
use Brebo\Office\Inzet\OnSitePresenceApiHandler;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$inbox = new OnSiteEventInbox(new PDO('sqlite::memory:'));
$inbox->migrate();
$handler = new OnSitePresenceApiHandler(new OnSiteAutomaticIngest($inbox));
$event = [
  'event_id' => '123e4567-e89b-12d3-a456-426614174001',
  'trigger' => 'automatic_geofence',
  'project_id' => '42',
  'zone_id' => '7',
  'kind' => 'in',
  'occurred_at' => '2026-10-18T11:59:00+00:00',
];
$now = new DateTimeImmutable('2026-10-18T12:00:00+00:00');
$allow = static fn(string $employee, string $project, string $zone, string $occurred): bool =>
  $employee === 'employee-1' && $project === '42' && $zone === '7';
$missing = $handler->handle($event, null, $allow, $now);
if ($missing['status'] !== 401) throw new RuntimeException('Unlinked device accepted');
$wrongTrigger = $handler->handle([...$event, 'trigger' => 'clock_action'], 'employee-1', $allow, $now);
if ($wrongTrigger['status'] !== 400) throw new RuntimeException('Manual trigger accepted');
$first = $handler->handle($event, 'employee-1', $allow, $now);
$retry = $handler->handle($event, 'employee-1', $allow, $now);
if ($first['status'] !== 201 || $retry['status'] !== 200 || ($retry['body']['duplicate'] ?? false) !== true) {
  throw new RuntimeException('Idempotent API acknowledgment failed');
}
if (count($inbox->eventsForEmployee('employee-1')) !== 1) throw new RuntimeException('Duplicate event stored');
echo "OnSitePresenceApiHandlerTest OK\n";
