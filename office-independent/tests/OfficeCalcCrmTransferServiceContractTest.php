<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__) . '/src/Calculation/OfficeCalcCrmTransferService.php');
if (!is_string($source)) throw new RuntimeException('Missing transfer service');
foreach (['beginTransaction()', 'commit()', 'rollBack()', 'FOR UPDATE', 'OfficeCalcAcceptanceIdentity::key', 'OfficeCalcWorkBudgetHandoff::prepare', 'office_calc_crm_acceptance_transfers', 'office_calc_work_budget_baselines', 'office_calc_work_budget_lines'] as $required) {
    if (!str_contains($source,$required)) throw new RuntimeException('Missing atomic transfer guard: '.$required);
}
if (str_contains($source,'CREATE TABLE') || str_contains($source,'ALTER TABLE')) throw new RuntimeException('Unexpected runtime schema mutation');
echo "CRM_CALC_ATOMIC_TRANSFER_CONTRACT=PASS\n";
