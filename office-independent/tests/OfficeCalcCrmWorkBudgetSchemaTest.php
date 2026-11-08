<?php
declare(strict_types=1);
$sql=file_get_contents(dirname(__DIR__) . '/deployment/hostinger/sql/office-calc-crm-work-budget.sql');
if (!is_string($sql)) throw new RuntimeException('Missing CRM Calc schema');
foreach (['office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines','office_calc_work_budget_lines','UNIQUE KEY uq_calc_crm_opportunity','UNIQUE KEY uq_calc_acceptance_key','UNIQUE KEY uq_calc_budget_source_line','FOREIGN KEY'] as $token) {
    if (!str_contains($sql,$token)) throw new RuntimeException('Missing schema invariant: '.$token);
}
foreach (['DROP TABLE','TRUNCATE','DELETE FROM','ALTER TABLE'] as $unsafe) {
    if (str_contains(strtoupper($sql),$unsafe)) throw new RuntimeException('Destructive SQL in migration');
}
echo "CRM_CALC_WORK_BUDGET_SCHEMA=PASS\n";
