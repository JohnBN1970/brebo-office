<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReview.php';

use Brebo\Office\Inzet\OnSiteHoursReview;

$review = new OnSiteHoursReview();
$session = [
  'approval_status'=>'pending',
  'in_event_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'out_event_id'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'duration_seconds'=>3600,
];
$approved = $review->decide($session, 'approve');
if ($approved['status'] !== 'approved' || $approved['reviewed_seconds'] !== 3600) {
  throw new RuntimeException('Complete session approval failed');
}
$corrected = $review->decide($session, 'approve', 3300, 'Break');
if ($corrected['reviewed_seconds'] !== 3300) throw new RuntimeException('Correction failed');
$rejected = $review->decide($session, 'reject', null, 'Incorrect geofence');
if ($rejected['status'] !== 'rejected') throw new RuntimeException('Rejection failed');
foreach ([
  fn() => $review->decide(array_merge($session, ['approval_status'=>'approved']), 'approve'),
  fn() => $review->decide(array_merge($session, ['out_event_id'=>null]), 'approve'),
  fn() => $review->decide($session, 'reject'),
  fn() => $review->decide($session, 'approve', 1200),
] as $invalid) {
  try {
    $invalid();
  } catch (\DomainException | \InvalidArgumentException $expected) {
    continue;
  }
  throw new RuntimeException('Invalid review was accepted');
}
echo "Office native hours review checks passed\n";
