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
assert($inbox->receive($event) === true);
assert($inbox->receive($event) === false);
assert(count($inbox->eventsForEmployee('employee-1')) === 1);
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
