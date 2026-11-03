<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Inspection/OfficeInspectionReportConfiguration.php';

use Brebo\Office\Inspection\OfficeInspectionReportConfiguration;

$default = OfficeInspectionReportConfiguration::normalize([]);
if ($default['report_type'] !== 'expertise' || !in_array('findings',$default['sections'],true)) throw new RuntimeException('default_failed');
foreach ([
  ['report_type'=>'other'],
  ['sections'=>['cover']],
  ['sections'=>['findings','findings']],
  ['sections'=>['findings','unknown']],
  ['notes'=>str_repeat('x',4001)]
] as $bad) {
  try { OfficeInspectionReportConfiguration::normalize($bad); throw new RuntimeException('invalid_input_accepted'); }
  catch (InvalidArgumentException $expected) {}
}
$selected = OfficeInspectionReportConfiguration::normalize(['report_type'=>'damage','sections'=>['findings','photos'],'notes'=>'  focus  ']);
if ($selected['sections'] !== ['findings','photos'] || $selected['notes'] !== 'focus') throw new RuntimeException('selection_failed');
echo "OfficeInspectionReportConfigurationTest OK\n";
