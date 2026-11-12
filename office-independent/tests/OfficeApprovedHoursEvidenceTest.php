<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeApprovedHoursEvidence.php';

use Brebo\Office\Inzet\OfficeApprovedHoursEvidence;

$session = [
    'project_id'=>42,'person_id'=>5,'work_date'=>'2026-11-12',
    'in_event_id'=>'in-1','out_event_id'=>'out-1',
];
$decision = [
    'status'=>'approved','persisted'=>true,'decision_id'=>'review-123',
    'approved_at'=>'2026-11-12 09:00:00','reviewed_seconds'=>5400,
    'reviewer_id'=>'manager',
];
$entry = OfficeApprovedHoursEvidence::fromVerifiedDecision($session,$decision,'manager');
if ($entry['approved_seconds'] !== 5400 || $entry['source_kind'] !== 'onsite_review'
    || $entry['source_reference'] !== 'onsite:review-123'
    || !preg_match('/^[a-f0-9]{64}$/D',$entry['source_sha256'])) {
    throw new RuntimeException('Approved OnSite mapping failed');
}
$reject = static function (array $s, array $d, string $reviewer): void {
    try {
        OfficeApprovedHoursEvidence::fromVerifiedDecision($s,$d,$reviewer);
    } catch (\DomainException|\InvalidArgumentException $expected) {
        return;
    }
    throw new RuntimeException('Invalid approval evidence was accepted');
};
$reject($session,array_replace($decision,['persisted'=>false]),'manager');
$reject($session,array_replace($decision,['status'=>'pending']),'manager');
$reject($session,array_replace($decision,['reviewer_id'=>'other']),'manager');
$reject($session,array_replace($decision,['reviewed_seconds'=>90000]),'manager');
$reject(array_replace($session,['out_event_id'=>'']),$decision,'manager');
$reject(array_replace($session,['work_date'=>'2026-02-30']),$decision,'manager');
echo "APPROVED_HOURS_EVIDENCE_OK\n";
