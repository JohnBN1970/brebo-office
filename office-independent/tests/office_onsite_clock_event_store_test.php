<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteClockEventStore.php';

use Brebo\Office\Inzet\OnSiteClockEventStore;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_inzet_clock_events (
 event_id TEXT PRIMARY KEY, project_id INTEGER NOT NULL, person_id INTEGER NOT NULL,
 action TEXT NOT NULL, occurred_at TEXT NOT NULL, latitude REAL NULL,
 longitude REAL NULL, accuracy_m REAL NULL)');
$store = new OnSiteClockEventStore($db);
$event = ['event_id'=>'onsite_test_00000001','project_id'=>10,'person_id'=>2,
 'action'=>'in','occurred_at'=>'2026-10-16T07:00:00.000000+02:00',
 'latitude'=>51.4,'longitude'=>4.5,'accuracy_m'=>12.0];
if ($store->receive($event) !== 'created') throw new RuntimeException('First receipt failed.');
if ($store->receive($event) !== 'duplicate') throw new RuntimeException('Retry created another record.');
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_clock_events')->fetchColumn() !== 1) throw new RuntimeException('Duplicate row inserted.');
try {
  $store->receive(array_replace($event,['action'=>'out']));
  throw new RuntimeException('Conflicting retry accepted.');
} catch (DomainException $expected) {
}
echo "OnSite clock event idempotency OK\n";
