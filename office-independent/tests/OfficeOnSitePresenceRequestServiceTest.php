<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSitePresenceRequestService.php';

$parser = new \Brebo\Office\Inzet\OnSitePresenceRequestService();
$valid = ['event_id'=>'onsite_event_20261019_0001','event'=>[
  'project_id'=>'17','zone_id'=>'8','kind'=>'in','occurred_at'=>'2026-10-19T08:00:00Z'
]];
if ($parser->parse($valid)!==$valid) throw new RuntimeException('Valid envelope changed');
$invalid = [
  array_replace($valid,['event_id'=>'short']),
  array_replace($valid,['employee_id'=>'101']),
  array_replace($valid,['event'=>array_replace($valid['event'],['uid'=>'101'])]),
  array_replace($valid,['event'=>array_replace($valid['event'],['latitude'=>52.1])]),
  array_replace($valid,['event'=>[]]),
];
foreach ($invalid as $case) {
  $rejected = false;
  try { $parser->parse($case); } catch (InvalidArgumentException) { $rejected = true; }
  if (!$rejected) throw new RuntimeException('Unsafe OnSite envelope accepted');
}
echo "OnSite request envelope contract OK\n";
