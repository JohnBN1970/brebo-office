<?php
declare(strict_types=1);
$root = dirname(__DIR__).'/src/Inzet/';
$approval = file_get_contents($root.'OfficeOnSiteApprovalService.php');
$posting = file_get_contents($root.'OfficeLabourBudgetDebitPostingService.php');
foreach ([
    [str_contains($approval, "OFFICE_TEST_LABOUR_BUDGET_DEBIT_ENABLED"), 'Missing independent feature flag'],
    [substr_count($approval, '$this->postBudgetInsideTransaction(') === 2, 'Initial approval and idempotent retry must both invoke guarded posting'],
    [str_contains($approval, '$this->db->rollBack()'), 'Approval rollback required'],
    [str_contains($approval, '?int $correctedSeconds = null'), 'Partial approval argument required'],
    [str_contains($approval, '$correctedSeconds > $seconds'), 'Partial approval may not exceed presence'],
    [str_contains($approval, '$correctedSeconds !== (int)$verified[\'decision\'][\'reviewed_seconds\']'), 'PDO string durations must compare numerically on idempotent retry'],
    [str_contains($approval, "Already approved OnSite duration cannot be changed by retry"), 'Immutable partial approval required'],
    [str_contains($approval, "Rejected OnSite session cannot be approved"), 'Rejected decisions must never be booked through retry'],
    [str_contains($posting, 'public function postInsideTransaction('), 'Transaction-aware debit required'],
    [str_contains($posting, '!$this->db->inTransaction()'), 'Caller-owned transaction required'],
    [str_contains($posting, 'WHERE approved_hours_id = ? FOR UPDATE'), 'Existing debit lock required'],
    [str_contains($posting, 'OfficeLabourPlannedShiftResolver'), 'Verified planned shift required'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}
// OnSite readiness: 8 hours present, 6 hours approved => 2 hours deducted.
require_once $root.'OnSitePresenceReviewView.php';
$rendered=\Brebo\Office\Inzet\OnSitePresenceReviewView::render([
    'active'=>[],
    'intervals'=>[[
        'person_id'=>17,'project_id'=>1,'zone_id'=>2,
        'entered_at'=>'2026-11-15 08:00:00','left_at'=>'2026-11-15 16:00:00',
        'elapsed_seconds'=>28800,'review_status'=>'approved','reviewed_seconds'=>21600,
    ]],
    'exceptions'=>[],
]);
if (!str_contains($rendered,'6:00 uur') || !str_contains($rendered,'2:00 niet goedgekeurd')
    || !str_contains($rendered,'Aankomst (lokaal)') || !str_contains($rendered,'09:00')) {
    throw new RuntimeException('OnSite partial approval must show deducted hours and local timestamps');
}
$page=file_get_contents(dirname(__DIR__).'/public/office-onsite-presence-review.php');
if (!str_contains($page,"['not_approved']+=") || !str_contains($page,'Niet goedgekeurd</th>')
    || !str_contains($page,'$weekly[$person][\'approved\']+=')) {
    throw new RuntimeException('OnSite week totals must separate approved and deducted hours');
}
$migrate=file_get_contents(dirname(__DIR__,2).'/scripts/deployment/office_test_migrate.php');
$activate=file_get_contents(dirname(__DIR__,2).'/scripts/deployment/office_test_activate.php');
if (!str_contains($migrate,'OFFICE_TEST_ONSITE_REJECTION_SCHEMA_PASS')
    || !str_contains($activate,"OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED")) {
    throw new RuntimeException('OnSite rejection schema must be verified before activation');
}
echo "ONSITE_READINESS_CONTRACT_OK\n";
echo "LABOUR_ATOMIC_APPROVAL_CONTRACT_OK\n";
