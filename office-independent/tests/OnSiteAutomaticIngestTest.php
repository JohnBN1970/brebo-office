<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteEventInbox.php';
require_once __DIR__ . '/../src/Inzet/OnSiteAutomaticEventValidator.php';
require_once __DIR__ . '/../src/Inzet/OnSiteAutomaticIngest.php';

use Brebo\Office\Inzet\OnSiteEventInbox;
use Brebo\Office\Inzet\OnSiteAutomaticIngest;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$inbox = new OnSiteEventInbox(new PDO('sqlite::memory:'));
$inbox->migrate();
$service = new OnSiteAutomaticIngest($inbox);
$event = [
  'event_id'=>'123e4567-e89b-12d3-a456-426614174000',
  'project_id'=>'42',
  'zone_id'=>'7',
  'kind'=>'in',
  'occurred_at'=>'2026-10-18T11:59:00+00:00',
];
$now = new DateTimeImmutable('2026-10-18T12:00:00+00:00');
$allow = static fn(string $employee, string $project, string $zone): bool =>
  $employee === 'employee-1' && $project === '42' && $zone === '7';
$first = $service->accept($event, 'employee-1', $allow, $now);
$retry = $service->accept($event, 'employee-1', $allow, $now);
if (!$first['accepted'] || $retry['accepted'] || count($inbox->eventsForEmployee('employee-1')) !== 1) {
  throw new RuntimeException('Automatic ingest idempotency failed');
}
$denied = false;
try {
  $service->accept($event, 'employee-2', $allow, $now);
} catch (InvalidArgumentException) {
  $denied = true;
}
if (!$denied) {
  throw new RuntimeException('Unauthorized assignment accepted');
}
echo "OnSiteAutomaticIngestTest OK\n";
