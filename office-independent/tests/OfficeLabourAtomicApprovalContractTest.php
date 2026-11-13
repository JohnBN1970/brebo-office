<?php
declare(strict_types=1);
$root = dirname(__DIR__).'/src/Inzet/';
$approval = file_get_contents($root.'OfficeOnSiteApprovalService.php');
$posting = file_get_contents($root.'OfficeLabourBudgetDebitPostingService.php');
foreach ([
    [str_contains($approval, "OFFICE_TEST_LABOUR_BUDGET_DEBIT_ENABLED"), 'Missing independent feature flag'],
    [substr_count($approval, 'postInsideTransaction(') === 2, 'Initial approval and idempotent retry must both reconcile budget'],
    [str_contains($approval, '$this->db->rollBack()'), 'Approval rollback required'],
    [str_contains($posting, 'public function postInsideTransaction('), 'Transaction-aware debit required'],
    [str_contains($posting, '!$this->db->inTransaction()'), 'Caller-owned transaction required'],
    [str_contains($posting, 'WHERE approved_hours_id = ? FOR UPDATE'), 'Existing debit lock required'],
    [str_contains($posting, 'OfficeLabourPlannedShiftResolver'), 'Verified planned shift required'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}
echo "LABOUR_ATOMIC_APPROVAL_CONTRACT_OK\n";
