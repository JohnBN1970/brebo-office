<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectBudgetPanel.php';
use Brebo\Office\WorkingBudget\ProjectBudgetPanel;

$html=ProjectBudgetPanel::render([
    'project_id'=>42,
    'kpis'=>['baseline_cents'=>120000,'awarded_cents'=>null,'actual_cents'=>null,
        'open_commitment_cents'=>null,'forecast_cents'=>null,'variance_cents'=>null],
    'source_status'=>['baseline'=>'locked','finance'=>'<script>alert(1)</script>'],
]);
if (!str_contains($html,'€ 1.200,00')
    || !str_contains($html,'Nog niet beschikbaar')
    || str_contains($html,'<script>')
    || !str_contains($html,'&lt;script&gt;')) {
    throw new RuntimeException('Budget panel display or escaping failed.');
}
echo "PROJECT_BUDGET_PANEL_TEST=PASS\n";
