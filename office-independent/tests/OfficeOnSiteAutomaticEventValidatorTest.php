<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAutomaticEventValidator.php';
use Brebo\Office\Inzet\OnSiteAutomaticEventValidator;
$validator = new OnSiteAutomaticEventValidator();
$valid = ['project_id'=>'12','zone_id'=>'zone_1','kind'=>'in','occurred_at'=>'2026-10-19T08:00:00+02:00'];
$result = $validator->validate($valid);
if ($result['occurred_at'] !== '2026-10-19T06:00:00.000000Z') {
  throw new RuntimeException('UTC normalization failed');
}
foreach ([
  ['kind'=>'pause'],
  ['employee_id'=>'spoofed'],
  ['latitude'=>51.0],
  ['occurred_at'=>'2026-99-99T08:00:00Z'],
  ['zone_id'=>'../../other'],
  ['project_id'=>''],
] as $override) {
  try {
    $validator->validate(array_replace($valid, $override));
    throw new RuntimeException('Rejected event accepted: ' . json_encode($override));
  } catch (InvalidArgumentException) {
    // Expected.
  }
}
echo "OnSite automatic transition validation passed\n";
