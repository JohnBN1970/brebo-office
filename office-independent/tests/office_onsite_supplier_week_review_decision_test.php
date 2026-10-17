<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekReviewDecision.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekReviewDecision;

$service = new OnSiteSupplierWeekReviewDecision();
$hash = str_repeat('a',64);
$ok = $service->decide(['supplier_id'=>3,'can_approve'=>true],7,'approve','',$hash);
if ($ok['review_status'] !== 'approved' || $ok['recorded'] || $ok['invoice_eligible']
  || $ok['source_sha256'] !== $hash) throw new RuntimeException('Approval record must be pending persistence.');
try {
  $service->decide(['supplier_id'=>3,'can_approve'=>false],7,'approve','',$hash);
  throw new RuntimeException('Unresolved issues approved.');
} catch (DomainException $expected) {}
try {
  $service->decide(['supplier_id'=>3,'can_approve'=>false],7,'reject','',$hash);
  throw new RuntimeException('Missing rejection reason accepted.');
} catch (InvalidArgumentException $expected) {}
$correction = $service->decide(['supplier_id'=>3,'can_approve'=>false],7,'request_correction','Uren toelichten',$hash);
if ($correction['review_status'] !== 'correction_requested') throw new RuntimeException('Correction request lost.');
echo "OnSite supplier week review decision OK\n";
