<?php
declare(strict_types=1);

// Prevent a silent mismatch between transfer writes and the read-side lock gate.
// The production schema must lock a newly inserted baseline automatically.
$root = dirname(__DIR__);
$sql = file_get_contents($root . '/deployment/hostinger/sql/office-calc-crm-work-budget.sql');
$service = file_get_contents($root . '/src/Calculation/OfficeCalcCrmTransferService.php');
$status = file_get_contents($root . '/src/Crm/OfficeCrmWorkBudgetHandoffStatus.php');
if (!is_string($sql) || !is_string($service) || !is_string($status)) {
    throw new RuntimeException('Missing work-budget handoff contract files');
}
if (!preg_match('/CREATE TABLE IF NOT EXISTS office_calc_work_budget_baselines\s*\\((.*?)\\) ENGINE=/s', $sql, $match)) {
    throw new RuntimeException('Work-budget baseline table missing');
}
if (!preg_match('/locked_at\s+DATETIME\s+NOT NULL\s+DEFAULT\s+CURRENT_TIMESTAMP/i', $match[1])) {
    throw new RuntimeException('A transferred baseline must be locked by default');
}
if (!str_contains($service, 'INSERT INTO office_calc_work_budget_baselines (acceptance_transfer_id, version_number) VALUES (?, 1)')) {
    throw new RuntimeException('Unexpected baseline insert; review lock semantics');
}
if (!str_contains($status, '$row[\'locked_at\'] !== null')) {
    throw new RuntimeException('Read-side lock gate missing');
}
echo "CALC_WORK_BUDGET_LOCK_CONTRACT=PASS\n";
