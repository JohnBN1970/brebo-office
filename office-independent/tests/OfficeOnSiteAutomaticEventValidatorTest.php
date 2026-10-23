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

$receipt=new DateTimeImmutable('2026-10-23T09:00:00Z');
$live=new \Brebo\Office\Inzet\OnSiteAutomaticEventValidator(static fn():DateTimeImmutable=>$receipt);
$base=['project_id'=>'17','zone_id'=>'8','kind'=>'in'];
foreach (['2026-10-23T09:05:00Z','2026-10-16T09:00:00Z'] as $at) {
  $live->validate($base+['occurred_at'=>$at]);
}
foreach (['2026-10-23T09:05:01Z','2026-10-16T08:59:59Z'] as $at) {
  try {$live->validate($base+['occurred_at'=>$at]);throw new RuntimeException('Outside receipt window accepted');}
  catch (InvalidArgumentException $expected) {}
}
$application=file_get_contents(dirname(__DIR__).'/src/Inzet/OnSitePresenceApplication.php');
if (!str_contains($application,"new \\DateTimeImmutable('now', new \\DateTimeZone('UTC'))")) throw new RuntimeException('Live application lacks trusted UTC receipt clock');
echo "Observed seven-day/+five-minute delivery window preserved in live application\n";
