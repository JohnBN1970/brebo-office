<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursReconciliationPolicy.php';
use Brebo\Office\Inzet\OfficeExternalHoursReconciliationPolicy as Policy;

$case=Policy::compare(21600,28800,28800);
if ($case['present_seconds']!==21600 || $case['presence_variance_seconds']!==7200 || $case['status']!=='review_required') {
    throw new RuntimeException('6h presence vs 8h claim must require review');
}
$approved=Policy::compare(21600,28800,28800,28800);
if ($approved['present_seconds']!==21600 || $approved['approved_payable_seconds']!==28800 || $approved['disputed_seconds']!==0) {
    throw new RuntimeException('8h payable must never alter 6h presence');
}
$partial=Policy::compare(21600,28800,28800,21600);
if ($partial['disputed_seconds']!==7200 || $partial['status']!=='reviewed') {
    throw new RuntimeException('Rejected 2h must remain disputed');
}
$missing=Policy::compare(21600,28800,null);
if ($missing['status']!=='awaiting_invoice') throw new RuntimeException('Missing invoice not handled');
try {
    Policy::compare(21600,28800,28800,32400);
    throw new RuntimeException('Overapproval incorrectly permitted');
} catch (DomainException $expected) {}
echo "EXTERNAL_HOURS_RECONCILIATION_POLICY_OK\n";
