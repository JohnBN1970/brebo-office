<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteEventInbox.php';
use Brebo\Office\Inzet\OnSiteEventInbox;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: sqlite driver unavailable\n";
  exit(0);
}
$inbox = new OnSiteEventInbox(new PDO('sqlite::memory:'));
$inbox->migrate();
$event = [
  'event_id' => 'evt-1',
  'employee_id' => 'employee-1',
  'project_id' => 'project-1',
  'zone_id' => 'zone-1',
  'kind' => 'in',
  'occurred_at' => '2026-10-18T07:00:00+02:00',
];
if ($inbox->receive($event) !== true) throw new RuntimeException('First event not stored');
if ($inbox->receive($event) !== false) throw new RuntimeException('Duplicate event stored');
if (count($inbox->eventsForEmployee('employee-1')) !== 1) throw new RuntimeException('Unexpected event count');
$conflict = $event;
$conflict['kind'] = 'out';
$rejected = false;
try {
  $inbox->receive($conflict);
} catch (InvalidArgumentException) {
  $rejected = true;
}
if (!$rejected) {
  throw new RuntimeException('Conflicting event retry must be rejected');
}
echo "OnSiteEventInboxTest OK\n";
