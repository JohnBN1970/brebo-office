<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteAutomaticEventValidator.php';
use Brebo\Office\Inzet\OnSiteAutomaticEventValidator;

$now = new DateTimeImmutable('2026-10-18T12:00:00+00:00');
$event = [
 'event_id'=>'123e4567-e89b-12d3-a456-426614174000',
 'project_id'=>'42', 'zone_id'=>'7', 'kind'=>'in',
 'occurred_at'=>'2026-10-18T11:59:00+00:00',
];
$result = OnSiteAutomaticEventValidator::validate($event,$now);
if ($result['project_id'] !== '42') throw new RuntimeException('Valid event rejected');
foreach ([
 array_merge($event,['kind'=>'pause']),
 array_merge($event,['occurred_at'=>'2026-10-01T10:00:00+00:00']),
 array_merge($event,['latitude'=>51.0]),
 array_merge($event,['zone_id'=>'0']),
 array_merge($event,['event_id'=>'123e4567-e89b-12d3-a456-42661417400x']),
 array_merge($event,['occurred_at'=>'2026-10-18T11:59:00']),
] as $bad) {
 $rejected = false;
 try { OnSiteAutomaticEventValidator::validate($bad,$now); }
 catch (InvalidArgumentException) { $rejected = true; }
 if (!$rejected) throw new RuntimeException('Invalid event accepted');
}
echo "OnSiteAutomaticEventValidatorTest OK\n";
