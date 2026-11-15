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
echo "LABOUR_ATOMIC_APPROVAL_CONTRACT_OK\n";
