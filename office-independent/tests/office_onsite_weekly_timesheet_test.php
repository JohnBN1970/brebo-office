<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteWeeklyTimesheetControl.php';

use Brebo\Office\Inzet\OnSiteWeeklyTimesheetControl;

$control = new OnSiteWeeklyTimesheetControl();
$approved = [
  ['person_id'=>7,'project_id'=>12,'date'=>'2026-10-12','minutes'=>480],
  ['person_id'=>7,'project_id'=>12,'date'=>'2026-10-13','minutes'=>480],
];
$balanced = [
  ['person_id'=>7,'project_id'=>12,'date'=>'2026-10-12','minutes'=>600],
  ['person_id'=>7,'project_id'=>12,'date'=>'2026-10-13','minutes'=>360],
];
$result = $control->compare($approved,$balanced);
if ($result['status'] !== 'requires_review' || $result['approved_minutes'] !== $result['supplier_minutes']) {
  throw new RuntimeException('Offsetting daily discrepancies not detected.');
}
if ($result['rows'][0]['difference_minutes'] !== 120 || $result['rows'][1]['difference_minutes'] !== -120) {
  throw new RuntimeException('Daily differences incorrect.');
}
if ($control->compare($approved,$approved)['status'] !== 'hours_match') throw new RuntimeException('Matching rows rejected.');
if ($control->compare([],[])['status'] !== 'no_evidence') throw new RuntimeException('Missing evidence accepted.');
try {
  $control->compare([], [['person_id'=>7,'project_id'=>12,'date'=>'2026-02-30','minutes'=>480]]);
  throw new RuntimeException('Invalid date accepted.');
} catch (InvalidArgumentException $expected) {}
echo "OnSite weekly timesheet control OK\n";
