<?php
declare(strict_types=1);
foreach (['OnSiteAssignmentStore','OnSiteEventInbox','OnSiteAutomaticEventValidator','OnSiteAutomaticIngest','OnSitePresenceReconciler','OnSiteOfficeGateway'] as $class) {
  require_once __DIR__.'/../src/Inzet/'.$class.'.php';
}
use Brebo\Office\Inzet\{OnSiteAssignmentStore,OnSiteEventInbox,OnSiteAutomaticIngest,OnSiteOfficeGateway};
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$assignments = new OnSiteAssignmentStore($db);
$assignments->migrate();
$inbox = new OnSiteEventInbox($db);
$inbox->migrate();
$db->exec("INSERT INTO onsite_projects VALUES ('42','Testproject')");
$db->exec("INSERT INTO onsite_zones VALUES ('7','42','Werkzone',51.4,4.5,100,1)");
$db->exec("INSERT INTO onsite_assignments VALUES ('employee-1','42','2026-10-18','2026-10-19')");
$gateway = new OnSiteOfficeGateway($assignments,new OnSiteAutomaticIngest($inbox),$inbox);
$bootstrap = $gateway->bootstrap('employee-1','Test','nl','2026-10-18');
if (count($bootstrap['projects']) !== 1 || count($bootstrap['projects'][0]['zones']) !== 1) {
  throw new RuntimeException('Bootstrap does not return assigned zones');
}
$now = new DateTimeImmutable('2026-10-18T12:00:00+02:00');
$payload = [
  'trigger'=>'automatic_geofence','event_id'=>'123e4567-e89b-12d3-a456-426614174000',
  'project_id'=>'42','zone_id'=>'7','kind'=>'in',
  'occurred_at'=>'2026-10-18T09:00:00+02:00',
];
if (!$gateway->presence($payload,'employee-1',$now)['accepted']) throw new RuntimeException('Event not accepted');
if ($gateway->presence($payload,'employee-1',$now)['accepted']) throw new RuntimeException('Retry inserted twice');
$exit = $payload;
$exit['event_id']='123e4567-e89b-12d3-a456-426614174001';
$exit['kind']='out';
$exit['occurred_at']='2026-10-18T10:00:00+02:00';
$gateway->presence($exit,'employee-1',$now);
$review = $gateway->review('employee-1');
if (count($review['sessions']) !== 1 || $review['sessions'][0]['state'] !== 'pending_review'
  || $review['sessions'][0]['duration_seconds'] !== 3600) {
  throw new RuntimeException('Presence review must remain unapproved');
}
try {
  $gateway->presence($payload,'employee-2',$now);
  throw new RuntimeException('Unauthorized employee accepted');
} catch (InvalidArgumentException $e) {
  if ($e->getMessage() === 'Unauthorized employee accepted') throw $e;
}
echo "OnSiteOfficeGatewayTest OK\n";
