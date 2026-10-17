<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReview.php';

use Brebo\Office\Inzet\OnSiteHoursReview;

$review = new OnSiteHoursReview();
$session = ['in_event_id'=>'event-in','out_event_id'=>'event-out',
  'duration_seconds'=>28800,'approval_status'=>'pending'];
$approved = $review->decide($session, 'approve');
if ($approved['status'] !== 'approved' || $approved['reviewed_seconds'] !== 28800) throw new RuntimeException('Approval failed.');
$corrected = $review->decide($session, 'approve', 27000, 'Pauze gecorrigeerd');
if ($corrected['reviewed_seconds'] !== 27000 || $corrected['reason'] === null) throw new RuntimeException('Correction failed.');
$rejected = $review->decide($session, 'reject', null, 'Niet gewerkt');
if ($rejected['status'] !== 'rejected' || $rejected['reviewed_seconds'] !== 0) throw new RuntimeException('Rejection failed.');
foreach ([
  [$session, 'reject', null, null],
  [$session, 'approve', 27000, null],
  [array_replace($session,['approval_status'=>'approved']), 'approve', null, null],
  [array_replace($session,['out_event_id'=>null]), 'approve', null, null],
] as [$candidate,$action,$seconds,$reason]) {
  try {
    $review->decide($candidate, $action, $seconds, $reason);
    throw new RuntimeException('Invalid review accepted.');
  } catch (InvalidArgumentException|DomainException $expected) {
  }
}
echo "OnSite hours review OK\n";
