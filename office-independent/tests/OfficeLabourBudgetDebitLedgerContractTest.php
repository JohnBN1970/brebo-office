<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeLabourBudgetDebitSchema.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeLabourBudgetDebitPostingService.php';
use Brebo\Office\Inzet\OfficeLabourBudgetDebitSchema;
use Brebo\Office\Inzet\OfficeLabourBudgetDebitPostingService;
$sql = implode("\n", OfficeLabourBudgetDebitSchema::statements());
foreach ([
    'UNIQUE KEY uq_labour_budget_approval (approved_hours_id)',
    'UNIQUE KEY uq_labour_budget_shift (shift_id)',
    'FOREIGN KEY (approved_hours_id) REFERENCES office_inzet_approved_hours(id)',
    'FOREIGN KEY (shift_id) REFERENCES office_inzet_shifts(id)',
] as $required) {
    if (!str_contains($sql, $required)) throw new RuntimeException('Missing ledger invariant: '.$required);
}
if (!class_exists(OfficeLabourBudgetDebitPostingService::class)) {
    throw new RuntimeException('Missing budget posting service');
}
echo "LABOUR_BUDGET_LEDGER_CONTRACT_OK\n";
