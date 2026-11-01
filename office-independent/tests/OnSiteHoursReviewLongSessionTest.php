<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReview.php';
$review = new \Brebo\Office\Inzet\OnSiteHoursReview();
$session = ['approval_status'=>'pending','in_event_id'=>'in-1','out_event_id'=>'out-1','duration_seconds'=>504000];
foreach ([[null,null],[3600,null]] as [$seconds,$reason]) {
  try { $review->decide($session,'approve',$seconds,$reason); }
  catch (DomainException $expected) { continue; }
  throw new RuntimeException('Long session approved without documented correction.');
}
$accepted = $review->decide($session,'approve',3600,'Geofence niet uitgeklokt');
if ($accepted['status'] !== 'approved' || $accepted['reviewed_seconds'] !== 3600) throw new RuntimeException('Corrected review failed.');
$rejected = $review->decide($session,'reject');
if ($rejected['status'] !== 'rejected' || $rejected['reviewed_seconds'] !== 0) throw new RuntimeException('Long session rejection failed.');
echo "OnSiteHoursReviewLongSessionTest OK\n";
