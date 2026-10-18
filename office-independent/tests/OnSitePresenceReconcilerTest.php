<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Inzet/OnSitePresenceReconciler.php';

use Brebo\Office\Inzet\OnSitePresenceReconciler;

$base = ['employee_id'=>'E1','project_id'=>'P1','zone_id'=>'Z1'];
$in = $base + ['event_id'=>'a','kind'=>'in','occurred_at'=>'2026-10-18T07:00:00+02:00'];
$out = $base + ['event_id'=>'b','kind'=>'out','occurred_at'=>'2026-10-18T15:00:00+02:00'];
$result = OnSitePresenceReconciler::reconcile([$out,$in,$in]);
if (count($result['sessions']) !== 1 || $result['sessions'][0]['duration_seconds'] !== 28800 || $result['exceptions'] !== []) {
  throw new RuntimeException('Expected one eight-hour session and no exceptions');
}
$missing = OnSitePresenceReconciler::reconcile([$in]);
if (count($missing['exceptions']) !== 1 || $missing['exceptions'][0]['reason'] !== 'missing_exit') {
  throw new RuntimeException('Missing exit not detected');
}
$orphan = OnSitePresenceReconciler::reconcile([$out]);
if ($orphan['exceptions'][0]['reason'] !== 'exit_without_entry') {
  throw new RuntimeException('Orphan exit not detected');
}
echo "OnSitePresenceReconcilerTest OK\n";
