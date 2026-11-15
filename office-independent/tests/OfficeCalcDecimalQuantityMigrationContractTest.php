<?php
declare(strict_types=1);
$sql = file_get_contents(dirname(__DIR__).'/deployment/hostinger/sql/office-calc-work-budget-decimal-quantity-v2.sql');
if (!is_string($sql)) throw new RuntimeException('Missing additive decimal migration.');
foreach (['ADD COLUMN quantity_decimal DECIMAL(18,4) NULL','ADD COLUMN unit_cost_decimal DECIMAL(18,4) NULL','ADD COLUMN budget_decimal DECIMAL(22,4) NULL'] as $required) {
    if (!str_contains($sql, $required)) throw new RuntimeException('Missing decimal storage: '.$required);
}
foreach (['DROP ', 'TRUNCATE ', 'DELETE ', 'UPDATE ', 'MODIFY COLUMN quantity ', 'CHANGE COLUMN quantity '] as $forbidden) {
    if (preg_match('/^\\s*'.preg_quote($forbidden,'/').'/mi', $sql)) throw new RuntimeException('Unsafe legacy baseline migration.');
}
echo "CALC_DECIMAL_MIGRATION_CONTRACT=PASS\n";
