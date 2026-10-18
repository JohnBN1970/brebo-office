<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSitePresenceReconciler.php';
use Brebo\Office\Inzet\OnSitePresenceReconciler;

$base = ['employee_id'=>'E1', 'project_id'=>'P1', 'zone_id'=>'Z1'];
$entry = array_merge($base, [
  'event_id'=>'entry', 'kind'=>'in', 'occurred_at'=>'2026-10-18T08:00:00+02:00'
]);
$exit = array_merge($base, [
  'event_id'=>'exit', 'kind'=>'out', 'occurred_at'=>'2026-10-18T07:00:00+00:00'
]);
$result = OnSitePresenceReconciler::reconcile([$exit, $entry, $entry]);
if (count($result['sessions']) !== 1 ||
    $result['sessions'][0]['duration_seconds'] !== 3600 ||
    count($result['exceptions']) !== 0) {
  throw new RuntimeException('Timezone and duplicate event reconciliation failed');
}
echo "OnSitePresenceTimezoneTest OK\n";
