<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__) . '/src/Calculation/OfficeCalcCrmTransferService.php');
if (!is_string($source)) throw new RuntimeException('Missing transfer service');
foreach (['verifyCrmAcceptance', 'CRM acceptance is not verified against persisted Office data', 'beginTransaction()', 'commit()', 'rollBack()', 'FOR UPDATE', 'OfficeCalcAcceptanceIdentity::key', 'OfficeCalcWorkBudgetHandoff::prepare', 'office_calc_crm_acceptance_transfers', 'office_calc_work_budget_baselines', 'office_calc_work_budget_lines', 'Persisted locked work budget verification failed', 'Native Office project was not persisted'] as $required) {
    if (!str_contains($source,$required)) throw new RuntimeException('Missing atomic transfer guard: '.$required);
}
$verifyPos = strpos($source, '$verifyCrmAcceptance($this->db, $acceptance, $lines)');
$projectPos = strpos($source, '$createProject($this->db, $acceptance)');
$beginPos = strpos($source, '$this->db->beginTransaction()');
if ($verifyPos === false || $projectPos === false || $beginPos === false
    || !($beginPos < $verifyPos && $verifyPos < $projectPos)) {
    throw new RuntimeException('CRM acceptance must be verified inside transaction before project creation');
}
if (str_contains($source,'CREATE TABLE') || str_contains($source,'ALTER TABLE')) throw new RuntimeException('Unexpected runtime schema mutation');
echo "CRM_CALC_ATOMIC_TRANSFER_CONTRACT=PASS\n";
