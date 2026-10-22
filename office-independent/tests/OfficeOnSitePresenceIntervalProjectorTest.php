<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OnSitePresenceIntervalProjector.php';
$projector = new \Brebo\Office\Inzet\OnSitePresenceIntervalProjector();
$event = static fn(string $id,string $kind,string $time,string $zone='7'):array => [
  'event_id'=>$id,'person_id'=>'101','project_id'=>'17','zone_id'=>$zone,
  'event_kind'=>$kind,'occurred_at_utc'=>$time
];
$result=$projector->project([
  $event('arrival_0000000001','in','2026-10-22T08:00:00Z'),
  $event('arrival_0000000002','in','2026-10-22T08:05:00Z'),
  $event('departure_0000001','out','2026-10-22T09:00:00Z'),
  $event('departure_0000002','out','2026-10-22T09:10:00Z'),
  $event('arrival_0000000003','in','2026-10-22T10:00:00Z'),
]);
if (count($result['intervals'])!==1 || $result['intervals'][0]['elapsed_seconds']!==3600
  || $result['intervals'][0]['status']!=='evidence_only') throw new RuntimeException('Presence interval mismatch');
$reasons=array_column($result['exceptions'],'reason');
sort($reasons);
if ($reasons!==['departure_without_arrival','missing_departure','overlapping_arrival'])
  throw new RuntimeException('Presence exceptions mismatch');
$mismatch=$projector->project([
  $event('arrival_0000000004','in','2026-10-22T08:00:00Z'),
  $event('departure_0000003','out','2026-10-22T09:00:00Z','8'),
]);
if (count($mismatch['intervals'])!==0 || !in_array('zone_mismatch',array_column($mismatch['exceptions'],'reason'),true))
  throw new RuntimeException('Zone mismatch incorrectly paired');
echo "OnSite evidence interval projection OK\n";
