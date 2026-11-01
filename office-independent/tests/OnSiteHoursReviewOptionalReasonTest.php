<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReview.php';

use Brebo\Office\Inzet\OnSiteHoursReview;

$review = new OnSiteHoursReview();
$session = [
  'approval_status' => 'pending',
  'in_event_id' => 'arrival-1',
  'out_event_id' => 'departure-1',
  'duration_seconds' => 3600,
];

$approved = $review->decide($session, 'approve', 1800, null);
if ($approved !== ['status'=>'approved','reviewed_seconds'=>1800,'reason'=>null]) {
  throw new RuntimeException('Correction without reason must be accepted.');
}
$rejected = $review->decide($session, 'reject', null, null);
if ($rejected !== ['status'=>'rejected','reviewed_seconds'=>0,'reason'=>null]) {
  throw new RuntimeException('Rejection without reason must be accepted.');
}
$explained = $review->decide($session, 'approve', null, ' gecontroleerd ');
if ($explained['reason'] !== 'gecontroleerd') {
  throw new RuntimeException('Optional reason should be trimmed.');
}
try {
  $review->decide($session + ['unused'=>true], 'approve', 90000, null);
  throw new RuntimeException('Invalid correction was accepted.');
} catch (InvalidArgumentException $expected) {
  if ($expected->getMessage() === 'Invalid correction was accepted.') throw $expected;
}
echo "OnSite review optional reason: PASS\n";
